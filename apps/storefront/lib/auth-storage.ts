// Auth session storage. The Sanctum token and the cached user live in
// localStorage (there are no auth cookies; the API client sends
// credentials: "omit"). Shared by the auth context and the API client's
// 401 interceptor so both clear the exact same keys.

import { uuidv4 } from "./utils";

export const AUTH_TOKEN_KEY = "baxela_token";
export const AUTH_USER_KEY = "baxela_user";
export const AUTH_DEVICE_ID_KEY = "baxela_device_id";

export function clearAuthStorage(): void {
  if (typeof window === "undefined") return;
  window.localStorage.removeItem(AUTH_TOKEN_KEY);
  window.localStorage.removeItem(AUTH_USER_KEY);
}

// Stable per-browser device identifier sent as device_name on sign-in: the
// backend names the Sanctum token after it and replaces only this device's
// previous token, keeping other devices signed in. Survives token rotation;
// deliberately NOT cleared on sign-out.
export function getDeviceId(): string {
  if (typeof window === "undefined") return "server";

  let deviceId = window.localStorage.getItem(AUTH_DEVICE_ID_KEY);
  if (!deviceId) {
    deviceId = uuidv4();
    window.localStorage.setItem(AUTH_DEVICE_ID_KEY, deviceId);
  }
  return deviceId;
}
