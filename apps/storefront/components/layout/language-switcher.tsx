"use client";

import { useLocale, useTranslations } from "next-intl";
import { usePathname, useRouter } from "@/i18n/navigation";

/**
 * Toggles between the two supported locales, keeping the current path.
 * Shows the name of the language it switches TO.
 */
export function LanguageSwitcher() {
  const locale = useLocale();
  const t = useTranslations("shared.layout");
  const pathname = usePathname();
  const router = useRouter();

  const nextLocale = locale === "fa" ? "en" : "fa";

  return (
    <button
      type="button"
      aria-label={t("header.actions.language")}
      onClick={() => router.replace(pathname, { locale: nextLocale })}
      className="rounded-default px-2 py-1.5 text-xs font-medium text-foreground transition-colors hover:bg-muted"
    >
      {nextLocale === "fa" ? "فارسی" : "EN"}
    </button>
  );
}
