"use client";

import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
  type ReactNode,
} from "react";
import { useAuth } from "@/context/auth-context";
import { cartApi } from "@/lib/cart/client";
import type { ApiCartItem } from "@/lib/api/types";

// Single source of truth for the cart, shared by the PDP buy box, the header
// badge + hover preview, and the cart page. Mounted inside AuthProvider — the
// audience (signed-in vs guest) is derived from the auth token, so the load
// refetches whenever the session changes (sign-in merges the guest cart on the
// server, sign-out falls back to the guest cart).

interface CartContextValue {
  /** null until the first load settles; ready flips with it. */
  items: ApiCartItem[] | null;
  ready: boolean;
  /** Total quantity across all lines — drives the header badge. */
  itemCount: number;
  /** Client-side sum of price_snapshot × quantity (the API has no totals). */
  subtotal: number;
  addItem: (variantId: number, quantity: number) => Promise<void>;
  updateItem: (itemId: number, quantity: number) => Promise<void>;
  removeItem: (itemId: number) => Promise<void>;
  /** Refetch the list; the mutations call it after every write. */
  refresh: () => Promise<void>;
}

const CartContext = createContext<CartContextValue | null>(null);

export function CartProvider({ children }: { children: ReactNode }) {
  const { status, token } = useAuth();
  const [items, setItems] = useState<ApiCartItem[] | null>(null);

  const refresh = useCallback(async () => {
    const fetched = await cartApi(token).list();
    setItems(fetched);
  }, [token]);

  useEffect(() => {
    if (status === "loading") return;
    let active = true;

    // Audience change (mount, sign-in/out): discard the previous list while
    // the refetch is in flight so stale lines from the old audience never
    // flash against the new one.
    // eslint-disable-next-line react-hooks/set-state-in-effect
    setItems(null);

    void (async () => {
      try {
        const fetched = await cartApi(token).list();
        if (active) setItems(fetched);
      } catch {
        // Initial load failure leaves the cart "not ready"; per-mutation
        // failures propagate to the caller instead.
      }
    })();

    return () => {
      active = false;
    };
  }, [status, token]);

  const addItem = useCallback(
    async (variantId: number, quantity: number) => {
      await cartApi(token).add(variantId, quantity);
      await refresh();
    },
    [token, refresh],
  );

  const updateItem = useCallback(
    async (itemId: number, quantity: number) => {
      await cartApi(token).update(itemId, quantity);
      await refresh();
    },
    [token, refresh],
  );

  const removeItem = useCallback(
    async (itemId: number) => {
      await cartApi(token).remove(itemId);
      await refresh();
    },
    [token, refresh],
  );

  const itemCount = useMemo(
    () => items?.reduce((sum, item) => sum + item.quantity, 0) ?? 0,
    [items],
  );

  const subtotal = useMemo(
    () =>
      items?.reduce(
        (sum, item) => sum + Number(item.price_snapshot) * item.quantity,
        0,
      ) ?? 0,
    [items],
  );

  const value = useMemo<CartContextValue>(
    () => ({
      items,
      ready: items !== null,
      itemCount,
      subtotal,
      addItem,
      updateItem,
      removeItem,
      refresh,
    }),
    [items, itemCount, subtotal, addItem, updateItem, removeItem, refresh],
  );

  return (
    <CartContext.Provider value={value}>{children}</CartContext.Provider>
  );
}

export function useCart(): CartContextValue {
  const context = useContext(CartContext);
  if (!context) {
    throw new Error("useCart must be used within a CartProvider");
  }
  return context;
}