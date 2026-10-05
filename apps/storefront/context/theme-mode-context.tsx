"use client";

import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useLayoutEffect,
  useMemo,
  useState,
  type ReactNode,
} from "react";
import {
  applyThemeMode,
  readThemeMode,
  resolveThemeMode,
  writeThemeMode,
  type ResolvedThemeMode,
  type ThemeMode,
} from "@/lib/theme";

interface ThemeModeContextValue {
  /** The shopper's stored preference — "system" resolves against the OS. */
  preference: ThemeMode;
  /** The concrete mode currently applied to <html data-mode>. */
  resolved: ResolvedThemeMode;
  setPreference: (mode: ThemeMode) => void;
}

const ThemeModeContext = createContext<ThemeModeContextValue | null>(null);

/**
 * Mirrors the shopper's display-mode preference (theme-mode cookie) into
 * React state and keeps <html data-mode> in sync. The inline script in the
 * root layout already applied the cookie before paint; this provider takes
 * over for toggles and live OS changes while "system" is active.
 */
export function ThemeModeProvider({ children }: { children: ReactNode }) {
  // Lazy initializer reads the same cookie the pre-paint script did, so the
  // first client state matches the DOM the script produced.
  const [preference, setPreferenceState] =
    useState<ThemeMode>(() => readThemeMode());

  // Dev-only Strict Mode remount resets <html> to its JSX attributes,
  // clearing what the init script set — re-apply before paint. No-op in
  // production when the attribute already matches.
  useLayoutEffect(() => {
    applyThemeMode(preference);
  }, [preference]);

  // While "system" is active, follow OS changes live.
  useEffect(() => {
    if (preference !== "system" || !window.matchMedia) return;
    const query = window.matchMedia("(prefers-color-scheme: dark)");
    const onChange = () => applyThemeMode("system");
    query.addEventListener("change", onChange);
    return () => query.removeEventListener("change", onChange);
  }, [preference]);

  const setPreference = useCallback((mode: ThemeMode) => {
    setPreferenceState(mode);
    writeThemeMode(mode);
    applyThemeMode(mode);
  }, []);

  const value = useMemo(
    () => ({ preference, resolved: resolveThemeMode(preference), setPreference }),
    [preference, setPreference],
  );

  return (
    <ThemeModeContext.Provider value={value}>
      {children}
    </ThemeModeContext.Provider>
  );
}

export function useThemeMode(): ThemeModeContextValue {
  const context = useContext(ThemeModeContext);
  if (!context) {
    throw new Error("useThemeMode must be used within ThemeModeProvider");
  }
  return context;
}
