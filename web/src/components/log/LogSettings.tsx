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
        className="inline-flex items-center gap-1.5 rounded-[8px] bg-[var(--surface)] border border-[var(--border)] px-[clamp(0.35rem,1.5vw,0.4rem)] py-[clamp(0.35rem,1.5vw,0.4rem)] font-semibold text-[clamp(0.75rem,1.8vw,0.8rem)] text-[var(--text)] transition-colors hover:bg-[var(--accent-bg)]"
        onClick={() => setOpen(!open)}
        aria-expanded={open}
        aria-haspopup="true"
      >
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg>
        Settings
      </button>
      {open && (
        <>
          <div className="fixed inset-0 z-30" onClick={() => setOpen(false)} />
          <div
            className="absolute right-0 bottom-full mb-2 z-40 min-w-[260px] rounded-[8px] bg-[var(--bg-surface)] border border-[var(--border)] p-2 shadow-[0_4px_20px_rgba(0,0,0,0.3)]"
            role="menu"
          >
            {groups.map((group, gi) => (
              <div key={group}>
                <div className="px-3 pt-2 pb-1 text-[clamp(0.65rem,1.5vw,0.7rem)] font-semibold uppercase tracking-[0.06em] text-[var(--text-muted)] select-none">
                  {group}
                </div>
                {SETTINGS.filter((s) => s.group === group).map((setting) => (
                  <div key={setting.key}>
                    <label className="flex cursor-pointer items-center justify-between gap-4 rounded-[6px] px-3 py-2 transition-colors hover:bg-[var(--surface)]">
                      <span className="text-sm text-[var(--text)]">{setting.label}</span>
                      <div className="relative h-5.5 w-10 shrink-0">
                        <input
                          type="checkbox"
                          checked={!!settings[setting.key]}
                          onChange={() => toggle(setting.key)}
                          className="peer sr-only"
                        />
                        <div className="h-5.5 w-10 rounded-full bg-[var(--surface)] transition-colors duration-150 peer-checked:bg-[var(--accent)]" />
                        <div className="absolute top-[3px] left-[3px] h-[18px] w-[18px] rounded-full bg-[var(--text-muted)] transition-all duration-150 peer-checked:left-[19px] peer-checked:bg-[var(--bg)]" />
                      </div>
                    </label>
                    <p className="px-3 pb-1 -mt-0.5 text-[clamp(0.7rem,1.6vw,0.75rem)] leading-relaxed text-[var(--text-muted)]">
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
