"use client";

import {
  createContext,
  useCallback,
  useContext,
  useState,
  type ReactNode,
} from "react";
import {
  DEFAULT_SETTINGS,
  SETTINGS_COOKIE_NAME,
  type LogSettingKey,
  type LogSettingsState,
} from "@/lib/log-settings";

const COOKIE_MAX_AGE = 100 * 365 * 24 * 60 * 60;

interface LogSettingsContextValue {
  settings: LogSettingsState;
  toggle: (key: LogSettingKey) => void;
}

const LogSettingsContext = createContext<LogSettingsContextValue | null>(null);

export function LogSettingsProvider({
  initial,
  children,
}: {
  initial?: Partial<LogSettingsState>;
  children: ReactNode;
}) {
  const [settings, setSettings] = useState<LogSettingsState>(() => ({
    ...DEFAULT_SETTINGS,
    ...initial,
  }));

  const toggle = useCallback((key: LogSettingKey) => {
    setSettings((prev) => {
      const next = { ...prev, [key]: !prev[key] };
      try {
        document.cookie = `${SETTINGS_COOKIE_NAME}=${encodeURIComponent(
          JSON.stringify(next)
        )};path=/;max-age=${COOKIE_MAX_AGE};samesite=lax`;
      } catch {
        /* cookies unavailable; settings stay in-memory for this session */
      }
      return next;
    });
  }, []);

  return (
    <LogSettingsContext.Provider value={{ settings, toggle }}>
      {children}
    </LogSettingsContext.Provider>
  );
}

export function useLogSettings(): LogSettingsContextValue {
  const value = useContext(LogSettingsContext);
  if (!value) {
    throw new Error("useLogSettings must be used within a LogSettingsProvider");
  }
  return value;
}
