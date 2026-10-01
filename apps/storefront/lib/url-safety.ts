/**
 * URL safety helpers for values that cross a trust boundary (query-string
 * redirect targets, backend-provided redirect URLs, CMS-managed menu hrefs).
 */

/**
 * Internal redirect path: must be same-app relative. Rejects protocol-
 * relative ("//evil.com") and backslash ("/\evil.com") forms that browsers
 * treat as absolute URLs.
 */
export function isSafeInternalPath(path: string): boolean {
  return path.startsWith("/") && !path.startsWith("//") && !path.startsWith("/\\");
}

/** URL schemes that execute script or leak data when used in an href. */
const FORBIDDEN_SCHEMES = ["javascript:", "data:", "vbscript:"];

/**
 * Safe CMS-managed href: blocks script/data schemes from stored menu links.
 * Relative URLs pass through unchanged (they resolve against the app).
 */
export function safeCmsHref(url: string | null | undefined): string {
  if (!url) return "#";
  const trimmed = url.trim().toLowerCase().replace(/\s+/g, "");
  if (FORBIDDEN_SCHEMES.some((scheme) => trimmed.startsWith(scheme))) {
    return "#";
  }
  return url;
}

/**
 * Safe external redirect target (e.g. a hosted-checkout URL returned by the
 * payment API): only https is allowed, except http://localhost for local
 * development gateways.
 */
export function isSafeExternalUrl(url: string): boolean {
  try {
    const parsed = new URL(url);
    if (parsed.protocol === "https:") return true;
    return (
      parsed.protocol === "http:" &&
      (parsed.hostname === "localhost" || parsed.hostname === "127.0.0.1")
    );
  } catch {
    return false;
  }
}
