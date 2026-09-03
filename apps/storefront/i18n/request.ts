import { hasLocale } from "next-intl";
import { getRequestConfig } from "next-intl/server";
import { notFound } from "next/navigation";
import { locale as getRootLocale } from "next/root-params";
import { routing } from "./routing";

/**
 * Loads the split dictionary files for a locale and nests them so the
 * runtime namespaces mirror the file paths:
 *   messages/{locale}/shared/common.json -> useTranslations('shared.common')
 */
async function loadMessages(locale: string) {
  const [common, layout, home, products, product, checkout, cart, auth] =
    await Promise.all([
      import(`../messages/${locale}/shared/common.json`),
      import(`../messages/${locale}/shared/layout.json`),
      import(`../messages/${locale}/home/home.json`),
      import(`../messages/${locale}/catalog/products.json`),
      import(`../messages/${locale}/catalog/product.json`),
      import(`../messages/${locale}/checkout/checkout.json`),
      import(`../messages/${locale}/cart/cart.json`),
      import(`../messages/${locale}/auth/auth.json`),
    ]);

  return {
    shared: {
      common: common.default,
      layout: layout.default,
    },
    home: { home: home.default },
    catalog: {
      products: products.default,
      product: product.default,
    },
    checkout: { checkout: checkout.default },
    cart: { cart: cart.default },
    auth: { auth: auth.default },
  };
}

export default getRequestConfig(async ({ locale }) => {
  // The [locale] segment is a catch-all, so invalid values (e.g. /unknown.txt)
  // resolve to a 404 via notFound().
  if (!locale) {
    const paramValue = await getRootLocale();
    if (hasLocale(routing.locales, paramValue)) {
      locale = paramValue;
    } else {
      notFound();
    }
  }

  return {
    locale,
    messages: await loadMessages(locale),
  };
});
