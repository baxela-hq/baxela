"use client";

import { useTranslations } from "next-intl";
import { MoonIcon, SunIcon } from "@/components/ui/icons";
import { useThemeMode } from "@/context/theme-mode-context";

/**
 * Toggles the resolved display mode (light ↔ dark). The icons swap via the
 * CSS dark: variant on <html data-mode>, so server and client markup always
 * agree. "System" stays selectable from the account appearance setting.
 */
export function ThemeToggle() {
  const t = useTranslations("shared.layout");
  const { resolved, setPreference } = useThemeMode();

  return (
    <button
      type="button"
      aria-label={t("header.actions.theme")}
      onClick={() => setPreference(resolved === "dark" ? "light" : "dark")}
      className="rounded-default p-2.5 text-foreground transition-colors hover:bg-muted"
    >
      <SunIcon className="size-5 dark:hidden" />
      <MoonIcon className="hidden size-5 dark:block" />
    </button>
  );
}
