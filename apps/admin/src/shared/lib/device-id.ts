// Stable per-browser device identifier sent as device_name on sign-in: the
// backend names the Sanctum token after it and replaces only this device's
// previous token, keeping other devices signed in. Survives token rotation;
// deliberately NOT cleared on sign-out.
const DEVICE_ID_KEY = 'admin_device_id'

export function getDeviceId(): string {
  let deviceId = window.localStorage.getItem(DEVICE_ID_KEY)

  if (!deviceId) {
    deviceId = crypto.randomUUID()
    window.localStorage.setItem(DEVICE_ID_KEY, deviceId)
  }

  return deviceId
}
