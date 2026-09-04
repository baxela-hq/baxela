"use client";

import { useState } from "react";
import { useTranslations } from "next-intl";
import { toast } from "sonner";
import { useAuth } from "@/context/auth-context";
import { api, ApiError } from "@/lib/api/client";
import type { ApiVariant } from "@/lib/api/types";
import { Button } from "@/components/ui/button";
import { HeartIcon } from "@/components/ui/icons";
import { useRouter } from "@/i18n/navigation";

function variantLabel(variant: ApiVariant): string {
  const titles = variant.option_values
    .map((optionValue) => optionValue.title)
    .filter((title): title is string => title !== null);
  return titles.length > 0 ? titles.join(" / ") : variant.sku;
}

/**
 * Variant selector + quantity stepper + add-to-cart for the product page.
 * Cart items are variant-based; unauthenticated users are sent to login
 * with a `next` param so they land back here.
 */
export function ProductActions({
  productHref,
  variants,
}: {
  productHref: string;
  variants: ApiVariant[];
}) {
  const t = useTranslations("catalog.product");
  const { status, token } = useAuth();
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

  const onAddToCart = async () => {
    setError(null);

    if (status !== "authenticated" || !token) {
      router.replace(`/login?next=${productHref}`);
      return;
    }
    if (variantId === null) return;

    setPending(true);
    setAdded(false);
    try {
      await api.post(
        "/cart/user/cart-items",
        { variant_id: variantId, quantity },
        { token },
      );
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
        <div className="flex items-center rounded-default border border-border">
          <button
            type="button"
            aria-label={t("labels.quantity_decrease")}
            onClick={() => setQuantity((value) => Math.max(1, value - 1))}
            className="px-4 py-3 text-foreground transition-colors hover:bg-muted"
          >
            −
          </button>
          <span className="px-4 text-sm text-foreground" aria-live="polite">
            {quantity.toLocaleString()}
          </span>
          <button
            type="button"
            aria-label={t("labels.quantity_increase")}
            onClick={() => setQuantity((value) => Math.min(99, value + 1))}
            className="px-4 py-3 text-foreground transition-colors hover:bg-muted"
          >
            +
          </button>
        </div>
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
        <button
          type="button"
          aria-label={t("actions.add_to_wishlist")}
          className="rounded-default border border-border p-3.5 text-foreground transition-colors hover:bg-muted"
        >
          <HeartIcon className="size-5" />
        </button>
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
