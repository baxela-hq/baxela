"use client";

import { useCallback, useEffect, useState } from "react";
import { useTranslations } from "next-intl";
import { toast } from "sonner";
import { useAuth } from "@/context/auth-context";
import { ApiError, api } from "@/lib/api/client";
import type { ApiWishlistItem } from "@/lib/api/types";
import ProductCard from "@/components/product-card";
import { CloseIcon, HeartIcon } from "@/components/ui/icons";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
import { Link } from "@/i18n/navigation";

/**
 * The signed-in user's wishlist: product cards (newest saved first) with a
 * remove affordance per card. Removal asks for confirmation first. Rows
 * whose product is no longer available render a muted placeholder so they
 * can still be removed — the backend never drops wishlist rows on its own.
 */
export function WishlistGrid() {
  const t = useTranslations("account.account");
  const tCommon = useTranslations("shared.common");
  const { status, token } = useAuth();

  const [items, setItems] = useState<ApiWishlistItem[] | null>(null);
  const [busyProductId, setBusyProductId] = useState<number | null>(null);
  const [confirming, setConfirming] = useState<ApiWishlistItem | null>(null);

  const load = useCallback(async () => {
    if (!token) return;
    try {
      const fetched = await api.get<ApiWishlistItem[]>(
        "/user/user/wishlist-items",
        { token },
      );
      setItems(fetched);
    } catch {
      setItems([]);
    }
  }, [token]);

  useEffect(() => {
    if (status === "authenticated") {
      // Fetch-on-auth is a legitimate external-system sync; the rule flags
      // setState statically even though it only runs in the async
      // continuation after the fetch resolves.
      // eslint-disable-next-line react-hooks/set-state-in-effect
      void load();
    }
  }, [status, load]);

  const removeItem = async (item: ApiWishlistItem) => {
    setBusyProductId(item.product_id);
    try {
      await api.delete(`/user/user/wishlist-items/${item.product_id}`, {
        token,
      });
      setItems((current) =>
        current === null
          ? current
          : current.filter(
              (candidate) => candidate.product_id !== item.product_id,
            ),
      );
    } catch (cause) {
      toast.error(
        cause instanceof ApiError
          ? cause.message
          : tCommon("messages.error.general"),
      );
    } finally {
      setBusyProductId(null);
    }
  };

  if (items === null) {
    return (
      <p className="text-base text-secondary-text rtl:normal-case rtl:tracking-normal">
        {tCommon("messages.info.loading")}
      </p>
    );
  }

  return (
    <>
      {confirming !== null ? (
        <ConfirmDialog
          title={t("texts.wishlist_remove_confirm_title")}
          message={t("texts.wishlist_remove_confirm_message", {
            title: confirming.product?.title ?? t("texts.wishlist_unavailable"),
          })}
          confirmLabel={t("texts.wishlist_remove")}
          cancelLabel={t("actions.wishlist_remove_cancel")}
          pending={busyProductId === confirming.product_id}
          onConfirm={async () => {
            await removeItem(confirming);
            setConfirming(null);
          }}
          onCancel={() => setConfirming(null)}
        />
      ) : null}
      {items.length === 0 ? (
        <div className="rounded-default border border-border-light bg-white px-6 py-24 text-center">
          <span className="grid size-14 place-items-center rounded-full bg-muted text-secondary-text mx-auto">
            <HeartIcon className="size-6" />
          </span>
          <p className="mt-6 text-base font-medium text-foreground rtl:normal-case rtl:tracking-normal">
            {t("texts.wishlist_empty")}
          </p>
          <p className="mt-2 text-sm text-secondary-text rtl:normal-case rtl:tracking-normal">
            {t("texts.wishlist_empty_hint")}
          </p>
          <Link
            href="/products"
            className="mt-6 inline-flex h-12 items-center justify-center rounded-default bg-primary px-8 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90 rtl:normal-case rtl:tracking-normal"
          >
            {t("actions.wishlist_continue_shopping")}
          </Link>
        </div>
      ) : (
        <div className="grid grid-cols-1 gap-x-6 gap-y-10 sm:grid-cols-2 xl:grid-cols-3">
          {items.map((item) => (
            <div key={item.id} className="relative">
              {item.product !== null ? (
                <ProductCard product={item.product} />
              ) : (
                <div className="flex aspect-square w-full items-center justify-center rounded-default bg-muted px-4">
                  <p className="text-center text-sm text-secondary-text rtl:normal-case rtl:tracking-normal">
                    {t("texts.wishlist_unavailable")}
                  </p>
                </div>
              )}
              <button
                type="button"
                aria-label={t("texts.wishlist_remove")}
                disabled={busyProductId === item.product_id}
                onClick={() => setConfirming(item)}
                className="absolute end-3 top-3 grid size-9 place-items-center rounded-full bg-white text-secondary-text shadow-sm transition-colors hover:text-red-600 disabled:opacity-40 rtl:normal-case rtl:tracking-normal"
              >
                <CloseIcon className="size-4" />
              </button>
            </div>
          ))}
        </div>
      )}
    </>
  );
}
