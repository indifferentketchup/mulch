import { getDb, getGridFsBucket } from "./mongodb";
import type { LogDocument, CreateLogRequest, AnalysisData } from "./types";
import {
  applyUploadFilters,
  byteLength,
  lineCount,
  DEFAULT_LIMIT_BYTES,
  DEFAULT_LIMIT_LINES,
} from "./filters";
import { randomBytes } from "crypto";
import { ObjectId } from "mongodb";

const ID_LENGTH = parseInt(process.env.IBLOGS_ID_LENGTH || "7");

export const LIMIT_BYTES = DEFAULT_LIMIT_BYTES;
export const LIMIT_LINES = DEFAULT_LIMIT_LINES;

/**
 * Thrown by createLog (and validateLogContent) when submitted content fails a
 * pre-storage check. `status` is the HTTP status callers should surface (400
 * for empty content, 413 for over-limit).
 */
export class LogValidationError extends Error {
  constructor(
    public readonly status: number,
    message: string,
  ) {
    super(message);
    this.name = "LogValidationError";
  }
}

/**
 * Shared pre-storage validation so every create path (/api/new and the v1
 * REST alias) rejects empty / oversized content identically. Mirrors the
 * checks the PHP CreateLogAction applied before persisting.
 */
export function validateLogContent(content: string | undefined): void {
  if (!content || content.trim().length === 0) {
    throw new LogValidationError(400, "Content is required.");
  }
  if (byteLength(content) > LIMIT_BYTES) {
    throw new LogValidationError(
      413,
      `Log is too large. Maximum ${(LIMIT_BYTES / 1024 / 1024).toFixed(0)} MB.`,
    );
  }
  if (lineCount(content) > LIMIT_LINES) {
    throw new LogValidationError(
      413,
      `Log has too many lines. Maximum ${LIMIT_LINES} lines.`,
    );
  }
}

function generateId(): string {
  const chars = "abcdefghijklmnopqrstuvwxyz0123456789";
  const bytes = randomBytes(ID_LENGTH);
  let id = "";
  for (let i = 0; i < ID_LENGTH; i++) {
    id += chars[bytes[i] % chars.length];
  }
  return id;
}

function generateToken(): string {
  return randomBytes(32).toString("hex");
}

async function storeContent(content: string, id: string): Promise<string> {
  const bucket = await getGridFsBucket();
  const fileId = new ObjectId();

  await new Promise<void>((resolve, reject) => {
    const upload = bucket.openUploadStreamWithId(fileId, `${id}.log`, {
      metadata: { log_id: id },
    });
    upload.on("error", reject);
    upload.on("finish", () => resolve());
    upload.end(Buffer.from(content, "utf8"));
  });

  return fileId.toHexString();
}

async function loadContent(contentFileId: string): Promise<string> {
  const bucket = await getGridFsBucket();
  const chunks: Buffer[] = [];

  await new Promise<void>((resolve, reject) => {
    const stream = bucket.openDownloadStream(new ObjectId(contentFileId));
    stream.on("data", (chunk: Buffer | Uint8Array | string) => {
      chunks.push(
        typeof chunk === "string" ? Buffer.from(chunk) : Buffer.from(chunk)
      );
    });
    stream.on("error", reject);
    stream.on("end", () => resolve());
  });

  return Buffer.concat(chunks).toString("utf8");
}

async function deleteContent(contentFileId: string): Promise<void> {
  const bucket = await getGridFsBucket();
  await bucket.delete(new ObjectId(contentFileId));
}

export async function createLog(data: CreateLogRequest): Promise<{
  id: string;
  token: string;
}> {
  // Validate the raw submission, then run the upload filter pipeline (trim,
  // byte/line caps, PZ PII redaction, username + access-token redaction)
  // BEFORE anything is stored or sent to the analyzer.
  validateLogContent(data.content);
  const content = await applyUploadFilters(data.content);

  const db = await getDb();
  const id = generateId();
  const token = generateToken();
  const now = new Date();
  const contentFileId = await storeContent(content, id);

  await db.collection<LogDocument>("logs").insertOne({
    _id: id,
    content_file_id: contentFileId,
    source: data.source,
    metadata: data.metadata,
    token,
    created: now,
    accessed: now,
  } as LogDocument);

  analyzeLog(id, content).catch((e) =>
    console.error("Analysis failed for", id, e)
  );

  return { id, token };
}

async function analyzeLog(id: string, content: string): Promise<void> {
  const analyzerUrl =
    process.env.ANALYZER_URL || "http://analyzer:8080";
  const res = await fetch(`${analyzerUrl}/analyze`, {
    method: "POST",
    headers: { "Content-Type": "text/plain" },
    body: content,
  });
  if (!res.ok) {
    console.error(`Analyzer returned ${res.status}`);
    return;
  }
  const raw = await res.json();
  const analysis: AnalysisData = {
    detected: raw.detected ?? "Generic",
    title: raw.title ?? "",
    lines: raw.lines ?? 0,
    errors: raw.errors ?? 0,
    entries: raw.entries ?? [],
    problems: raw.analysis?.problems ?? [],
    information: raw.analysis?.information ?? [],
    gated: raw.analysis?.gated ?? [],
  };
  const db = await getDb();
  await db.collection<LogDocument>("logs").updateOne(
    { _id: id },
    { $set: { analysis } as Partial<LogDocument> }
  );
}

export async function getLog(id: string): Promise<LogDocument | null> {
  const db = await getDb();
  const log = await db
    .collection<LogDocument>("logs")
    .findOne({ _id: id });

  if (log) {
    if (typeof log.content !== "string" && log.content_file_id) {
      log.content = await loadContent(log.content_file_id);
    }
    await db.collection<LogDocument>("logs").updateOne(
      { _id: id },
      { $set: { accessed: new Date() } as Partial<LogDocument> }
    );
  }

  return log;
}

export async function getRawLog(id: string): Promise<string | null> {
  const log = await getLog(id);
  return log?.content ?? null;
}

export async function deleteLog(id: string, token: string): Promise<boolean> {
  const db = await getDb();
  const log = await db
    .collection<LogDocument>("logs")
    .findOne({ _id: id, token }, { projection: { content_file_id: 1 } });
  if (!log) {
    return false;
  }
  const result = await db
    .collection<LogDocument>("logs")
    .deleteOne({ _id: id, token });
  if (result.deletedCount > 0 && log.content_file_id) {
    await deleteContent(log.content_file_id);
  }
  return result.deletedCount > 0;
}
