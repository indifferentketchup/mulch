import { getDb } from "./mongodb";
import type { LogDocument, CreateLogRequest } from "./types";
import { randomBytes } from "crypto";

const ID_LENGTH = parseInt(process.env.IBLOGS_ID_LENGTH || "7");
const STORAGE_TTL = parseInt(process.env.IBLOGS_STORAGE_TTL || "7776000");

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

export async function createLog(data: CreateLogRequest): Promise<{
  id: string;
  token: string;
}> {
  const db = await getDb();
  const id = generateId();
  const token = generateToken();
  const now = new Date();

  await db.collection<LogDocument>("logs").insertOne({
    _id: id,
    content: data.content,
    source: data.source,
    metadata: data.metadata,
    token,
    created: now,
    accessed: now,
  } as LogDocument);

  analyzeLog(id, data.content).catch((e) =>
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
  const analysis = await res.json();
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
  const result = await db
    .collection<LogDocument>("logs")
    .deleteOne({ _id: id, token });
  return result.deletedCount > 0;
}
