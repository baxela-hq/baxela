// Auth session storage. The Sanctum token and the cached user live in
// localStorage (there are no auth cookies; the API client sends
// credentials: "omit"). Shared by the auth context and the API client's
// 401 interceptor so both clear the exact same keys.

export const AUTH_TOKEN_KEY = "baxela_token";
export const AUTH_USER_KEY = "baxela_user";

export function clearAuthStorage(): void {
  if (typeof window === "undefined") return;
  window.localStorage.removeItem(AUTH_TOKEN_KEY);
  window.localStorage.removeItem(AUTH_USER_KEY);
}
