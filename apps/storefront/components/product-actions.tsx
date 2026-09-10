"use client";

import { useEffect, useState } from "react";
import { useTranslations } from "next-intl";
import { toast } from "sonner";
import { useAuth } from "@/context/auth-context";
import { useCart } from "@/context/cart-context";
import { ApiError, api, buildQuery } from "@/lib/api/client";
import type { ApiVariant, ApiWishlistItem } from "@/lib/api/types";
import { Button } from "@/components/ui/button";
import { QuantityStepper } from "@/components/ui/quantity-stepper";
import { HeartIcon, HeartSolidIcon } from "@/components/ui/icons";
import { Link, useRouter } from "@/i18n/navigation";

function variantLabel(variant: ApiVariant): string {
  const titles = variant.option_values
    .map((optionValue) => optionValue.title)
    .filter((title): title is string => title !== null);
  return titles.length > 0 ? titles.join(" / ") : variant.sku;
}

/**
 * Variant selector + quantity stepper + add-to-cart for the product page.
 * Cart items are variant-based; adding works for guests too — cartApi picks
 * the guest cart (X-Cart-Token) until a session exists. Once a variant is in
 * the cart the buy box switches to an "In cart" stepper that edits the server
 * cart directly and links through to the cart page. The wishlist heart is
 * account-only: guests are sent to login and back here.
 */
