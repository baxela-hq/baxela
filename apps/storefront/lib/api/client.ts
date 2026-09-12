// Fetch wrapper for the backend API. Server Components use lib/api/server
// (which injects the request locale); client code uses these helpers —
// locale falls back to <html lang>, which the [locale] layout always sets.

import { routing } from "@/i18n/routing";
import { clearAuthStorage } from "@/lib/auth-storage";

const DEFAULT_BASE_URL = "http://baxela-backend.local/api/v1";

// The browser must reach the API through the host-published backend port,
// while server-side fetches (SSR) may run inside the develop compose network
// and reach the nginx service directly via SERVER_API_BASE_URL.
export const API_BASE_URL =
  (typeof window === "undefined" ? process.env.SERVER_API_BASE_URL : undefined) ??
  process.env.NEXT_PUBLIC_API_BASE_URL ??
  DEFAULT_BASE_URL;

export class ApiError extends Error {
  constructor(
    message: string,
    public readonly status: number,
    public readonly code?: string,
    public readonly errors?: Record<string, string[]>,
  ) {
    super(message);
    this.name = "ApiError";
  }
}

export interface ApiRequestOptions extends Omit<RequestInit, "body"> {
  /** Sanctum bearer token (authenticated calls). */
  token?: string | null;
  /** Guest cart token — sent as X-Cart-Token for /cart/public/* calls. */
  cartToken?: string | null;
  locale?: string;
  body?: unknown;
}

function resolveLocale(explicit?: string): string {
  if (explicit) return explicit;
  if (typeof document !== "undefined") {
    return document.documentElement.lang || "en";
  }
  return "en";
}

// A 401 on a token-bearing request means the session died server-side
// (expired/revoked token). Clear the stored session and hard-navigate to
// the login page: a full reload discards all in-memory state and avoids
// the hydration-race soft navigations that dropped React-effect
// redirects. The latch keeps parallel 401s (e.g. the 1 + N fetches on the
// orders page) to a single clear/redirect; it resets with the page load
// the redirect itself triggers.
let handlingExpiredSession = false;

// next-intl hrefs are locale-less: "/en/profile/orders?tab=x" →
// "/profile/orders?tab=x" (the login page re-prefixes via the i18n router).
function currentPathWithoutLocale(): string {
  const { pathname, search } = window.location;
  for (const locale of routing.locales) {
    if (pathname === `/${locale}`) return `/${search}`;
    if (pathname.startsWith(`/${locale}/`)) {
      return `/${pathname.slice(locale.length + 2)}${search}`;
    }
  }
  return `${pathname}${search}`;
}

function handleExpiredSession(): void {
  handlingExpiredSession = true;
  clearAuthStorage();
  const path = currentPathWithoutLocale();
  // Deliberately not useRouter()/redirect(): this runs outside React at
  // module level (possibly mid-hydration), and the full reload is the
  // point — it drops every in-memory copy of the dead session.
  // eslint-disable-next-line @next/next/no-location-assign-relative-destination
  window.location.assign(
    `/${resolveLocale()}/login?next=${encodeURIComponent(path)}&session_expired=1`,
  );
}

export async function apiFetch<T>(
  path: string,
  options: ApiRequestOptions = {},
): Promise<T> {
  const { token, cartToken, locale, body, headers, ...rest } = options;

  const response = await fetch(`${API_BASE_URL}${path}`, {
    ...rest,
    credentials: "omit",
    headers: {
      Accept: "application/json",
      "Accept-Language": resolveLocale(locale),
      ...(body !== undefined ? { "Content-Type": "application/json" } : {}),
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
      ...(cartToken ? { "X-Cart-Token": cartToken } : {}),
      ...headers,
    },
    ...(body !== undefined ? { body: JSON.stringify(body) } : {}),
  });

  if (response.status === 204) {
    return undefined as T;
  }

  const payload = (await response.json().catch(() => null)) as
    | { data?: unknown; message?: string; code?: string; errors?: Record<string, string[]> }
    | null;

  if (!response.ok) {
    // Only authenticated calls redirect — a failed sign-in or a guest
    // cart/OTP request never carries a token and must keep rendering its
    // inline error. The ApiError still throws so call-site catch blocks
    // behave as before.
    if (
      response.status === 401 &&
      token &&
      typeof window !== "undefined" &&
      !handlingExpiredSession
    ) {
      handleExpiredSession();
    }
    throw new ApiError(
      payload?.message ?? `Request failed (${response.status})`,
      response.status,
      payload?.code,
      payload?.errors,
    );
  }

  // Laravel API resources wrap single items/collections in {data: ...};
  // paginated responses also carry links/meta and are returned whole
  // (callers type those as Paginated<T>).
  if (payload && "data" in payload && !("meta" in payload)) {
    return payload.data as T;
  }
  return payload as T;
}

export const api = {
  get: <T>(path: string, options?: ApiRequestOptions) =>
    apiFetch<T>(path, { ...options, method: "GET" }),
  post: <T>(path: string, body?: unknown, options?: ApiRequestOptions) =>
    apiFetch<T>(path, { ...options, method: "POST", body }),
  patch: <T>(path: string, body?: unknown, options?: ApiRequestOptions) =>
    apiFetch<T>(path, { ...options, method: "PATCH", body }),
  delete: <T>(path: string, options?: ApiRequestOptions) =>
    apiFetch<T>(path, { ...options, method: "DELETE" }),
};

export function buildQuery(params: Record<string, string | number | undefined | null>): string {
  const search = new URLSearchParams();
  for (const [key, value] of Object.entries(params)) {
    if (value !== undefined && value !== null && value !== "") {
      search.set(key, String(value));
    }
  }
  const query = search.toString();
  return query ? `?${query}` : "";
}
