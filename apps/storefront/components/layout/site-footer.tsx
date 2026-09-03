import { getTranslations } from "next-intl/server";
import { MEGA_MENU_COLUMNS } from "@/components/mega-menu-columns";
import { Logo } from "@/components/ui/logo";
import { Link } from "@/i18n/navigation";

export async function SiteFooter() {
  const t = await getTranslations("shared.layout");

  return (
    <footer className="border-t border-border-light bg-white">
      <div className="mx-auto grid max-w-7xl gap-10 px-6 py-14 md:grid-cols-4">
        <div>
          <Logo variant="dark" />
          <p className="mt-4 max-w-xs text-sm text-secondary-text rtl:normal-case rtl:tracking-normal">
            {t("footer.texts.tagline")}
          </p>
        </div>
        <div>
          <h3 className="text-sm font-semibold uppercase tracking-wide text-foreground rtl:normal-case rtl:tracking-normal">
            {t("footer.columns.shop.title")}
          </h3>
          {/* Mock category links — replaced by catalog data from the API */}
          <ul className="mt-4 space-y-3 text-sm text-secondary-text">
            {MEGA_MENU_COLUMNS.map((column) => (
              <li key={column.title}>
                <Link
                  href="/products"
                  className="transition-colors hover:text-foreground"
                >
                  {column.title}
                </Link>
              </li>
            ))}
          </ul>
        </div>
        <div>
          <h3 className="text-sm font-semibold uppercase tracking-wide text-foreground rtl:normal-case rtl:tracking-normal">
            {t("footer.columns.support.title")}
          </h3>
          <ul className="mt-4 space-y-3 text-sm text-secondary-text">
            <li>
              <Link
                href="/contact"
                className="transition-colors hover:text-foreground"
              >
                {t("footer.columns.support.links.contact_us")}
              </Link>
            </li>
            <li>
              <Link
                href="/contact"
                className="transition-colors hover:text-foreground"
              >
                {t("footer.columns.support.links.shipping_returns")}
              </Link>
            </li>
            <li>
              <Link
                href="/contact"
                className="transition-colors hover:text-foreground"
              >
                {t("footer.columns.support.links.size_guide")}
              </Link>
            </li>
            <li>
              <Link
                href="/contact"
                className="transition-colors hover:text-foreground"
              >
                {t("footer.columns.support.links.faq")}
              </Link>
            </li>
          </ul>
        </div>
        <div>
          <h3 className="text-sm font-semibold uppercase tracking-wide text-foreground rtl:normal-case rtl:tracking-normal">
            {t("footer.columns.account.title")}
          </h3>
          <ul className="mt-4 space-y-3 text-sm text-secondary-text">
            <li>
              <Link
                href="/login"
                className="transition-colors hover:text-foreground"
              >
                {t("footer.columns.account.links.login")}
              </Link>
            </li>
            <li>
              <Link
                href="/signup"
                className="transition-colors hover:text-foreground"
              >
                {t("footer.columns.account.links.create_account")}
              </Link>
            </li>
            <li>
              <Link
                href="/forgot-password"
                className="transition-colors hover:text-foreground"
              >
                {t("footer.columns.account.links.forgot_password")}
              </Link>
            </li>
          </ul>
        </div>
      </div>
      <div className="border-t border-border-light">
        <div className="mx-auto flex max-w-7xl flex-col items-center justify-between gap-3 px-6 py-6 text-sm text-secondary-text md:flex-row">
          <p className="rtl:normal-case rtl:tracking-normal">
            {t("footer.copyright.text", {
              year: new Date().getFullYear(),
              company: "Baxela",
            })}
          </p>
          <div className="flex gap-6">
            <Link
              href="/contact"
              className="transition-colors hover:text-foreground"
            >
              {t("footer.legal.privacy_policy")}
            </Link>
            <Link
              href="/contact"
              className="transition-colors hover:text-foreground"
            >
              {t("footer.legal.terms_of_service")}
            </Link>
          </div>
        </div>
      </div>
    </footer>
  );
}
