import { getTranslations } from "next-intl/server";
import { HeartIcon } from "@/components/ui/icons";
import { Logo } from "@/components/ui/logo";
import { MegaMenuNavItem } from "@/components/mega-menu";
import { MobileMenu } from "@/components/mobile-menu";
import { SearchMenu } from "@/components/search-menu";
import { AccountMenu } from "@/components/layout/account-menu";
import { CartMenu } from "@/components/layout/cart-menu";
import { LanguageSwitcher } from "@/components/layout/language-switcher";
import { fetchMenu } from "@/lib/api/site";
import { categoryItems, megaMenuColumns, navItems } from "@/lib/menu";
import { Link } from "@/i18n/navigation";

export async function SiteHeader() {
  const t = await getTranslations("shared.layout");
  const headerMenu = await fetchMenu("header");
  const nav = (headerMenu?.links ?? []).filter((link) => link.title);
  const links = navItems(nav);
  const categories = categoryItems(nav);

  return (
    <>
      {/* Announcement bar */}
      <div className="bg-primary py-2.5 text-center text-sm text-primary-foreground rtl:normal-case rtl:tracking-normal">
        {t("announcement.text")}
      </div>

      {/* Header */}
      <header className="sticky top-0 z-40 border-b border-border-light bg-white">
        <div className="mx-auto flex h-20 max-w-7xl items-center justify-between px-4 sm:px-6">
          <Logo />
          <nav
            aria-label={t("header.labels.main_navigation")}
            className="hidden items-center gap-8 md:flex"
          >
            {nav.map((link) =>
              link.children.length > 0 ? (
                <MegaMenuNavItem
                  key={link.id}
                  label={link.title ?? ""}
                  href={link.url}
                  columns={megaMenuColumns(link)}
                  className="text-sm font-medium text-foreground transition-colors hover:text-accent"
                />
              ) : (
                <Link
                  key={link.id}
                  href={link.url}
                  className="text-sm font-medium text-foreground transition-colors hover:text-accent"
                >
                  {link.title}
                </Link>
              ),
            )}
          </nav>
          <div className="flex items-center gap-0.5 sm:gap-1">
            <SearchMenu categories={categories} />
            <Link
              href="/profile/wishlists"
              aria-label={t("header.actions.wishlist")}
              className="rounded-default p-2.5 text-foreground transition-colors hover:bg-muted"
            >
              <HeartIcon className="size-5" />
            </Link>
            <CartMenu />
            <AccountMenu />
            <LanguageSwitcher />
            <MobileMenu links={links} categories={categories} />
          </div>
        </div>
      </header>
    </>
  );
}