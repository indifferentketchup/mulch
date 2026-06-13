"use client";

import { useState } from "react";
import { SETTINGS } from "@/lib/log-settings";
import { useLogSettings } from "./LogSettingsContext";

export function LogSettings() {
  const [open, setOpen] = useState(false);
  const { settings, toggle } = useLogSettings();

  const groups = Array.from(new Set(SETTINGS.map((s) => s.group)));

  return (
    <div className="relative">
      <button
        type="button"
        className="inline-flex h-8 items-center gap-1.5 rounded-[var(--radius-md)] border border-[var(--border)] bg-[var(--bg-elevated)] px-3 font-[var(--font-mono)] text-[0.75rem] text-[var(--text-muted)] transition-colors duration-150 hover:border-[var(--text-muted)] hover:text-[var(--text)]"
        onClick={() => setOpen(!open)}
        aria-expanded={open}
        aria-haspopup="true"
      >
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 11-2.83 2.83l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 11-2.83-2.83l.06-.06a1.65 1.65 0 00.33-1.82 1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 112.83-2.83l.06.06a1.65 1.65 0 001.82.33H9a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 112.83 2.83l-.06.06a1.65 1.65 0 00-.33 1.82V9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg>
        settings
      </button>
      {open && (
        <>
          <div className="fixed inset-0 z-30" onClick={() => setOpen(false)} />
          <div
            className="absolute bottom-full left-0 z-40 mb-2 min-w-[280px] rounded-[var(--radius-md)] border border-[var(--border)] bg-[var(--bg-elevated)] p-2 shadow-[var(--shadow-panel)]"
            role="menu"
          >
            {groups.map((group, gi) => (
              <div key={group}>
                <div className="px-3 pb-1 pt-2 font-[var(--font-mono)] text-[0.66rem] font-semibold uppercase tracking-[0.08em] text-[var(--text-muted)] opacity-70 select-none">
                  {group}
                </div>
                {SETTINGS.filter((s) => s.group === group).map((setting) => (
                  <div key={setting.key} className="rounded-[var(--radius-sm)] px-1 py-1.5 transition-colors hover:bg-[var(--bg-surface)]">
                    <label className="flex cursor-pointer items-center justify-between gap-4 px-2">
                      <span className="font-[var(--font-mono)] text-[0.8rem] text-[var(--text)]">{setting.label}</span>
                      <span className="relative h-5 w-9 shrink-0">
                        <input
                          type="checkbox"
                          checked={!!settings[setting.key]}
                          onChange={() => toggle(setting.key)}
                          className="peer sr-only"
                        />
                        <span className="block h-5 w-9 rounded-full bg-[var(--border)] transition-colors duration-150 peer-checked:bg-[var(--info)]" />
                        <span className="absolute left-[2px] top-[2px] block h-4 w-4 rounded-full bg-[var(--text-muted)] transition-all duration-150 peer-checked:left-[18px] peer-checked:bg-[var(--bg)]" />
                      </span>
                    </label>
                    <p className="mt-0.5 px-2 font-[var(--font-sans)] text-[0.72rem] leading-snug text-[var(--text-muted)]">
                      {setting.desc}
                    </p>
                  </div>
                ))}
                {gi < groups.length - 1 && (
                  <div className="mx-2 my-1 h-px bg-[var(--border)]" />
                )}
              </div>
            ))}
          </div>
        </>
      )}
    </div>
  );
}
