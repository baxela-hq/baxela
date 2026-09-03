import type { Metadata } from "next";
import { getFormatter, getTranslations } from "next-intl/server";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Link } from "@/i18n/navigation";

const CART_ITEMS = [
  { id: 1, name: "Product 1", price: 74.99, quantity: 1 },
  { id: 2, name: "Product 2", price: 64.99, quantity: 2 },
  { id: 3, name: "Product 3", price: 49.99, quantity: 1 },
];

const SHIPPING_METHODS = [
  { id: "free", label: "Free", detail: "5–7 business days", price: "Free" },
  { id: "standard", label: "Standard", detail: "3–5 business days", price: "$9.99" },
  { id: "express", label: "Express", detail: "1–2 business days", price: "$19.99" },
];

export const metadata: Metadata = {
  title: "Checkout — Baxela Storefront",
};

export default async function CheckoutPage() {
  const [t, tLayout, format] = await Promise.all([
    getTranslations("checkout.checkout"),
    getTranslations("shared.layout"),
    getFormatter(),
  ]);

  const usd = {
    style: "currency",
    currency: "USD",
  } as const;

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

        <div className="mt-8 grid grid-cols-1 gap-12 lg:grid-cols-[1fr_420px]">
          <div className="space-y-10">
            <div>
              <h2 className="text-lg font-semibold text-foreground rtl:normal-case rtl:tracking-normal">
                {t("contact.texts.title")}
              </h2>
              <div className="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <Input
                  type="email"
                  placeholder={t("contact.placeholders.email")}
                  aria-label={t("contact.labels.email")}
                />
                <Input
                  type="tel"
                  placeholder={t("contact.placeholders.phone")}
                  aria-label={t("contact.labels.phone")}
                />
              </div>
            </div>

            <div>
              <h2 className="text-lg font-semibold text-foreground rtl:normal-case rtl:tracking-normal">
                {t("shipping_address.texts.title")}
              </h2>
              <div className="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <Input
                  type="text"
                  placeholder={t("shipping_address.placeholders.first_name")}
                  aria-label={t("shipping_address.labels.first_name")}
                />
                <Input
                  type="text"
                  placeholder={t("shipping_address.placeholders.last_name")}
                  aria-label={t("shipping_address.labels.last_name")}
                />
                <div className="sm:col-span-2">
                  <Input
                    type="text"
                    placeholder={t("shipping_address.placeholders.street")}
                    aria-label={t("shipping_address.labels.street")}
                  />
                </div>
                <Input
                  type="text"
                  placeholder={t("shipping_address.placeholders.apartment")}
                  aria-label={t("shipping_address.labels.apartment")}
                />
                <Input
                  type="text"
                  placeholder={t("shipping_address.placeholders.city")}
                  aria-label={t("shipping_address.labels.city")}
                />
                <Input
                  type="text"
                  placeholder={t("shipping_address.placeholders.state")}
                  aria-label={t("shipping_address.labels.state")}
                />
                <Input
                  type="text"
                  placeholder={t("shipping_address.placeholders.zip_code")}
                  aria-label={t("shipping_address.labels.zip_code")}
                />
              </div>
            </div>

            <div>
              <h2 className="text-lg font-semibold text-foreground rtl:normal-case rtl:tracking-normal">
                {t("shipping_method.texts.title")}
              </h2>
              <div className="mt-4 space-y-3">
                {SHIPPING_METHODS.map((method) => (
                  <label
                    key={method.id}
                    className="flex cursor-pointer items-center justify-between gap-4 rounded-default border border-border px-4 py-4 transition-colors hover:bg-muted"
                  >
                    <span className="flex items-center gap-3">
                      <input
                        type="radio"
                        name="shipping"
                        value={method.id}
                        defaultChecked={method.id === "free"}
                        className="size-4 accent-accent"
                      />
                      <span>
                        <span className="block text-sm font-medium text-foreground">
                          {method.label}
                        </span>
                        <span className="block text-sm text-secondary-text rtl:normal-case rtl:tracking-normal">
                          {method.detail}
                        </span>
                      </span>
                    </span>
                    <span className="text-sm font-medium text-foreground">
                      {method.price}
                    </span>
                  </label>
                ))}
              </div>
            </div>
          </div>

          <aside
            aria-label={t("summary.labels.aside")}
            className="h-fit rounded-default border border-border-light p-6 lg:sticky lg:top-28"
          >
            <h2 className="text-lg font-semibold text-foreground rtl:normal-case rtl:tracking-normal">
              {t("summary.labels.title")}
            </h2>
            <ul className="mt-6 space-y-4">
              {CART_ITEMS.map((item) => (
                <li key={item.id} className="flex items-center gap-4">
                  <span
                    className="size-16 shrink-0 rounded-default border border-border bg-muted"
                    aria-hidden="true"
                  />
                  <span className="min-w-0 flex-1">
                    <span className="block truncate text-sm font-medium text-foreground">
                      {item.name}
                    </span>
                    <span className="block text-sm text-secondary-text rtl:normal-case rtl:tracking-normal">
                      {t("summary.texts.qty", { count: item.quantity })}
                    </span>
                  </span>
                  <span className="text-sm font-medium text-foreground">
                    {format.number(item.price * item.quantity, usd)}
                  </span>
                </li>
              ))}
            </ul>

            <div className="mt-6 space-y-3 border-t border-border-light pt-6 text-sm">
              <p className="flex items-center justify-between text-secondary-text rtl:normal-case rtl:tracking-normal">
                <span>{t("summary.labels.subtotal")}</span>
                <span className="font-medium text-foreground">
                  {format.number(254.96, usd)}
                </span>
              </p>
              <p className="flex items-center justify-between text-secondary-text rtl:normal-case rtl:tracking-normal">
                <span>{t("summary.labels.shipping")}</span>
                <span className="font-medium text-foreground rtl:normal-case rtl:tracking-normal">
                  {SHIPPING_METHODS[0].price}
                </span>
              </p>
              <p className="flex items-center justify-between text-secondary-text rtl:normal-case rtl:tracking-normal">
                <span>{t("summary.labels.tax")}</span>
                <span className="font-medium text-foreground">
                  {format.number(20.4, usd)}
                </span>
              </p>
              <p className="flex items-center justify-between border-t border-border-light pt-3 text-base font-semibold text-foreground rtl:normal-case rtl:tracking-normal">
                <span>{t("summary.labels.total")}</span>
                <span>{format.number(275.36, usd)}</span>
              </p>
            </div>

            <div className="mt-6 flex gap-2">
              <Input
                type="text"
                placeholder={t("discount.placeholders.code")}
                aria-label={t("discount.labels.code")}
              />
              <Button type="button" variant="outline" fullWidth={false}>
                {t("discount.actions.apply")}
              </Button>
            </div>

            <Button type="button" variant="accent" fullWidth className="mt-6">
              {t("actions.place_order")}
            </Button>
          </aside>
        </div>
      </section>
    </>
  );
}
