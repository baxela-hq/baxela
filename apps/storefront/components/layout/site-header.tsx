import { getTranslations } from "next-intl/server";
import { HeartIcon, ShoppingCartIcon } from "@/components/ui/icons";
import { Logo } from "@/components/ui/logo";
import { MegaMenuNavItem } from "@/components/mega-menu";
import { MobileMenu } from "@/components/mobile-menu";
import { SearchMenu } from "@/components/search-menu";
import { AccountMenu } from "@/components/layout/account-menu";
import { LanguageSwitcher } from "@/components/layout/language-switcher";
import { Link } from "@/i18n/navigation";

const NAV_LINKS = [
  { key: "home", href: "/" },
  { key: "products", href: "/products" },
  { key: "about", href: "/about" },
  { key: "contact", href: "/contact" },
] as const;

export async function SiteHeader() {
  const t = await getTranslations("shared.layout");

  return (
    <>
      {/* Announcement bar */}
      <div className="bg-primary py-2.5 text-center text-sm text-primary-foreground rtl:normal-case rtl:tracking-normal">
        {t("announcement.text")}
      </div>

      {/* Header */}
      <header className="sticky top-0 z-40 border-b border-border-light bg-white">
        <div className="mx-auto flex h-20 max-w-7xl items-center justify-between px-4 sm:px-6">
          <Logo variant="dark" />
          <nav
            aria-label={t("header.labels.main_navigation")}
            className="hidden items-center gap-8 md:flex"
          >
            {NAV_LINKS.map((link) =>
              link.key === "products" ? (
                <MegaMenuNavItem
                  key={link.key}
                  label={t(`header.nav.${link.key}`)}
                  href={link.href}
                  className="text-sm font-medium text-foreground transition-colors hover:text-accent"
                />
              ) : (
                <Link
                  key={link.key}
                  href={link.href}
                  className="text-sm font-medium text-foreground transition-colors hover:text-accent"
                >
                  {t(`header.nav.${link.key}`)}
                </Link>
              ),
            )}
          </nav>
          <div className="flex items-center gap-0.5 sm:gap-1">
            <SearchMenu />
            <button
              type="button"
              aria-label={t("header.actions.wishlist")}
              className="rounded-default p-2.5 text-foreground transition-colors hover:bg-muted"
            >
              <HeartIcon className="size-5" />
            </button>
            <Link
              href="/cart"
              aria-label={t("header.actions.cart")}
              className="rounded-default p-2.5 text-foreground transition-colors hover:bg-muted"
            >
              <ShoppingCartIcon className="size-5" />
            </Link>
            <AccountMenu />
            <LanguageSwitcher />
            <MobileMenu />
          </div>
        </div>
      </header>
    </>
  );
}
