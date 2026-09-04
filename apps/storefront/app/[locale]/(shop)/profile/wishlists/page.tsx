"use client";

import { useTranslations } from "next-intl";
import { ComingSoonPanel } from "@/components/account/coming-soon-panel";
import { HeartIcon } from "@/components/ui/icons";

export default function ProfileWishlistsPage() {
  const t = useTranslations("account.account");

  return (
    <ComingSoonPanel
      icon={<HeartIcon className="size-6" />}
      title={t("labels.my_wishlists")}
    />
  );
}
