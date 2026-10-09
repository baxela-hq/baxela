import { getTranslations } from "next-intl/server";
import { Link } from "@/i18n/navigation";

/**
 * Inline 404 body for matched routes whose resource is missing (hidden,
 * scheduled or unknown slugs). Rendered on the normal path — not via
 * notFound(), whose error-path render comes out blank under the
 * app/[locale] root layout (Next 16 + top-level dynamic segment).
 */
export default async function NotFoundContent() {
  const t = await getTranslations("shared.common");

  return (
    <main className="flex min-h-[60vh] flex-col items-center justify-center gap-4 bg-background px-6 py-24 text-center">
      <p className="text-5xl font-bold text-foreground">404</p>
      <p className="text-base text-secondary-text rtl:normal-case rtl:tracking-normal">
        {t("messages.info.not_found")}
      </p>
      <Link
        href="/"
        className="text-sm font-medium text-accent underline underline-offset-4"
      >
        {t("form.actions.back")}
      </Link>
    </main>
  );
}
