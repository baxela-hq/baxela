import Echo from 'laravel-echo'

// Realtime notifications over Laravel Reverb. One Echo connection per
// session: connectEcho() is called with the current bearer token (the
// auth endpoint validates channel subscriptions server-side), and
// disconnectEcho() tears the socket down on sign-out / token reset.

let echo: Echo<'reverb'> | null = null

const APP_KEY = import.meta.env.VITE_REVERB_APP_KEY as string | undefined
const HOST = import.meta.env.VITE_REVERB_HOST as string | undefined
const PORT = Number(import.meta.env.VITE_REVERB_PORT ?? 443)
const FORCE_TLS = (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https'

/**
 * Open (or reuse) the Echo connection. Returns null when Reverb is not
 * configured, so callers can silently degrade to HTTP-only updates.
 */
export function connectEcho(token: string): Echo<'reverb'> | null {
  if (!APP_KEY || !HOST) return null
  if (echo) return echo

  echo = new Echo({
    broadcaster: 'reverb',
    key: APP_KEY,
    wsHost: HOST,
    wsPort: FORCE_TLS ? 443 : PORT,
    wssPort: PORT,
    forceTLS: FORCE_TLS,
    enabledTransports: ['ws', 'wss'],
    disableStats: true,
    authEndpoint: `${import.meta.env.VITE_API_BASE_URL}/notification/broadcasting/auth`,
    auth: {
      headers: {
        Accept: 'application/json',
        Authorization: `Bearer ${token}`,
      },
    },
  })

  return echo
}

export function disconnectEcho(): void {
  echo?.disconnect()
  echo = null
}

export function getEcho(): Echo<'reverb'> | null {
  return echo
}
