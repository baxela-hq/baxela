import { getTranslations } from "next-intl/server";
import { fetchMenu } from "@/lib/api/site";
import { Logo } from "@/components/ui/logo";
import { Link } from "@/i18n/navigation";

export async function SiteFooter() {
  const t = await getTranslations("shared.layout");
  const footerMenu = await fetchMenu("footer");
  const columns = (footerMenu?.links ?? []).filter((link) => link.title);

  return (
    <footer className="border-t border-border-light bg-white">
      <div className="mx-auto grid max-w-7xl gap-10 px-6 py-14 md:grid-cols-4">
        <div>
          <Logo />
          <p className="mt-4 max-w-xs text-sm text-secondary-text rtl:normal-case rtl:tracking-normal">
            {t("footer.texts.tagline")}
          </p>
        </div>
        {columns.map((column) => (
          <div key={column.id}>
            <h3 className="text-sm font-semibold uppercase tracking-wide text-foreground rtl:normal-case rtl:tracking-normal">
              {column.title}
            </h3>
            <ul className="mt-4 space-y-3 text-sm text-secondary-text">
              {column.children.map((child) => (
                <li key={child.id}>
                  <Link
                    href={child.url}
                    className="transition-colors hover:text-foreground"
                  >
                    {child.title}
                  </Link>
                </li>
              ))}
            </ul>
          </div>
        ))}
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
              href="/pages/privacy-policy"
              className="transition-colors hover:text-foreground"
            >
              {t("footer.legal.privacy_policy")}
            </Link>
            <Link
              href="/pages/terms-of-service"
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