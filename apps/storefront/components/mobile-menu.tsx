"use client";

import { useEffect, useState } from "react";
import { useTranslations } from "next-intl";

import { MEGA_MENU_COLUMNS } from "@/components/mega-menu-columns";
import { Logo } from "@/components/ui/logo";
import { CloseIcon, MenuIcon } from "@/components/ui/icons";
import { Link, usePathname } from "@/i18n/navigation";

const NAV_LINKS = [
  { key: "home", href: "/" },
  { key: "products", href: "/products" },
  { key: "about", href: "/about" },
  { key: "contact", href: "/contact" },
] as const;

/**
 * Hamburger trigger + slide-in navigation drawer for viewports below `md`,
 * where the desktop nav is hidden. Mirrors the desktop navigation: the main
 * links plus the category shortcuts from the mega menu. The whole component
 * is display:none at `md` and up, so the drawer never renders on desktop.
 */
export function MobileMenu() {
  const [open, setOpen] = useState(false);
  const pathname = usePathname();
  const t = useTranslations("shared.layout");

  useEffect(() => {
    if (!open) return;

    const onKeyDown = (event: KeyboardEvent) => {
      if (event.key === "Escape") setOpen(false);
    };
    document.addEventListener("keydown", onKeyDown);
    document.body.style.overflow = "hidden";

    return () => {
      document.removeEventListener("keydown", onKeyDown);
      document.body.style.overflow = "";
    };
  }, [open]);

  // Close automatically when the viewport grows past the md breakpoint
  // (e.g. rotating a phone to landscape), otherwise the hidden drawer would
  // keep the body scroll locked on the desktop layout.
  useEffect(() => {
    const mediaQuery = window.matchMedia("(min-width: 768px)");
    const onChange = (event: MediaQueryListEvent) => {
      if (event.matches) setOpen(false);
    };
    mediaQuery.addEventListener("change", onChange);
    return () => mediaQuery.removeEventListener("change", onChange);
  }, []);

  return (
    <div className="md:hidden">
      <button
        type="button"
        aria-label={t("header.actions.open_menu")}
        aria-expanded={open}
        aria-controls="mobile-menu"
        onClick={() => setOpen(true)}
        className="rounded-default p-2.5 text-foreground transition-colors hover:bg-muted"
      >
        <MenuIcon className="size-5" />
      </button>

      <div
        aria-hidden="true"
        onClick={() => setOpen(false)}
        className={`fixed inset-0 z-50 bg-foreground/40 transition-opacity duration-200 ${
          open ? "opacity-100" : "pointer-events-none opacity-0"
        }`}
      />

      {/* Always mounted so the slide transition runs both ways; inert keeps
          the closed drawer out of the tab order and accessibility tree. */}
      <div
        id="mobile-menu"
        inert={!open}
        className={`fixed inset-y-0 start-0 z-50 flex w-80 max-w-[85vw] flex-col border-e border-border-light bg-white transition-transform duration-200 ${
          open ? "translate-x-0" : "ltr:-translate-x-full rtl:translate-x-full"
        }`}
      >
        <div className="flex items-center justify-between border-b border-border-light px-6 py-4">
          <Logo />
          <button
            type="button"
            aria-label={t("header.actions.close_menu")}
            onClick={() => setOpen(false)}
            className="rounded-default p-2.5 text-foreground transition-colors hover:bg-muted"
          >
            <CloseIcon className="size-5" />
          </button>
        </div>

        <nav
          aria-label={t("mobile_menu.labels.navigation")}
          className="flex-1 overflow-y-auto px-6 py-6"
        >
          <ul className="space-y-1">
            {NAV_LINKS.map((link) => (
              <li key={link.key}>
                <Link
                  href={link.href}
                  onClick={() => setOpen(false)}
                  className={
                    pathname === link.href
                      ? "block rounded-default px-3 py-3 text-base font-medium text-foreground underline underline-offset-4 rtl:normal-case rtl:tracking-normal"
                      : "block rounded-default px-3 py-3 text-base font-medium text-foreground transition-colors hover:bg-muted hover:text-accent rtl:normal-case rtl:tracking-normal"
                  }
                >
                  {t(`header.nav.${link.key}`)}
                </Link>
              </li>
            ))}
          </ul>

          <p className="mt-8 text-sm font-semibold uppercase tracking-wide text-secondary-text rtl:normal-case rtl:tracking-normal">
            {t("mobile_menu.texts.categories")}
          </p>
          <ul className="mt-4 space-y-3">
            {MEGA_MENU_COLUMNS.map((column) => (
              <li key={column.title}>
                <Link
                  href={column.links[0].href}
                  onClick={() => setOpen(false)}
                  className="block py-1 text-base text-foreground transition-colors hover:text-accent"
                >
                  {column.title}
                </Link>
              </li>
            ))}
          </ul>
        </nav>
      </div>
    </div>
  );
}
