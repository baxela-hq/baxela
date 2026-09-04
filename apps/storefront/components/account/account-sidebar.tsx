"use client";

import type { ReactNode } from "react";
import { useTranslations } from "next-intl";
import { useAuth } from "@/context/auth-context";
import {
  BellIcon,
  BoxIcon,
  CreditCardIcon,
  HeartIcon,
  LogoutIcon,
  MapPinIcon,
  SettingsIcon,
  UserIcon,
} from "@/components/ui/icons";
import { Link, usePathname, useRouter } from "@/i18n/navigation";

export type AccountTab =
  | "personal_information"
  | "my_orders"
  | "my_wishlists"
  | "manage_addresses"
  | "saved_cards"
  | "notifications"
  | "settings";

const TABS: { key: AccountTab; href: string; icon: ReactNode }[] = [
  {
    key: "personal_information",
    href: "/profile/personal-information",
    icon: <UserIcon className="size-5" />,
  },
  { key: "my_orders", href: "/profile/orders", icon: <BoxIcon className="size-5" /> },
  {
    key: "my_wishlists",
    href: "/profile/wishlists",
    icon: <HeartIcon className="size-5" />,
  },
  {
    key: "manage_addresses",
    href: "/profile/addresses",
    icon: <MapPinIcon className="size-5" />,
  },
  {
    key: "saved_cards",
    href: "/profile/cards",
    icon: <CreditCardIcon className="size-5" />,
  },
  {
    key: "notifications",
    href: "/profile/notifications",
    icon: <BellIcon className="size-5" />,
  },
  {
    key: "settings",
    href: "/profile/settings",
    icon: <SettingsIcon className="size-5" />,
  },
];

interface AccountSidebarProps {
  displayName: string;
  initials: string;
}

/**
 * The account navigation card from the profile screens: greeting with the
 * customer's initials, one section per route, and sign out at the bottom.
 * The active item follows the current pathname; `/profile` itself redirects
 * to the orders tab, which is the fallback here too.
 */
export function AccountSidebar({ displayName, initials }: AccountSidebarProps) {
  const t = useTranslations("account.account");
  const { signOut } = useAuth();
  const router = useRouter();
  const pathname = usePathname();

  const active =
    TABS.find((tab) => pathname.startsWith(tab.href))?.key ?? "my_orders";

  const onSignOut = () => {
    signOut();
    router.replace("/login");
  };

  return (
    <aside className="h-fit rounded-default border border-border-light bg-white">
      <div className="flex items-center gap-4 border-b border-border-light p-6">
        <span
          aria-hidden="true"
          className="grid size-14 shrink-0 place-items-center rounded-full bg-muted text-base font-semibold text-foreground"
        >
          {initials}
        </span>
        <span className="min-w-0">
          <span className="block text-sm text-secondary-text rtl:normal-case rtl:tracking-normal">
            {t("texts.greeting")} 👋
          </span>
          <span className="mt-1 block truncate text-base font-semibold text-foreground rtl:normal-case rtl:tracking-normal">
            {displayName}
          </span>
        </span>
      </div>

      <nav aria-label={t("texts.title")} className="p-3">
        <ul className="space-y-1">
          {TABS.map((tab) => (
            <li key={tab.key}>
              <Link
                href={tab.href}
                aria-current={active === tab.key ? "page" : undefined}
                className={
                  active === tab.key
                    ? "flex w-full items-center gap-3 rounded-default bg-primary px-4 py-3.5 text-sm font-medium text-primary-foreground transition-colors rtl:normal-case rtl:tracking-normal"
                    : "flex w-full items-center gap-3 rounded-default px-4 py-3.5 text-sm font-medium text-foreground transition-colors hover:bg-muted rtl:normal-case rtl:tracking-normal"
                }
              >
                {tab.icon}
                {t(`labels.${tab.key}`)}
              </Link>
            </li>
          ))}
        </ul>
      </nav>

      <div className="border-t border-border-light p-3">
        <button
          type="button"
          onClick={onSignOut}
          className="flex w-full items-center gap-3 rounded-default px-4 py-3.5 text-sm font-medium text-secondary-text transition-colors hover:bg-muted hover:text-red-600 rtl:normal-case rtl:tracking-normal"
        >
          <LogoutIcon className="size-5" />
          {t("labels.sign_out")}
        </button>
      </div>
    </aside>
  );
}
