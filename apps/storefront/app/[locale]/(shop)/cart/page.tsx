"use client";

import { useState } from "react";
import { useFormatter, useTranslations } from "next-intl";
import { useAuth } from "@/context/auth-context";
import { useCart } from "@/context/cart-context";
import { ApiError } from "@/lib/api/client";
import type { ApiCartItem } from "@/lib/api/types";
import { Button } from "@/components/ui/button";
import { QuantityStepper } from "@/components/ui/quantity-stepper";
import { Link, useRouter } from "@/i18n/navigation";

/**
 * Cart for both audiences: signed-in visitors get their per-user cart,
 * everyone else the guest cart keyed by the localStorage cart token. Login
 * is only requested at checkout — this page never redirects. State lives in
 * the shared cart context so the header badge and hover preview stay in sync
 * with edits made here.
 */
export default function CartPage() {
  const t = useTranslations("cart.cart");
  const tLayout = useTranslations("shared.layout");
  const tCommon = useTranslations("shared.common");
  const format = useFormatter();
  const { status } = useAuth();
  const { items, ready, subtotal, updateItem, removeItem } = useCart();
  const router = useRouter();

  const [busyItemId, setBusyItemId] = useState<number | null>(null);
  const [error, setError] = useState<string | null>(null);

  const updateQuantity = async (item: ApiCartItem, quantity: number) => {
    if (quantity < 1) return;
    setBusyItemId(item.id);
    setError(null);
    try {
      await updateItem(item.id, quantity);
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

  const removeItemAction = async (item: ApiCartItem) => {
    setBusyItemId(item.id);
    setError(null);
    try {
      await removeItem(item.id);
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

  const usd = { style: "currency", currency: "USD" } as const;

  // Hold rendering until the shared context has settled the audience and
  // fetched the list (it gates on the restored session before choosing the
  // guest vs account cart).
  if (!ready) {
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
                  {item.product_id !== null ? (
                    <Link
                      href={`/products/${item.product_slug ?? item.product_id}`}
                      aria-label={item.product_name_snapshot}
                      className="size-16 shrink-0 overflow-hidden rounded-default border border-border bg-muted transition-colors hover:border-primary"
                    >
                      {item.image_url ? (
                        // Backend-served images come from arbitrary hosts,
                        // so this is a plain img rather than next/image.
                        // eslint-disable-next-line @next/next/no-img-element
                        <img
                          src={item.image_url}
                          alt=""
                          loading="lazy"
                          className="block size-full object-cover"
                        />
                      ) : (
                        <span aria-hidden="true" className="block size-full" />
                      )}
                    </Link>
                  ) : (
                    <span
                      aria-hidden="true"
                      className="size-16 shrink-0 rounded-default border border-border bg-muted"
                    />
                  )}
                  <div className="min-w-0 flex-1">
                    {item.product_id !== null ? (
                      <Link
                        href={`/products/${item.product_slug ?? item.product_id}`}
                        className="truncate text-sm font-medium text-foreground transition-colors hover:text-accent rtl:normal-case rtl:tracking-normal"
                      >
                        {item.product_name_snapshot}
                      </Link>
                    ) : (
                      <p className="truncate text-sm font-medium text-foreground rtl:normal-case rtl:tracking-normal">
                        {item.product_name_snapshot}
                      </p>
                    )}
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
                  <QuantityStepper
                    value={item.quantity}
                    min={1}
                    disabled={busyItemId === item.id}
                    decreaseLabel={tCommon("form.actions.back")}
                    increaseLabel={t("labels.quantity")}
                    onDecrease={() => updateQuantity(item, item.quantity - 1)}
                    onIncrease={() => updateQuantity(item, item.quantity + 1)}
                  />
                  <p className="w-24 text-end text-sm font-semibold text-foreground">
                    {format.number(
                      Number(item.price_snapshot) * item.quantity,
                      usd,
                    )}
                  </p>
                  <button
                    type="button"
                    disabled={busyItemId === item.id}
                    onClick={() => removeItemAction(item)}
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
              {status === "unauthenticated" ? (
                <p className="mt-3 text-center text-xs text-secondary-text rtl:normal-case rtl:tracking-normal">
                  {t("texts.guest_checkout_hint")}
                </p>
              ) : null}
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
