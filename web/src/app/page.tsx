"use client";

import { useState, useRef, useCallback } from "react";
import { useRouter } from "next/navigation";

const PASTE_BUFFER_THRESHOLD = 256 * 1024;
const PASTE_PREVIEW_LINES = 50;

export default function PastePage() {
  const [content, setContent] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);
  const [bufferInfo, setBufferInfo] = useState<string | null>(null);
  const [dragActive, setDragActive] = useState(false);
  const textareaRef = useRef<HTMLTextAreaElement>(null);
  const fileInputRef = useRef<HTMLInputElement>(null);
  // uploadRef always holds the TRUE full text to upload; content state is UI-only gating.
  const uploadRef = useRef("");
  const router = useRouter();

  const showError = useCallback((msg: string) => {
    setError(msg);
    setSaving(false);
    setTimeout(() => setError(null), 5000);
  }, []);

  const handleInput = useCallback(() => {
    setError(null);
    setBufferInfo(null);
    const val = textareaRef.current?.value || "";
    setContent(val);
    uploadRef.current = val;
  }, []);

  const loadBuffered = (text: string) => {
    const lines = text.split("\n");
    const sizeMb = (text.length / 1024 / 1024).toFixed(2);
    setBufferInfo(
      `${lines.length.toLocaleString()} lines, ${sizeMb} MB - full content will upload on Save`
    );
    // Store the FULL text for upload; show only a preview in the textarea/state.
    uploadRef.current = text;
    const preview = lines.slice(0, PASTE_PREVIEW_LINES).join("\n");
    setContent(preview);
    if (textareaRef.current) {
      textareaRef.current.value = `[Large paste buffered: ${lines.length.toLocaleString()} lines, ${sizeMb} MB.\n Full content uploads on Save. Edit this textarea to clear the buffer.]\n\n--- preview: first ${PASTE_PREVIEW_LINES} of ${lines.length.toLocaleString()} lines ---\n${preview}\n--- ${lines.length - PASTE_PREVIEW_LINES} more lines hidden ---`;
      textareaRef.current.readOnly = true;
    }
  };

  const loadFile = useCallback(async (file: File) => {
    if (file.size > 100 * 1024 * 1024) {
      showError("File is too large.");
      return;
    }
    try {
      const buf = await file.arrayBuffer();
      const text = new TextDecoder().decode(buf);
      if (text.includes("\0")) {
        showError("This file is not supported.");
        return;
      }
      if (text.length > PASTE_BUFFER_THRESHOLD) {
        loadBuffered(text);
      } else {
        uploadRef.current = text;
        setContent(text);
        if (textareaRef.current) textareaRef.current.value = text;
      }
    } catch {
      showError("Failed to read file.");
    }
  }, [showError]);

  const handlePaste = useCallback((e: React.ClipboardEvent) => {
    const file = e.clipboardData.files?.[0];
    if (file) {
      e.preventDefault();
      loadFile(file);
      return;
    }
    const text = e.clipboardData.getData("text");
    if (text && text.length > PASTE_BUFFER_THRESHOLD) {
      e.preventDefault();
      loadBuffered(text);
    }
  }, [loadFile]);

  const handleSave = useCallback(async () => {
    // Use the ref so we always upload the full text even when the textarea shows
    // only a buffered preview. The content state is used only to gate the button.
    const payload = uploadRef.current;
    if (!payload.trim()) return;
    setError(null);
    setSaving(true);

    try {
      const res = await fetch("/api/new", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          content: payload,
          source: window.location.host,
        }),
      });
      const data = await res.json();
      if (!res.ok || !data.success) {
        showError(data.error || `${res.status} ${res.statusText}`);
        return;
      }
      // Leave saving=true through the redirect so the button stays disabled.
      router.push(data.url);
    } catch {
      showError("Network error");
    }
  }, [router, showError]);

  const handleDrop = useCallback((e: React.DragEvent) => {
    e.preventDefault();
    const file = e.dataTransfer.files?.[0];
    if (file) loadFile(file);
  }, [loadFile]);

  const handleClipboard = useCallback(async () => {
    try {
      const text = await navigator.clipboard.readText();
      if (!text.trim()) { showError("Clipboard is empty."); return; }
      if (text.length > PASTE_BUFFER_THRESHOLD) {
        loadBuffered(text);
      } else {
        uploadRef.current = text;
        setContent(text);
        if (textareaRef.current) textareaRef.current.value = text;
      }
    } catch {
      showError("Clipboard is not accessible.");
    }
  }, [showError]);

  const handleClear = useCallback(() => {
    setContent("");
    setBufferInfo(null);
    setError(null);
    uploadRef.current = "";
    if (textareaRef.current) {
      textareaRef.current.value = "";
      textareaRef.current.readOnly = false;
      textareaRef.current.focus();
    }
  }, []);

  const lines = content ? content.split("\n").length : 0;
  const bytes = content ? new TextEncoder().encode(content).length : 0;
  const formatBytes = (b: number) =>
    b < 1024 ? `${b} B` : b < 1024 * 1024 ? `${(b / 1024).toFixed(1)} KB` : `${(b / 1024 / 1024).toFixed(1)} MB`;

  return (
    <main className="relative z-10 mx-auto flex w-full max-w-[1400px] flex-1 flex-col px-[var(--page-padding)] py-[clamp(0.85rem,2.5vw,1.5rem)]">
      <div
        onDragOver={(e) => {
          e.preventDefault();
          if (!dragActive) setDragActive(true);
        }}
        onDragLeave={() => setDragActive(false)}
        onDrop={(e) => {
          setDragActive(false);
          handleDrop(e);
        }}
        className={`relative flex flex-1 flex-col overflow-hidden rounded-[var(--radius-panel)] bg-[var(--bg-surface)] shadow-[var(--shadow-panel)] outline outline-1 transition-[outline-color] duration-150 focus-within:ring-2 focus-within:ring-[var(--info)] focus-within:ring-inset ${
          dragActive ? "outline-[var(--accent)]" : "outline-transparent"
        }`}
      >
        {/* console head */}
        <div className="flex items-center justify-between gap-3 border-b border-[var(--border)] px-4 py-2.5">
          <span className="flex items-center gap-2 font-[var(--font-mono)] text-[0.75rem] text-[var(--text-muted)]">
            <span className="inline-block h-2 w-2 rounded-full bg-[var(--accent)]" aria-hidden="true" />
            log input
          </span>
          {content && (
            <span className="font-[var(--font-mono)] text-[0.72rem] tabular-nums text-[var(--text-muted)]">
              {lines.toLocaleString()} {lines === 1 ? "line" : "lines"} &middot; {formatBytes(bytes)}
            </span>
          )}
        </div>

        {/* console body */}
        <div className="relative flex flex-1 flex-col">
          {!content && (
            <div className="pointer-events-none absolute inset-0 z-[2] flex flex-col items-center justify-center px-6 text-center">
              <p className="font-[var(--font-sans)] text-[clamp(1.15rem,3.5vw,1.6rem)] font-semibold text-[var(--text)] [text-wrap:balance]">
                Paste or drop a log.
              </p>
              <p className="mt-2 font-[var(--font-mono)] text-[clamp(0.78rem,2vw,0.85rem)] text-[var(--text-muted)]">
                we read it and tell you what broke
              </p>
              <div className="pointer-events-auto mt-6 flex flex-wrap items-center justify-center gap-2 font-[var(--font-mono)] text-[0.8rem]">
                <button
                  type="button"
                  onClick={handleClipboard}
                  className="inline-flex h-9 items-center gap-1.5 rounded-[var(--radius-md)] border border-[var(--border)] bg-transparent px-4 text-[var(--text)] transition-colors duration-150 hover:border-[var(--info)] hover:bg-[var(--bg-elevated)]"
                >
                  <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden="true"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/></svg>
                  paste
                </button>
                <button
                  type="button"
                  onClick={() => fileInputRef.current?.click()}
                  className="inline-flex h-9 items-center gap-1.5 rounded-[var(--radius-md)] border border-[var(--border)] bg-transparent px-4 text-[var(--text)] transition-colors duration-150 hover:border-[var(--info)] hover:bg-[var(--bg-elevated)]"
                >
                  <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden="true"><path d="M22 19a2 2 0 01-2 2H4a2 2 0 01-2-2V5a2 2 0 012-2h5l2 3h9a2 2 0 012 2z"/></svg>
                  browse
                </button>
                <span className="text-[var(--text-muted)]">or drop a file</span>
              </div>
            </div>
          )}

          <textarea
            ref={textareaRef}
            spellCheck={false}
            onInput={handleInput}
            onPaste={handlePaste}
            className="relative z-[1] w-full flex-1 resize-none bg-transparent p-[clamp(0.85rem,2.5vw,1.25rem)] font-[var(--font-mono)] text-[clamp(0.78rem,2vw,0.875rem)] leading-[1.7] text-[var(--text)] outline-none"
            placeholder=""
            aria-label="Paste or drop your log here"
          />
          <input
            ref={fileInputRef}
            type="file"
            className="hidden"
            onChange={(e) => {
              const file = e.target.files?.[0];
              if (file) loadFile(file);
              e.target.value = "";
            }}
          />

          {bufferInfo && (
            <div className="absolute bottom-4 left-1/2 z-[4] -translate-x-1/2 rounded-[var(--radius-sm)] border border-[var(--border)] bg-[var(--bg-elevated)] px-3 py-1.5 font-[var(--font-mono)] text-[0.75rem] text-[var(--text-muted)] shadow-[var(--shadow-panel)]">
              {bufferInfo}
            </div>
          )}
        </div>

        {/* action bar */}
        <div className="flex items-center justify-between gap-3 border-t border-[var(--border)] px-4 py-3">
          <span className="truncate font-[var(--font-mono)] text-[0.72rem] text-[var(--text-muted)]">
            {content ? "kept 90 days from last view" : "minecraft · project zomboid · hytale"}
          </span>
          <div className="flex shrink-0 items-center gap-2">
            {content && !saving && (
              <button
                type="button"
                onClick={handleClear}
                className="inline-flex h-10 items-center gap-1.5 rounded-[var(--radius-md)] border border-[var(--border)] bg-transparent px-4 font-[var(--font-mono)] text-[0.8rem] text-[var(--text-muted)] transition-colors duration-150 hover:border-[var(--text-muted)] hover:text-[var(--text)]"
              >
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" aria-hidden="true"><path d="M18 6L6 18M6 6l12 12" /></svg>
                clear
              </button>
            )}
            <button
              type="button"
              disabled={!content || saving}
              onClick={handleSave}
              className="inline-flex h-10 items-center gap-2 rounded-[var(--radius-md)] bg-[var(--accent)] px-6 font-[var(--font-sans)] text-[0.9rem] font-bold text-[var(--brand-ink)] transition-all duration-150 [transition-timing-function:var(--ease-out)] hover:bg-[var(--accent-hover)] hover:shadow-[var(--shadow-card)] active:scale-[0.97] disabled:cursor-not-allowed disabled:opacity-55 disabled:hover:bg-[var(--accent)] disabled:hover:shadow-none disabled:active:scale-100"
            >
              {saving ? (
                <svg className="motion-safe:animate-spin" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" aria-hidden="true"><path d="M21 12a9 9 0 11-6.22-8.56" /></svg>
              ) : (
                <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M6 4l14 8-14 8z" /></svg>
              )}
              {saving ? "saving" : "save log"}
            </button>
          </div>
        </div>

        {error && (
          <div className="absolute right-4 top-4 z-50 flex items-center gap-2 rounded-[var(--radius-md)] border border-[var(--error-border)] bg-[var(--error-bg)] px-4 py-2.5 font-[var(--font-mono)] text-[0.8rem] font-medium text-[var(--error)] shadow-[var(--shadow-panel)] animate-[errorSlideIn_0.3s_var(--ease-out)]">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden="true">
              <path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
              <path d="M12 9v4M12 17h.01" />
            </svg>
            {error}
          </div>
        )}
      </div>
    </main>
  );
}
