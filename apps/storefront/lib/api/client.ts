// Fetch wrapper for the backend API. Server Components use lib/api/server
// (which injects the request locale); client code uses these helpers —
// locale falls back to <html lang>, which the [locale] layout always sets.

const DEFAULT_BASE_URL = "http://baxela-backend.local/api/v1";

export const API_BASE_URL =
  process.env.NEXT_PUBLIC_API_BASE_URL ?? DEFAULT_BASE_URL;

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
  token?: string | null;
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

export async function apiFetch<T>(
  path: string,
  options: ApiRequestOptions = {},
): Promise<T> {
  const { token, locale, body, headers, ...rest } = options;

  const response = await fetch(`${API_BASE_URL}${path}`, {
    ...rest,
    credentials: "omit",
    headers: {
      Accept: "application/json",
      "Accept-Language": resolveLocale(locale),
      ...(body !== undefined ? { "Content-Type": "application/json" } : {}),
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
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
