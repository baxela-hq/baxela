// Theme registry and shopper display-mode helpers. Two axes drive the
// attributes on <html> (see app/globals.css):
//
//  - data-theme: the brand palette, chosen by the admin via the
//    storefront_theme setting and resolved server-side in the root layout.
//    The storefront owns this registry — unknown setting values fall back
//    to the default theme instead of failing.
//  - data-mode: light/dark, the shopper's preference. It lives in the
//    theme-mode cookie so THEME_MODE_INIT_SCRIPT can apply it before paint
//    (no flash); the cookie — not localStorage — so the value is readable
//    wherever <html> is rendered.

export const SITE_THEMES = ["default"] as const;
export type SiteTheme = (typeof SITE_THEMES)[number];
export const DEFAULT_SITE_THEME: SiteTheme = "default";

export function resolveSiteTheme(value: string | null | undefined): SiteTheme {
  return (SITE_THEMES as readonly string[]).includes(value ?? "")
    ? (value as SiteTheme)
    : DEFAULT_SITE_THEME;
}

export const THEME_MODES = ["light", "dark", "system"] as const;
export type ThemeMode = (typeof THEME_MODES)[number];
export type ResolvedThemeMode = "light" | "dark";
export const DEFAULT_THEME_MODE: ThemeMode = "light";

export const THEME_MODE_COOKIE = "theme-mode";

export function isThemeMode(
  value: string | null | undefined,
): value is ThemeMode {
  return (THEME_MODES as readonly string[]).includes(value ?? "");
}

/** Resolve the "system" preference against the OS setting; SSR falls back to light. */
export function resolveThemeMode(mode: ThemeMode): ResolvedThemeMode {
  if (mode !== "system") return mode;
  if (typeof window === "undefined" || !window.matchMedia) return "light";
  return window.matchMedia("(prefers-color-scheme: dark)").matches
    ? "dark"
    : "light";
}

export function readThemeMode(): ThemeMode {
  if (typeof document === "undefined") return DEFAULT_THEME_MODE;
  const match = document.cookie.match(
    /(?:^|; )theme-mode=(light|dark|system)\b/,
  );
  return match ? (match[1] as ThemeMode) : DEFAULT_THEME_MODE;
}

export function writeThemeMode(mode: ThemeMode): void {
  if (typeof document === "undefined") return;
  // One year, path-scoped, lax — SameSite only, so dev over plain http works.
  document.cookie = `${THEME_MODE_COOKIE}=${mode}; path=/; max-age=31536000; samesite=lax`;
}

/** Apply a preference to <html> and return the resolved light/dark value. */
export function applyThemeMode(mode: ThemeMode): ResolvedThemeMode {
  const resolved = resolveThemeMode(mode);
  if (typeof document !== "undefined") {
    document.documentElement.dataset.mode = resolved;
  }
  return resolved;
}

/**
 * Rendered inline as the first child of <body> in the root layout: applies
 * the cookie preference to <html data-mode> before first paint so a dark
 * preference never flashes light. Kept in sync with the helpers above —
 * the layout cannot ship client JS here, so this is plain script.
 */
export const THEME_MODE_INIT_SCRIPT = `(function(){try{var m=document.cookie.match(/(?:^|; )theme-mode=(light|dark|system)\\b/);var p=m?m[1]:"light";var d=p==="dark"||(p==="system"&&window.matchMedia&&window.matchMedia("(prefers-color-scheme: dark)").matches);document.documentElement.dataset.mode=d?"dark":"light"}catch(e){document.documentElement.dataset.mode="light"}})();`;
