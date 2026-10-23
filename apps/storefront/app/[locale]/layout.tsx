import type { Metadata } from "next";
import { Geist, Geist_Mono, Vazirmatn } from "next/font/google";
import { hasLocale } from "next-intl";
import { NextIntlClientProvider } from "next-intl";
import { notFound } from "next/navigation";
import { AuthProvider } from "@/context/auth-context";
import { CartProvider } from "@/context/cart-context";
import { NotificationsProvider } from "@/context/notifications-context";
import { ThemeModeProvider } from "@/context/theme-mode-context";
import { CurrencyProvider } from "@/context/currency-context";
import { Toaster } from "@/components/ui/sonner";
import { ThemeModeInitScript } from "@/components/theme-mode-init-script";
import { fetchSettings } from "@/lib/api/site";
import { resolveDisplayCurrency, toDisplayCurrencyData } from "@/lib/display-currency";
import { SITE_URL } from "@/lib/site-url";
import { resolveSiteTheme } from "@/lib/theme";
import { routing } from "@/i18n/routing";
import { localeDirection } from "@/lib/i18n";

import "../globals.css";

const geistSans = Geist({
  variable: "--font-geist-sans",
  subsets: ["latin"],
});

const geistMono = Geist_Mono({
  variable: "--font-geist-mono",
  subsets: ["latin"],
});

// Geist has no Arabic-script glyphs — Vazirmatn covers RTL locales (see
// globals.css: html[dir="rtl"] swaps the body font).
const vazirmatn = Vazirmatn({
  variable: "--font-vazirmatn",
  subsets: ["arabic", "latin"],
});

export const metadata: Metadata = {
  // Resolves relative metadata URLs (canonicals, OG images) against the
  // storefront's public origin.
  metadataBase: new URL(SITE_URL),
  title: "Baxela Storefront",
  description: "Baxela e-commerce storefront built from the Figma UI kit",
};

// Settings-dependent markup (the site theme attribute, the announcement
// bar) is rendered in this layout; ISR keeps prerendered pages within a
// minute of admin changes instead of freezing them until the next build.
export const revalidate = 60;

export function generateStaticParams() {
  return routing.locales.map((locale) => ({ locale }));
}

export default async function LocaleLayout({
  children,
  params,
}: LayoutProps<"/[locale]">) {
  const { locale } = await params;
  if (!hasLocale(routing.locales, locale)) {
    notFound();
  }

  const dir = localeDirection(locale);

  // Site theme from the admin-selected storefront_theme setting; unknown or
  // missing values (also when the backend is unreachable) fall back to the
  // default theme. fetchSettings is per-request cached, so this dedupes with
  // the SiteHeader announcement lookup.
  const settings = await fetchSettings();
  // Visitor's display currency (cookie, else the locale's first-visit
  // default, + active rates) — per-request cached, deduping with the
  // price-format resolution and the header switcher into one fetch.
  const display = await resolveDisplayCurrency(locale);
  const siteTheme = resolveSiteTheme(
    settings?.find((setting) => setting.name === "storefront_theme")?.value,
  );

  return (
    <html
      lang={locale}
      dir={dir}
      data-theme={siteTheme}
      data-mode="light"
      suppressHydrationWarning
      className={`${geistSans.variable} ${geistMono.variable} ${vazirmatn.variable} h-full antialiased`}
    >
      <body className="min-h-full flex flex-col">
        {/* Applies the theme-mode cookie to <html data-mode> before first
            paint — keep in sync with lib/theme.ts. Server-inserted into the
            <head> so the <script> never becomes a client-rendered React
            element (React 19.2 warns those are never executed). */}
        <ThemeModeInitScript />
        <NextIntlClientProvider>
          <AuthProvider>
            <CartProvider>
              <NotificationsProvider>
                <CurrencyProvider data={toDisplayCurrencyData(display)}>
                  <ThemeModeProvider>{children}</ThemeModeProvider>
                </CurrencyProvider>
              </NotificationsProvider>
            </CartProvider>
          </AuthProvider>
          <Toaster dir={dir} />
        </NextIntlClientProvider>
      </body>
    </html>
  );
}
