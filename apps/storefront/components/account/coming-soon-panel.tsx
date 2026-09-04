"use client";

import type { ReactNode } from "react";
import { useTranslations } from "next-intl";

/**
 * Placeholder panel for account sections whose backend endpoints are not
 * implemented yet (wishlists, saved cards, notifications, settings).
 */
export function ComingSoonPanel({
  icon,
  title,
}: {
  icon: ReactNode;
  title: string;
}) {
  const t = useTranslations("account.account");

  return (
    <div className="grid place-items-center rounded-default border border-border-light bg-white px-6 py-24 text-center">
      <span className="grid size-14 place-items-center rounded-full bg-muted text-secondary-text [&>svg]:size-6">
        {icon}
      </span>
      <p className="mt-6 text-base font-medium text-foreground rtl:normal-case rtl:tracking-normal">
        {title}
      </p>
      <p className="mt-2 text-sm text-secondary-text rtl:normal-case rtl:tracking-normal">
        {t("texts.coming_soon")}
      </p>
    </div>
  );
}
