"use client";

import { useFormatter, useTranslations } from "next-intl";
import { useCart } from "@/context/cart-context";
import { ShoppingCartIcon } from "@/components/ui/icons";
import { Link } from "@/i18n/navigation";

/**
 * Header cart entry: an icon link with a total-quantity badge and a pure-CSS
 * hover preview of the line items + subtotal. Auth and cart live in
 * client context, so this is a client island inside the server-rendered
 * SiteHeader (the AccountMenu pattern). The preview is read-only — quantity
 * edits happen on the PDP buy box and the cart page.
 */
export function CartMenu() {
  const t = useTranslations("shared.layout");
  const tCommon = useTranslations("shared.common");
  const format = useFormatter();
  const { items, ready, itemCount, subtotal } = useCart();

  const usd = { style: "currency", currency: "USD" } as const;

  return (
    <div className="group relative">
      <Link
        href="/cart"
        aria-label={t("header.actions.cart")}
        className="relative flex size-10 items-center justify-center rounded-default text-foreground transition-colors hover:bg-muted"
      >
        <span className="relative">
          <ShoppingCartIcon className="size-5" />
          {ready && itemCount > 0 ? (
            <span className="absolute -top-1 -end-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-accent px-1 text-[10px] font-semibold leading-none text-accent-foreground">
              {itemCount > 99 ? "99+" : itemCount.toLocaleString()}
            </span>
          ) : null}
        </span>
      </Link>

      <div className="invisible absolute end-0 top-full z-50 w-80 pt-4 opacity-0 transition-opacity duration-150 group-hover:visible group-hover:opacity-100 group-focus-within:visible group-focus-within:opacity-100">
        <div className="rounded-default border border-border-light bg-white shadow-[0_24px_48px_-24px_rgba(23,23,23,0.15)]">
          {!ready ? (
            <p className="px-5 py-8 text-center text-sm text-secondary-text rtl:normal-case rtl:tracking-normal">
              {tCommon("messages.info.loading")}
            </p>
          ) : items!.length === 0 ? (
            <p className="px-5 py-8 text-center text-sm text-secondary-text rtl:normal-case rtl:tracking-normal">
              {t("header.cart.texts.empty")}
            </p>
          ) : (
            <>
              <ul className="max-h-72 divide-y divide-border-light overflow-y-auto">
                {items!.map((item) => (
                  <li key={item.id}>
                    <Link
                      href={`/products/${item.product_slug ?? item.product_id}`}
                      className="flex items-center gap-3 px-5 py-3 transition-colors hover:bg-muted"
                    >
                      <span
                        aria-hidden="true"
                        className="size-12 shrink-0 rounded-default border border-border bg-muted"
                      />
                      <span className="min-w-0 flex-1">
                        <span className="block truncate text-sm font-medium text-foreground rtl:normal-case rtl:tracking-normal">
                          {item.product_name_snapshot}
                        </span>
                        {item.variant_label ? (
                          <span className="mt-0.5 block truncate text-xs text-secondary-text rtl:normal-case rtl:tracking-normal">
                            {item.variant_label}
                          </span>
                        ) : null}
                      </span>
                      <span className="shrink-0 text-xs text-secondary-text rtl:normal-case rtl:tracking-normal">
                        ×{item.quantity.toLocaleString()}
                      </span>
                      <span className="w-20 shrink-0 text-end text-sm font-semibold text-foreground">
                        {format.number(
                          Number(item.price_snapshot) * item.quantity,
                          usd,
                        )}
                      </span>
                    </Link>
                  </li>
                ))}
              </ul>
              <div className="border-t border-border-light px-5 py-4">
                <p className="flex items-center justify-between text-sm rtl:normal-case rtl:tracking-normal">
                  <span className="text-secondary-text">
                    {t("header.cart.labels.subtotal")}
                  </span>
                  <span className="font-semibold text-foreground">
                    {format.number(subtotal, usd)}
                  </span>
                </p>
                <Link
                  href="/cart"
                  className="mt-3 flex h-10 items-center justify-center rounded-default border border-border bg-white text-sm font-medium text-foreground transition-colors hover:bg-muted rtl:normal-case rtl:tracking-normal"
                >
                  {t("header.cart.actions.view_cart")}
                </Link>
                <Link
                  href="/checkout"
                  className="mt-2 flex h-10 items-center justify-center rounded-default bg-primary text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90 rtl:normal-case rtl:tracking-normal"
                >
                  {t("header.cart.actions.checkout")}
                </Link>
              </div>
            </>
          )}
        </div>
      </div>
    </div>
  );
}