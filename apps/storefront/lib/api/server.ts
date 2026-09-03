import { api, type ApiRequestOptions } from "./client";

// Server-side API access: injects the active request locale so catalog
// responses localize (the backend resolves Accept-Language). next-intl/server
// is imported dynamically to keep this module out of client bundles.

export async function serverApiGet<T>(
  path: string,
  options: ApiRequestOptions = {},
): Promise<T> {
  const { getLocale } = await import("next-intl/server");
  const locale = await getLocale();
  return api.get<T>(path, { ...options, locale });
}
