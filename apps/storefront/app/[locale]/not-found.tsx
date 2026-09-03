import { getTranslations } from "next-intl/server";
import { Link } from "@/i18n/navigation";

export default async function NotFound() {
  const t = await getTranslations("shared.common");

  return (
    <main className="flex min-h-screen flex-col items-center justify-center gap-4 bg-background px-6 text-center">
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
