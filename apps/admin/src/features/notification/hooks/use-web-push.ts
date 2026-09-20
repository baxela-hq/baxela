import { useCallback, useEffect, useMemo, useState } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import i18n from '@/i18n'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import {
  deletePushSubscription,
  fetchPushSubscriptions,
  fetchVapidPublicKey,
  savePushSubscription,
} from '../api/webpush.api'
import { FeatureRoutes, Locales } from '../data/routes'

export type WebPushSupport =
  | 'supported'
  | 'unsupported-browser'
  | 'denied'
  | 'unconfigured'

export type WebPushStatus = WebPushSupport | 'enabling' | 'disabling'

/** VAPID keys are base64url; the subscribe() API wants raw bytes. */
function urlBase64ToUint8Array(base64Url: string): Uint8Array<ArrayBuffer> {
  const padding = '='.repeat((4 - (base64Url.length % 4)) % 4)
  const base64 = (base64Url + padding).replace(/-/g, '+').replace(/_/g, '/')
  const raw = atob(base64)
  const buffer = new ArrayBuffer(raw.length)
  const output = new Uint8Array(buffer)
  for (let index = 0; index < raw.length; index += 1) {
    output[index] = raw.charCodeAt(index)
  }
  return output
}

/**
 * Browser web push state + enable/disable flow for the signed-in staff
 * account. "Enabled" means this browser's endpoint is registered
 * server-side, so the state survives reloads and reports other devices.
 */
export function useWebPush() {
  const queryClient = useQueryClient()
  const { tMessage } = useAppTranslation(Locales.SHARED_COMMON)

  const [pending, setPending] = useState<'enabling' | 'disabling' | null>(
    null
  )
  const [permission, setPermission] = useState<NotificationPermission>(
    typeof Notification !== 'undefined' ? Notification.permission : 'default'
  )
  const [localEndpoint, setLocalEndpoint] = useState<string | null>(null)

  const browserSupported =
    typeof window !== 'undefined' &&
    'serviceWorker' in navigator &&
    'PushManager' in window &&
    typeof Notification !== 'undefined'

  const { data: keyData } = useQuery({
    queryKey: [FeatureRoutes.CACHE_KEY, 'webpush', 'vapid-key'],
    queryFn: () => fetchVapidPublicKey(),
  })

  const { data: subscriptionsData } = useQuery({
    queryKey: [FeatureRoutes.CACHE_KEY, 'webpush', 'subscriptions'],
    queryFn: () => fetchPushSubscriptions(),
  })

  // This browser's current registration, if any — the SW must be ready
  // before pushManager can be queried.
  useEffect(() => {
    if (!browserSupported) return
    let active = true
    void (async () => {
      try {
        const registration = await navigator.serviceWorker.ready
        const subscription = await registration.pushManager.getSubscription()
        if (active) setLocalEndpoint(subscription?.endpoint ?? null)
      } catch {
        // no registration yet — treated as "not enabled"
      }
    })()
    return () => {
      active = false
    }
  }, [browserSupported])

  const serverConfigured = keyData?.data.public_key != null
  const serverEndpoints = useMemo(
    () =>
      new Set(
        (subscriptionsData?.data ?? []).map(
          (subscription) => subscription.endpoint
        )
      ),
    [subscriptionsData]
  )

  const enabled =
    localEndpoint != null && serverEndpoints.has(localEndpoint) === true

  const support: WebPushSupport = !browserSupported
    ? 'unsupported-browser'
    : permission === 'denied'
      ? 'denied'
      : serverConfigured === false
        ? 'unconfigured'
        : 'supported'

  const enable = useCallback(async () => {
    if (!keyData?.data.public_key) return
    setPending('enabling')
    try {
      const result = await Notification.requestPermission()
      setPermission(result)
      if (result !== 'granted') return

      const registration = await navigator.serviceWorker.register('/sw.js')
      await navigator.serviceWorker.ready
      const subscription =
        (await registration.pushManager.getSubscription()) ??
        (await registration.pushManager.subscribe({
          userVisibleOnly: true,
          applicationServerKey: urlBase64ToUint8Array(keyData.data.public_key),
        }))

      const json = subscription.toJSON()
      await savePushSubscription({
        endpoint: subscription.endpoint,
        keys: {
          p256dh: json.keys?.p256dh ?? null,
          auth: json.keys?.auth ?? null,
        },
        user_agent: navigator.userAgent,
        locale: i18n.language,
      })
      setLocalEndpoint(subscription.endpoint)
      await queryClient.invalidateQueries({
        queryKey: [FeatureRoutes.CACHE_KEY, 'webpush'],
      })
    } catch {
      toast.error(tMessage('error.general'))
    } finally {
      setPending(null)
    }
  }, [keyData, queryClient, tMessage])

  const disable = useCallback(async () => {
    setPending('disabling')
    try {
      const registration = await navigator.serviceWorker.ready
      const subscription = await registration.pushManager.getSubscription()
      if (subscription) {
        await deletePushSubscription(subscription.endpoint)
        await subscription.unsubscribe()
        setLocalEndpoint(null)
      }
      await queryClient.invalidateQueries({
        queryKey: [FeatureRoutes.CACHE_KEY, 'webpush'],
      })
    } catch {
      toast.error(tMessage('error.general'))
    } finally {
      setPending(null)
    }
  }, [queryClient, tMessage])

  const status: WebPushStatus = pending ?? support

  return { status, enabled, enable, disable }
}
