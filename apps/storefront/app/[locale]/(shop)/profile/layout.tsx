"use client";

import { useEffect, useMemo, useState, type ReactNode } from "react";
import { useTranslations } from "next-intl";
import { useAuth } from "@/context/auth-context";
import {
  ProfileProvider,
  type ProfileContextValue,
} from "@/context/profile-context";
import { api } from "@/lib/api/client";
import type { ApiProfile } from "@/lib/api/types";
import { Link, usePathname, useRouter } from "@/i18n/navigation";
import { AccountSidebar } from "@/components/account/account-sidebar";
import { accountIdentity } from "@/components/account/identity";
import { CloseIcon } from "@/components/ui/icons";

/**
 * Shared shell for the /profile/* pages: auth guard, single profile fetch
 * (shared through ProfileProvider), the completion banner and the sidebar.
 * Each tab page only renders its own content.
 */

const BANNER_DISMISSED_KEY = "baxela_profile_banner_dismissed";

export default function ProfileLayout({ children }: { children: ReactNode }) {
  const t = useTranslations("account.account");
  const { status, user, token } = useAuth();
  const router = useRouter();
  const pathname = usePathname();

  const [profile, setProfile] = useState<ApiProfile | null>(null);
  // null = still reading localStorage, so the banner never flashes during
  // the prerendered HTML pass.
  const [bannerDismissed, setBannerDismissed] = useState<boolean | null>(null);

  useEffect(() => {
    if (status === "unauthenticated") {
      router.replace(`/login?next=${pathname}`);
    }
  }, [status, router, pathname]);

  useEffect(() => {
    if (status !== "authenticated" || !token) return;
    void (async () => {
      try {
        // The profile row may not exist yet; the greeting then falls back
        // to the email prefix.
        setProfile(await api.get<ApiProfile>("/user/user/profile", { token }));
      } catch {
        setProfile({
          full_name: null,
          display_name: null,
          bio: null,
          avatar: null,
          gender: null,
          date_of_birth: null,
        });
      }
    })();
  }, [status, token]);

  useEffect(() => {
    // The async IIFE keeps the setState off the synchronous effect path
    // (mirrors auth-context's session read).
    void (async () => {
      await Promise.resolve();
      setBannerDismissed(
        window.localStorage.getItem(BANNER_DISMISSED_KEY) === "1",
      );
    })();
  }, []);

  const { displayName, initials } = accountIdentity(profile, user?.email);

  const profileValue = useMemo<ProfileContextValue>(
    () => ({ profile, setProfile }),
    [profile],
  );

  const showBanner =
    bannerDismissed === false &&
    pathname !== "/profile/personal-information" &&
    (profile?.full_name?.trim() ?? "") === "";

  const onDismissBanner = () => {
    window.localStorage.setItem(BANNER_DISMISSED_KEY, "1");
    setBannerDismissed(true);
  };

  return (
    <ProfileProvider value={profileValue}>
      <section className="mx-auto max-w-7xl px-4 py-12 sm:px-6">
        <h1 className="text-3xl font-bold text-foreground rtl:normal-case rtl:tracking-normal">
          {t("texts.title")}
        </h1>

        {showBanner ? (
          <div
            role="status"
            className="mt-6 flex flex-wrap items-center justify-between gap-4 rounded-default border border-accent/30 bg-accent/5 px-6 py-4"
          >
            <div className="min-w-0">
              <p className="text-sm font-semibold text-foreground rtl:normal-case rtl:tracking-normal">
                {t("banner.texts.title")}
              </p>
              <p className="mt-0.5 text-sm text-secondary-text rtl:normal-case rtl:tracking-normal">
                {t("banner.texts.description")}
              </p>
            </div>
            <div className="flex items-center gap-2">
              <Link
                href="/profile/personal-information?edit=1"
                className="inline-flex h-10 items-center justify-center rounded-default bg-primary px-5 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90 rtl:normal-case rtl:tracking-normal"
              >
                {t("banner.actions.complete")}
              </Link>
              <button
                type="button"
                onClick={onDismissBanner}
                aria-label={t("banner.actions.dismiss")}
                className="rounded-default p-2.5 text-secondary-text transition-colors hover:bg-muted hover:text-foreground"
              >
                <CloseIcon className="size-4" />
              </button>
            </div>
          </div>
        ) : null}

        <div className="mt-10 grid grid-cols-1 gap-10 lg:grid-cols-[300px_1fr]">
          <AccountSidebar displayName={displayName} initials={initials} />
          <div className="min-w-0">{children}</div>
        </div>
      </section>
    </ProfileProvider>
  );
}
