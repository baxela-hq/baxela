"use client";

import { Suspense } from "react";
import { useTranslations } from "next-intl";
import { useSearchParams } from "next/navigation";
import { useProfile } from "@/context/profile-context";
import { PersonalInfo } from "@/components/account/personal-info";

function PersonalInformationView() {
  const tCommon = useTranslations("shared.common");
  const { profile, setProfile } = useProfile();
  const searchParams = useSearchParams();

  if (!profile) {
    return (
      <p className="py-16 text-center text-base text-secondary-text rtl:normal-case rtl:tracking-normal">
        {tCommon("messages.info.loading")}
      </p>
    );
  }

  return (
    <PersonalInfo
      profile={profile}
      onSaved={setProfile}
      initialEditing={searchParams.get("edit") === "1"}
    />
  );
}

export default function ProfilePersonalInformationPage() {
  return (
    <Suspense>
      <PersonalInformationView />
    </Suspense>
  );
}
