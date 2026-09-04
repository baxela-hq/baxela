"use client";

import { useCallback, useEffect, useState } from "react";
import { useFormatter, useTranslations } from "next-intl";
import { useAuth } from "@/context/auth-context";
import { api, ApiError } from "@/lib/api/client";
import type { ApiCartItem } from "@/lib/api/types";
import { Button } from "@/components/ui/button";
import { Link, useRouter } from "@/i18n/navigation";

/**
 * The backend cart is per-authenticated-user (no guest carts), so this page
 * requires a session; visitors without one are sent to login and back.
 */
export default function CartPage() {
  const t = useTranslations("cart.cart");
  const tLayout = useTranslations("shared.layout");
  const tCommon = useTranslations("shared.common");
  const format = useFormatter();
  const { status, token } = useAuth();
  const router = useRouter();

  const [items, setItems] = useState<ApiCartItem[] | null>(null);
  const [busyItemId, setBusyItemId] = useState<number | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (status === "unauthenticated") {
      router.replace("/login?next=/cart");
    }
  }, [status, router]);

  const load = useCallback(async () => {
    if (!token) return;
    try {
      const fetched = await api.get<ApiCartItem[]>("/cart/user/cart-items", {
        token,
      });
      setItems(fetched);
      setError(null);
    } catch {
      setError(tCommon("messages.error.general"));
    }
  }, [token, tCommon]);

  useEffect(() => {
    if (status === "authenticated") {
      // Fetch-on-auth is a legitimate external-system sync; the rule flags
      // setState statically even though it only runs in the async
      // continuation after the fetch resolves.
      // eslint-disable-next-line react-hooks/set-state-in-effect
      void load();
    }
  }, [status, load]);

  const updateQuantity = async (item: ApiCartItem, quantity: number) => {
    if (quantity < 1) return;
    setBusyItemId(item.id);
    setError(null);
    try {
      await api.patch(
        `/cart/user/cart-items/${item.id}`,
        { quantity },
        { token },
      );
      await load();
    } catch (cause) {
      setError(
        cause instanceof ApiError
          ? cause.message
          : t("messages.error.update_failed"),
      );
    } finally {
      setBusyItemId(null);
    }
  };

  const removeItem = async (item: ApiCartItem) => {
    setBusyItemId(item.id);
    setError(null);
    try {
      await api.delete(`/cart/user/cart-items/${item.id}`, { token });
      await load();
    } catch (cause) {
      setError(
        cause instanceof ApiError
          ? cause.message
          : t("messages.error.remove_failed"),
      );
    } finally {
      setBusyItemId(null);
    }
  };

  const subtotal =
    items?.reduce(
      (sum, item) => sum + Number(item.price_snapshot) * item.quantity,
      0,
    ) ?? 0;

  const usd = { style: "currency", currency: "USD" } as const;

  if (status !== "authenticated") {
    return (
      <section className="mx-auto max-w-7xl px-6 py-24 text-center">
        <p className="text-base text-secondary-text rtl:normal-case rtl:tracking-normal">
          {tCommon("messages.info.loading")}
        </p>
      </section>
    );
  }

  return (
    <>
      <nav
        aria-label={tLayout("breadcrumb.labels.navigation")}
        className="border-b border-border-light bg-muted"
      >
        <ol className="mx-auto flex max-w-7xl items-center gap-2 px-6 py-4 text-sm text-secondary-text">
          <li>
            <Link
              href="/"
              className="transition-colors hover:text-accent rtl:normal-case rtl:tracking-normal"
            >
              {tLayout("breadcrumb.links.home")}
            </Link>
          </li>
          <li aria-hidden="true">/</li>
          <li className="font-medium text-foreground rtl:normal-case rtl:tracking-normal">
            {t("texts.title")}
          </li>
        </ol>
      </nav>

      <section className="mx-auto max-w-7xl px-6 py-12">
        <h1 className="text-3xl font-bold text-foreground rtl:normal-case rtl:tracking-normal">
          {t("texts.title")}
        </h1>

        {error ? (
          <p
            role="alert"
            className="mt-4 text-sm text-red-600 rtl:normal-case rtl:tracking-normal"
          >
            {error}
          </p>
        ) : null}

        {items !== null && items.length === 0 ? (
          <div className="mt-8 text-center">
            <p className="text-base text-foreground rtl:normal-case rtl:tracking-normal">
              {t("texts.empty")}
            </p>
            <p className="mt-2 text-sm text-secondary-text rtl:normal-case rtl:tracking-normal">
              {t("texts.empty_hint")}
            </p>
            <Link
              href="/products"
              className="mt-6 inline-flex h-12 items-center justify-center rounded-default bg-primary px-8 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90 rtl:normal-case rtl:tracking-normal"
            >
              {t("actions.continue_shopping")}
            </Link>
          </div>
        ) : null}

        {items !== null && items.length > 0 ? (
          <div className="mt-8 grid grid-cols-1 gap-12 lg:grid-cols-[1fr_380px]">
            <ul className="divide-y divide-border-light rounded-default border border-border-light">
              {items.map((item) => (
                <li
                  key={item.id}
                  className="flex flex-wrap items-center gap-4 p-6"
                >
                  <span
                    className="size-16 shrink-0 rounded-default border border-border bg-muted"
                    aria-hidden="true"
                  />
                  <div className="min-w-0 flex-1">
                    <p className="truncate text-sm font-medium text-foreground rtl:normal-case rtl:tracking-normal">
                      {item.product_name_snapshot}
                    </p>
                    {item.variant_label ? (
                      <p className="mt-1 text-sm text-secondary-text rtl:normal-case rtl:tracking-normal">
                        {item.variant_label}
                      </p>
                    ) : null}
                    <p className="mt-1 text-sm text-secondary-text rtl:normal-case rtl:tracking-normal">
                      {t("labels.unit_price")}:{" "}
                      {format.number(Number(item.price_snapshot), usd)}
                    </p>
                  </div>
                  <div className="flex items-center rounded-default border border-border">
                    <button
                      type="button"
                      disabled={busyItemId === item.id || item.quantity <= 1}
                      aria-label={tCommon("form.actions.back")}
                      onClick={() => updateQuantity(item, item.quantity - 1)}
                      className="px-4 py-3 text-foreground transition-colors hover:bg-muted disabled:opacity-40"
                    >
                      −
                    </button>
                    <span className="px-4 text-sm text-foreground">
                      {item.quantity.toLocaleString()}
                    </span>
                    <button
                      type="button"
                      disabled={busyItemId === item.id}
                      aria-label={t("labels.quantity")}
                      onClick={() => updateQuantity(item, item.quantity + 1)}
                      className="px-4 py-3 text-foreground transition-colors hover:bg-muted disabled:opacity-40"
                    >
                      +
                    </button>
                  </div>
                  <p className="w-24 text-end text-sm font-semibold text-foreground">
                    {format.number(
                      Number(item.price_snapshot) * item.quantity,
                      usd,
                    )}
                  </p>
                  <button
                    type="button"
                    disabled={busyItemId === item.id}
                    onClick={() => removeItem(item)}
                    className="text-sm text-secondary-text underline underline-offset-2 transition-colors hover:text-red-600 rtl:normal-case rtl:tracking-normal"
                  >
                    {t("actions.remove")}
                  </button>
                </li>
              ))}
            </ul>

            <aside className="h-fit rounded-default border border-border-light p-6 lg:sticky lg:top-28">
              <p className="flex items-center justify-between text-sm text-secondary-text rtl:normal-case rtl:tracking-normal">
                <span>{t("labels.item_total")}</span>
                <span className="text-base font-semibold text-foreground">
                  {format.number(subtotal, usd)}
                </span>
              </p>
              <Button
                variant="accent"
                fullWidth
                className="mt-6"
                onClick={() => router.push("/checkout")}
              >
                {t("actions.checkout")}
              </Button>
              <Link
                href="/products"
                className="mt-4 block text-center text-sm text-secondary-text underline underline-offset-2 hover:text-foreground rtl:normal-case rtl:tracking-normal"
              >
                {t("actions.continue_shopping")}
              </Link>
            </aside>
          </div>
        ) : null}
      </section>
    </>
  );
}
