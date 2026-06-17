"use client";

import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useRef,
  useState,
  type ReactNode,
} from "react";
import {
  DEFAULT_SETTINGS,
  SETTINGS_COOKIE_NAME,
  SETTINGS_COOKIE_VERSION,
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

  const channelRef = useRef<BroadcastChannel | null>(null);

  useEffect(() => {
    if (typeof BroadcastChannel === "undefined") return;

    const channel = new BroadcastChannel("iblogs-settings");
    channelRef.current = channel;

    channel.onmessage = (event: MessageEvent) => {
      if (
        event.data &&
        event.data.type === "settings-updated" &&
        event.data.settings
      ) {
        setSettings((prev) => ({ ...prev, ...event.data.settings }));
      }
    };

    return () => {
      channel.close();
      channelRef.current = null;
    };
  }, []);

  const toggle = useCallback((key: LogSettingKey) => {
    setSettings((prev) => {
      const next = { ...prev, [key]: !prev[key] };
      try {
        document.cookie = `${SETTINGS_COOKIE_NAME}=${encodeURIComponent(
          JSON.stringify({ version: SETTINGS_COOKIE_VERSION, ...next })
        )};path=/;max-age=${COOKIE_MAX_AGE};samesite=lax`;
      } catch {
        /* cookies unavailable; settings stay in-memory for this session */
      }
      try {
        channelRef.current?.postMessage({ type: "settings-updated", settings: next });
      } catch {
        /* BroadcastChannel send failed; other tabs will not be notified */
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
