"use client";

import Echo from "laravel-echo";

// Realtime notifications over Laravel Reverb. One Echo connection per
// authenticated session: the auth-context connects it once the Sanctum
// token is known (channel subscriptions are authorized server-side with
// that token) and tears it down on sign-out / session expiry.

let echo: Echo<"reverb"> | null = null;

const APP_KEY = process.env.NEXT_PUBLIC_REVERB_APP_KEY;
const HOST = process.env.NEXT_PUBLIC_REVERB_HOST;
const PORT = Number(process.env.NEXT_PUBLIC_REVERB_PORT ?? 443);
const FORCE_TLS =
  (process.env.NEXT_PUBLIC_REVERB_SCHEME ?? "https") === "https";
const API_BASE_URL =
  process.env.NEXT_PUBLIC_API_BASE_URL ??
  "http://baxela-backend.local/api/v1";

/**
 * Open (or reuse) the Echo connection. Returns null when Reverb is not
 * configured, so callers can silently degrade to fetch-only updates.
 */
export function connectNotificationEcho(token: string): Echo<"reverb"> | null {
  if (!APP_KEY || !HOST) return null;
  if (echo) return echo;

  echo = new Echo({
    broadcaster: "reverb",
    key: APP_KEY,
    wsHost: HOST,
    wsPort: FORCE_TLS ? 443 : PORT,
    wssPort: PORT,
    forceTLS: FORCE_TLS,
    enabledTransports: ["ws", "wss"],
    disableStats: true,
    authEndpoint: `${API_BASE_URL}/notification/broadcasting/auth`,
    auth: {
      headers: {
        Accept: "application/json",
        Authorization: `Bearer ${token}`,
      },
    },
  });

  return echo;
}

export function disconnectNotificationEcho(): void {
  echo?.disconnect();
  echo = null;
}

export function getNotificationEcho(): Echo<"reverb"> | null {
  return echo;
}
