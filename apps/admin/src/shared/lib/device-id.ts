// Stable per-browser device identifier sent as device_name on sign-in: the
// backend names the Sanctum token after it and replaces only this device's
// previous token, keeping other devices signed in. Survives token rotation;
// deliberately NOT cleared on sign-out.
const DEVICE_ID_KEY = 'admin_device_id'

export function getDeviceId(): string {
  let deviceId = window.localStorage.getItem(DEVICE_ID_KEY)

  if (!deviceId) {
    deviceId = randomUuid()
    window.localStorage.setItem(DEVICE_ID_KEY, deviceId)
  }

  return deviceId
}

/**
 * crypto.randomUUID() only exists in secure contexts (HTTPS / localhost);
 * the admin is routinely served over plain http on a LAN IP, where calling
 * it throws and sign-in dies before any request is sent — so fall back to
 * a getRandomValues-based v4 UUID there.
 */
function randomUuid(): string {
  if (typeof crypto.randomUUID === 'function') {
    return crypto.randomUUID()
  }

  const bytes = new Uint8Array(16)
  crypto.getRandomValues(bytes)
  bytes[6] = (bytes[6] & 0x0f) | 0x40 // version 4
  bytes[8] = (bytes[8] & 0x3f) | 0x80 // variant 10

  const hex = Array.from(bytes, (byte) =>
    byte.toString(16).padStart(2, '0')
  ).join('')

  return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`
}