export function ProductActions({
  productHref,
  productId,
  variants,
}: {
  productHref: string;
  productId: number;
  variants: ApiVariant[];
}) {
  const t = useTranslations("catalog.product");
  const { status, token } = useAuth();
  const { items, ready, addItem, updateItem } = useCart();
  const router = useRouter();

  const defaultVariant =
    variants.find((variant) => variant.is_default) ?? variants[0] ?? null;
  const [variantId, setVariantId] = useState<number | null>(
    defaultVariant?.id ?? null,
  );
  const [quantity, setQuantity] = useState(1);
  const [pending, setPending] = useState(false);
  const [added, setAdded] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [wishlisted, setWishlisted] = useState(false);
  const [wishlistPending, setWishlistPending] = useState(false);

  useEffect(() => {
    if (status !== "authenticated" || !token) return;
    let active = true;
    void (async () => {
      try {
        const rows = await api.get<ApiWishlistItem[]>(
          `/wishlist/user/wishlist-items${buildQuery({ "filter[product_id]": productId })}`,
          { token },
        );
        if (active) {
          setWishlisted(rows.length > 0);
        }
      } catch {
        // The heart simply starts unfilled when the check fails.
      }
    })();
    return () => {
      active = false;
    };
  }, [status, token, productId]);

  const cartItem =
    ready && variantId !== null
      ? (items?.find((item) => item.variant_id === variantId) ?? null)
      : null;
  const inCart = cartItem !== null;

  const onToggleWishlist = async () => {
    if (status !== "authenticated" || !token) {
      router.replace(`/login?next=${productHref}`);
      return;
    }

    setWishlistPending(true);
    try {
      if (wishlisted) {
        await api.delete(`/wishlist/user/wishlist-items/${productId}`, {
          token,
        });
        setWishlisted(false);
        toast.success(t("messages.success.wishlist_removed"));
      } else {
        await api.post<ApiWishlistItem>(
          "/wishlist/user/wishlist-items",
          { product_id: productId },
          { token },
        );
        setWishlisted(true);
        toast.success(t("messages.success.wishlist_added"));
      }
    } catch (cause) {
      toast.error(
        cause instanceof ApiError
          ? cause.message
          : t("messages.error.wishlist_failed"),
      );
    } finally {
      setWishlistPending(false);
    }
  };

  const onAddToCart = async () => {
    setError(null);

    if (variantId === null) return;

    setPending(true);
    setAdded(false);
    try {
      await addItem(variantId, quantity);
      setAdded(true);
      toast.success(t("messages.success.added_to_cart"), {
        action: {
          label: t("actions.view_cart"),
          onClick: () => router.push("/cart"),
        },
      });
    } catch (cause) {
      setError(
        cause instanceof ApiError
          ? cause.message
          : t("messages.error.add_failed"),
      );
    } finally {
      setPending(false);
    }
  };

  const onUpdateQuantity = async (delta: number) => {
    if (!cartItem) return;
    const next = cartItem.quantity + delta;
    if (next < 1) return;

    setPending(true);
    setError(null);
    try {
      await updateItem(cartItem.id, next);
    } catch (cause) {
      setError(
        cause instanceof ApiError
          ? cause.message
          : t("messages.error.update_failed"),
      );
    } finally {
      setPending(false);
    }
  };

  const wishlistButton = (
    <button
      type="button"
      aria-label={
        wishlisted
          ? t("actions.remove_from_wishlist")
          : t("actions.add_to_wishlist")
      }
      aria-pressed={wishlisted}
      disabled={wishlistPending}
      onClick={onToggleWishlist}
      className={
        wishlisted
          ? "rounded-default border border-accent bg-accent/10 p-3.5 text-accent transition-colors hover:bg-accent/20 disabled:opacity-40"
          : "rounded-default border border-border p-3.5 text-foreground transition-colors hover:bg-muted disabled:opacity-40"
      }
    >
      {wishlisted ? (
        <HeartSolidIcon className="size-5" />
      ) : (
        <HeartIcon className="size-5" />
      )}
    </button>
  );

  return (
    <>
      {variants.length > 0 ? (
        <div className="mt-6">
          <h2 className="text-sm font-semibold uppercase tracking-wide text-foreground rtl:normal-case rtl:tracking-normal">
            {t("labels.size")}
          </h2>
          <div className="mt-3 flex flex-wrap gap-2">
            {variants.map((variant) => (
              <button
                key={variant.id}
                type="button"
                aria-pressed={variant.id === variantId}
                onClick={() => setVariantId(variant.id)}
                className={
                  variant.id === variantId
                    ? "rounded-default border border-primary bg-primary px-4 py-2 text-sm text-primary-foreground rtl:normal-case rtl:tracking-normal"
                    : "rounded-default border border-border px-4 py-2 text-sm text-foreground transition-colors hover:bg-muted rtl:normal-case rtl:tracking-normal"
                }
              >
                {variantLabel(variant)}
              </button>
            ))}
          </div>
        </div>
      ) : null}

      <div className="mt-8 flex items-center gap-4">
        {inCart ? (
          <>
            <div>
              <p className="mb-2 text-sm font-medium text-foreground rtl:normal-case rtl:tracking-normal">
                {t("labels.in_cart")}
              </p>
              <QuantityStepper
                value={cartItem.quantity}
                min={1}
                disabled={pending}
                decreaseLabel={t("labels.quantity_decrease")}
                increaseLabel={t("labels.quantity_increase")}
                onDecrease={() => void onUpdateQuantity(-1)}
                onIncrease={() => void onUpdateQuantity(1)}
              />
            </div>
            <Link
              href="/cart"
              className="flex h-14 flex-1 items-center justify-center rounded-default border border-border bg-white px-6 text-base font-medium text-foreground transition-colors hover:bg-muted rtl:normal-case rtl:tracking-normal"
            >
              {t("actions.view_cart")}
            </Link>
            {wishlistButton}
          </>
        ) : (
          <>
            <QuantityStepper
              value={quantity}
              min={1}
              max={99}
              disabled={pending}
              decreaseLabel={t("labels.quantity_decrease")}
              increaseLabel={t("labels.quantity_increase")}
              onDecrease={() => setQuantity((value) => Math.max(1, value - 1))}
              onIncrease={() => setQuantity((value) => Math.min(99, value + 1))}
            />
            <Button
              type="button"
              className="flex-1"
              disabled={pending || variantId === null}
              onClick={onAddToCart}
            >
              {added
                ? t("actions.added_to_cart")
                : pending
                  ? t("messages.info.adding")
                  : t("actions.add_to_cart")}
            </Button>
            {wishlistButton}
          </>
        )}
      </div>

      {error ? (
        <p
          role="alert"
          className="mt-3 text-sm text-red-600 rtl:normal-case rtl:tracking-normal"
        >
          {error}
        </p>
      ) : null}
    </>
  );
}