"use client";

import { useEffect, useState } from "react";
import { useTranslations } from "next-intl";
import { useAuth } from "@/context/auth-context";
import { api } from "@/lib/api/client";
import type { ApiProfile } from "@/lib/api/types";
import { useRouter } from "@/i18n/navigation";
import {
  AccountSidebar,
  type AccountTab,
} from "@/components/account/account-sidebar";
import { AddressManager } from "@/components/account/address-manager";
import { ComingSoonPanel } from "@/components/account/coming-soon-panel";
import { Notifications } from "@/components/account/notifications";
import { OrderList } from "@/components/account/order-list";
import {
  OrdersToolbar,
  type OrderStatusFilter,
} from "@/components/account/orders-toolbar";
import { SavedCards } from "@/components/account/saved-cards";
import { Settings } from "@/components/account/settings";
import { EMPTY_PROFILE, PersonalInfo } from "@/components/account/personal-info";
import { HeartIcon } from "@/components/ui/icons";

/**
 * The account hub ("My Profile" screen): sidebar navigation plus the active
 * section. Wishlists is a design placeholder — the backend has no endpoint
 * for it yet; saved cards, notifications and settings are mock UI.
 */
export default function ProfilePage() {
  const t = useTranslations("account.account");
  const tCommon = useTranslations("shared.common");
  const { status, token, user } = useAuth();
  const router = useRouter();

  const [tab, setTab] = useState<AccountTab>("my_orders");
  const [profile, setProfile] = useState<ApiProfile | null>(null);
  const [search, setSearch] = useState("");
  const [statusFilter, setStatusFilter] = useState<OrderStatusFilter>("all");

  useEffect(() => {
    if (status === "unauthenticated") {
      router.replace("/login?next=/profile");
    }
  }, [status, router]);

  useEffect(() => {
    if (status !== "authenticated" || !token) return;
    void (async () => {
      try {
        // The profile row may not exist yet; the greeting then falls back
        // to the email prefix.
        setProfile(await api.get<ApiProfile>("/user/user/profile", { token }));
      } catch {
        setProfile(EMPTY_PROFILE);
      }
    })();
  }, [status, token]);

  if (status !== "authenticated") {
    return (
      <section className="mx-auto max-w-7xl px-6 py-24 text-center">
        <p className="text-base text-secondary-text rtl:normal-case rtl:tracking-normal">
          {tCommon("messages.info.loading")}
        </p>
      </section>
    );
  }

  const fullName =
    [profile?.full_name, profile?.display_name]
      .find((value) => Boolean(value?.trim()))
      ?.trim() ?? "";
  const emailPrefix = user?.email.split("@")[0] ?? "";
  const displayName = fullName || emailPrefix;
  const initials = (fullName || emailPrefix)
    .split(/[\s._-]+/)
    .map((part) => part[0])
    .join("")
    .slice(0, 2)
    .toUpperCase();

  return (
    <section className="mx-auto max-w-7xl px-4 py-12 sm:px-6">
      <div className="flex flex-wrap items-center justify-between gap-6">
        <h1 className="text-3xl font-bold text-foreground rtl:normal-case rtl:tracking-normal">
          {t("texts.title")}
        </h1>
        {tab === "my_orders" ? (
          <OrdersToolbar
            search={search}
            onSearchChange={setSearch}
            status={statusFilter}
            onStatusChange={setStatusFilter}
          />
        ) : null}
      </div>

      <div className="mt-10 grid grid-cols-1 gap-10 lg:grid-cols-[300px_1fr]">
        <AccountSidebar
          active={tab}
          onSelect={setTab}
          displayName={displayName}
          initials={initials}
        />

        <div className="min-w-0">
          {tab === "my_orders" ? (
            <OrderList search={search} statusFilter={statusFilter} />
          ) : null}

          {tab === "personal_information" ? (
            profile ? (
              <PersonalInfo profile={profile} onSaved={setProfile} />
            ) : (
              <p className="py-16 text-center text-base text-secondary-text rtl:normal-case rtl:tracking-normal">
                {tCommon("messages.info.loading")}
              </p>
            )
          ) : null}

          {tab === "manage_addresses" ? <AddressManager /> : null}

          {tab === "my_wishlists" ? (
            <ComingSoonPanel
              icon={<HeartIcon className="size-6" />}
              title={t("labels.my_wishlists")}
            />
          ) : null}
          {tab === "saved_cards" ? <SavedCards /> : null}
          {tab === "notifications" ? (
            <Notifications initials={initials} />
          ) : null}
          {tab === "settings" ? <Settings /> : null}
        </div>
      </div>
    </section>
  );
}
