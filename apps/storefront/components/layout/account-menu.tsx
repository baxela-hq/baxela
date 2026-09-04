"use client";

import { useTranslations } from "next-intl";
import { useAuth } from "@/context/auth-context";
import { UserIcon } from "@/components/ui/icons";
import { Link } from "@/i18n/navigation";

/**
 * Header entry point to the profile page. Auth lives in localStorage, so
 * this has to be a client island inside the server-rendered SiteHeader:
 * a dark login button like the design when signed out, an account icon
 * when signed in.
 */
export function AccountMenu() {
  const t = useTranslations("shared.layout");
  const { status } = useAuth();

  if (status === "unauthenticated") {
    return (
      <Link
        href="/login"
        className="hidden h-10 items-center rounded-default bg-primary px-6 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90 md:inline-flex rtl:normal-case rtl:tracking-normal"
      >
        {t("header.actions.login")}
      </Link>
    );
  }

  return (
    <Link
      href="/profile"
      aria-label={t("header.actions.account")}
      className="rounded-default p-2.5 text-foreground transition-colors hover:bg-muted"
    >
      <UserIcon className="size-5" />
    </Link>
  );
}
