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
  const textareaRef = useRef<HTMLTextAreaElement>(null);
  const fileInputRef = useRef<HTMLInputElement>(null);
  const router = useRouter();

  const showError = useCallback((msg: string) => {
    setError(msg);
    setSaving(false);
    setTimeout(() => setError(null), 5000);
  }, []);

  const handleInput = useCallback(() => {
    setError(null);
    setBufferInfo(null);
    setContent(textareaRef.current?.value || "");
  }, []);

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
  }, []);

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
        setContent(text);
        if (textareaRef.current) textareaRef.current.value = text;
      }
    } catch {
      showError("Failed to read file.");
    }
  }, [showError]);

  const loadBuffered = useCallback((text: string) => {
    const lines = text.split("\n");
    const sizeMb = (text.length / 1024 / 1024).toFixed(2);
    setBufferInfo(
      `${lines.length.toLocaleString()} lines, ${sizeMb} MB — full content will upload on Save`
    );
    const preview = lines.slice(0, PASTE_PREVIEW_LINES).join("\n");
    setContent(preview);
    if (textareaRef.current) {
      textareaRef.current.value = `[Large paste buffered: ${lines.length.toLocaleString()} lines, ${sizeMb} MB.\n Full content uploads on Save. Edit this textarea to clear the buffer.]\n\n--- preview: first ${PASTE_PREVIEW_LINES} of ${lines.length.toLocaleString()} lines ---\n${preview}\n--- ${lines.length - PASTE_PREVIEW_LINES} more lines hidden ---`;
      textareaRef.current.readOnly = true;
    }
  }, []);

  const handleSave = useCallback(async () => {
    if (!content.trim()) return;
    setError(null);
    setSaving(true);

    try {
      const res = await fetch("/api/new", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          content,
          source: window.location.host,
        }),
      });
      const data = await res.json();
      if (!res.ok || !data.success) {
        showError(data.error || `${res.status} ${res.statusText}`);
        return;
      }
      router.push(data.url);
    } catch {
      showError("Network error");
    }
  }, [content, router, showError]);

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
        setContent(text);
        if (textareaRef.current) textareaRef.current.value = text;
      }
    } catch {
      showError("Clipboard is not accessible.");
    }
  }, [showError, loadBuffered]);

  return (
    <main className="relative z-10 mx-auto flex w-full max-w-[min(100%,calc(1400px-var(--page-padding)*2))] flex-1 flex-col overflow-hidden rounded-[12px] bg-[var(--bg-surface)]">
      <div
        className="relative flex flex-1 flex-col rounded-[12px] border-2 border-dashed border-transparent transition-colors duration-250"
        onDragOver={(e) => e.preventDefault()}
        onDrop={handleDrop}
      >
        {!content && (
          <div className="absolute top-1/2 left-1/2 z-[2] flex -translate-x-1/2 -translate-y-1/2 flex-col items-center text-[clamp(1rem,3vw,1.5rem)] pointer-events-none">
            <svg className="mb-[clamp(0.5rem,2vw,1.5rem)] h-[clamp(2rem,8vw,3.5rem)] w-[clamp(2rem,8vw,3.5rem)] text-[var(--text-muted)]" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
              <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M7 10l5 5 5-5M12 15V3" />
            </svg>
            <p className="mb-[clamp(1.2rem,2vw,1.5rem)] font-semibold text-[var(--text)]">
              Paste or drop your log here
            </p>
            <div className="flex gap-[clamp(1rem,3vw,1.5rem)] text-[clamp(0.75rem,1.8vw,0.8rem)] text-[var(--text-muted)]">
              <button
                type="button"
                onClick={handleClipboard}
                className="pointer-events-auto flex items-center gap-2 rounded-[8px] bg-transparent px-4 py-[clamp(0.35rem,1.5vw,0.4rem)] text-[var(--accent)] transition hover:bg-[#00000014]"
              >
                Paste
              </button>
              <button
                type="button"
                onClick={() => fileInputRef.current?.click()}
                className="pointer-events-auto flex items-center gap-2 rounded-[8px] bg-transparent px-4 py-[clamp(0.35rem,1.5vw,0.4rem)] text-[var(--accent)] transition hover:bg-[#00000014]"
              >
                Browse
              </button>
              <span className="pointer-events-auto flex items-center gap-2">Drop</span>
            </div>
          </div>
        )}

        <textarea
          ref={textareaRef}
          spellCheck={false}
          onInput={handleInput}
          onPaste={handlePaste}
          className="z-[1] flex-1 w-full resize-none bg-transparent p-[clamp(0.5rem,3vw,1.2rem)] font-[var(--font-mono)] text-[clamp(0.75rem,2vw,0.9rem)] text-[var(--text)] outline-none"
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

        <div className="absolute bottom-0 left-0 right-0 z-[5] h-[120px] rounded-b-[12px] bg-gradient-to-b from-transparent via-[color-mix(in_srgb,var(--bg-surface)_40%,transparent)] to-[var(--bg-surface)] pointer-events-none" />

        {bufferInfo && (
          <div className="absolute bottom-28 left-1/2 z-10 -translate-x-1/2 rounded-[6px] bg-[var(--surface)] border border-[var(--border)] px-4 py-2 text-[clamp(0.75rem,1.8vw,0.8rem)] text-[var(--text-muted)] font-[var(--font-mono)]">
            {bufferInfo}
          </div>
        )}

        {content && (
          <button
            type="button"
            disabled={saving}
            onClick={handleSave}
            className="absolute bottom-6 left-1/2 z-10 -translate-x-1/2 rounded-[8px] bg-[var(--accent)] px-8 py-[clamp(0.6rem,2vw,0.7rem)] text-[clamp(0.85rem,2vw,0.9rem)] font-semibold text-[var(--bg)] transition-all duration-150 hover:bg-[color-mix(in_srgb,var(--accent)_78%,var(--bg)_22%)] disabled:opacity-50 disabled:cursor-not-allowed focus-visible:outline-2 focus-visible:outline-[var(--accent)] focus-visible:outline-offset-2 active:scale-[0.97]"
          >
            {saving ? "Saving..." : "Save"}
          </button>
        )}

        {error && (
          <div className="absolute top-[clamp(1rem,2.5vw,1.5rem)] right-[clamp(1rem,2.5vw,1.5rem)] z-[1000] rounded-[8px] bg-[var(--error-bg)] border border-[var(--error-border)] px-[clamp(1rem,2.5vw,1.25rem)] py-[clamp(0.7rem,2vw,0.8rem)] text-[clamp(0.85rem,2vw,0.9rem)] font-semibold text-[var(--error)] animate-[errorSlideIn_0.3s_ease-out]">
            {error}
          </div>
        )}
      </div>
    </main>
  );
}
