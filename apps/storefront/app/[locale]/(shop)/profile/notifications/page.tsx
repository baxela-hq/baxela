"use client";

import { useTranslations } from "next-intl";
import { useAuth } from "@/context/auth-context";
import { useProfile } from "@/context/profile-context";
import { Notifications } from "@/components/account/notifications";
import { accountIdentity } from "@/components/account/identity";

export default function ProfileNotificationsPage() {
  const tCommon = useTranslations("shared.common");
  const { user } = useAuth();
  const { profile } = useProfile();

  if (!profile) {
    return (
      <p className="py-16 text-center text-base text-secondary-text rtl:normal-case rtl:tracking-normal">
        {tCommon("messages.info.loading")}
      </p>
    );
  }

  const { initials } = accountIdentity(profile, user?.email);

  return <Notifications initials={initials} />;
}
