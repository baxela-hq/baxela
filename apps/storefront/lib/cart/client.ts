// Guest cart plumbing. The cart token is a CSPRNG UUID kept in
// localStorage: it is the bearer credential for the /cart/public/*
// endpoints. On sign-in the backend folds the guest cart into the account
// cart; the token is cleared only after that succeeds (a failed sign-in
// must leave the guest cart reachable).

import { api } from "@/lib/api/client";
import type { ApiCartItem } from "@/lib/api/types";

const CART_TOKEN_KEY = "baxela_cart_token";

function randomUuid(): string {
  const c = globalThis.crypto;
  if (typeof c?.randomUUID === "function") {
    return c.randomUUID();
  }
  // CSPRNG fallback for non-secure contexts without crypto.randomUUID.
  const bytes = c.getRandomValues(new Uint8Array(16));
  bytes[6] = (bytes[6] & 0x0f) | 0x40; // version 4
  bytes[8] = (bytes[8] & 0x3f) | 0x80; // RFC 4122 variant
  const hex = [...bytes]
    .map((byte) => byte.toString(16).padStart(2, "0"))
    .join("");
  return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
}

export function getCartToken(): string | null {
  if (typeof window === "undefined") return null;
  return window.localStorage.getItem(CART_TOKEN_KEY);
}

export function ensureCartToken(): string {
  if (typeof window === "undefined") {
    throw new Error("ensureCartToken must only run in the browser");
  }
  const existing = window.localStorage.getItem(CART_TOKEN_KEY);
  if (existing) return existing;

  const token = randomUuid();
  window.localStorage.setItem(CART_TOKEN_KEY, token);
  return token;
}

export function clearCartToken(): void {
  if (typeof window === "undefined") return;
  window.localStorage.removeItem(CART_TOKEN_KEY);
}

/**
 * Cart calls for either audience: an authenticated session targets the
 * per-user cart with the bearer token; everyone else targets the guest cart
 * keyed by the localStorage cart token (created on first use). Callers pass
 * the auth token from useAuth() only once status has settled past "loading",
 * so the right audience is picked after session restoration.
 */
export function cartApi(token: string | null) {
  const base = token ? "/cart/user/cart-items" : "/cart/public/cart-items";
  const options = token ? { token } : { cartToken: ensureCartToken() };

  return {
    list: () => api.get<ApiCartItem[]>(base, options),
    add: (variantId: number, quantity: number) =>
      api.post<ApiCartItem>(
        base,
        { variant_id: variantId, quantity },
        options,
      ),
    update: (itemId: number, quantity: number) =>
      api.patch<ApiCartItem>(`${base}/${itemId}`, { quantity }, options),
    remove: (itemId: number) => api.delete<void>(`${base}/${itemId}`, options),
  };
}
