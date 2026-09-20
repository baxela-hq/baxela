/*
 * Baxela storefront service worker — Web Push delivery only; it never
 * intercepts fetches, so page caching/updates are unaffected.
 */

self.addEventListener("install", () => {
  self.skipWaiting();
});

self.addEventListener("activate", (event) => {
  event.waitUntil(self.clients.claim());
});

self.addEventListener("push", (event) => {
  let payload = {};
  try {
    payload = event.data ? event.data.json() : {};
  } catch {
    payload = {};
  }

  const title = payload.title || "Notification";

  event.waitUntil(
    self.registration.showNotification(title, {
      body: payload.body || "",
      lang: payload.locale || "en",
      dir: payload.dir === "rtl" ? "rtl" : "ltr",
      // Tag groups repeats of the same notification code.
      tag: payload.code || "notification",
      data: {
        code: payload.code || null,
        meta: payload.meta || null,
        locale: payload.locale || "en",
      },
    }),
  );
});

self.addEventListener("notificationclick", (event) => {
  event.notification.close();

  const target = deepLinkFor(event.notification.data);

  event.waitUntil(
    (async () => {
      const clientList = await self.clients.matchAll({
        type: "window",
        includeUncontrolled: true,
      });
      for (const client of clientList) {
        if (client.focus) {
          if (client.navigate) await client.navigate(target);
          return client.focus();
        }
      }
      return self.clients.openWindow(target);
    })(),
  );
});

// Order notifications deep-link into the customer's order history,
// pre-filtered to the order the notification is about.
function deepLinkFor(data) {
  const orderCode =
    data && data.meta && typeof data.meta.order_code === "string"
      ? data.meta.order_code
      : null;

  const locale =
    data && typeof data.locale === "string" ? data.locale : "en";

  if (!orderCode) return `/${locale}/profile/notifications`;

  const search = new URLSearchParams();
  search.set("q", orderCode);
  return `/${locale}/profile/orders?${search.toString()}`;
}
