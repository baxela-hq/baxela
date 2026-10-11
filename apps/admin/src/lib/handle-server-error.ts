import { AxiosError } from 'axios'
import { toast } from 'sonner'
import { Sentry } from '@/lib/sentry'

// Everything attached to a Sentry event is treated as untrusted diagnostic
// data: query strings are stripped (they carry search terms and ids), the
// AxiosError itself is never serialized (its config holds the bearer
// token), and server-provided text is capped.
const MAX_REPORTED_TEXT_LENGTH = 200

function sanitizeText(value: unknown): string | undefined {
  if (typeof value !== 'string' || value.length === 0) return undefined
  return value.slice(0, MAX_REPORTED_TEXT_LENGTH)
}

function sanitizePath(url: string | undefined): string {
  const path = url?.split('?')[0]?.split('#')[0] ?? ''
  return path.slice(0, MAX_REPORTED_TEXT_LENGTH) || 'unknown'
}

/**
 * Report a failed request to Sentry. Expected 4xx outcomes (auth,
 * validation, missing content) are normal client behavior, not bugs — only
 * server errors and network failures are captured. Queries report via
 * queryCache.onError and mutations via handleServerError; the paths are
 * disjoint, so one failed request yields at most one event.
 */
export function reportServerError(error: unknown) {
  if (error instanceof AxiosError) {
    const status = error.response?.status

    // undefined status = the request never got a response (network error)
    if (status === undefined || status >= 500) {
      const method = error.config?.method?.toUpperCase() ?? 'REQUEST'
      Sentry.captureException(
        new Error(`API ${method} ${sanitizePath(error.config?.url)} failed`),
        {
          extra: {
            status: status ?? 'network-error',
            code: sanitizeText(error.response?.data?.code),
            server_title: sanitizeText(error.response?.data?.title),
          },
        }
      )
    }
    return
  }

  Sentry.captureException(error)
}

export function handleServerError(error: unknown) {
  reportServerError(error)

  let errMsg = 'Something went wrong!'

  if (
    error &&
    typeof error === 'object' &&
    'status' in error &&
    Number(error.status) === 204
  ) {
    errMsg = 'Content not found.'
  }

  if (error instanceof AxiosError) {
    errMsg = error.response?.data.title
  }

  toast.error(errMsg)
}
