"use client";

import { useTranslations } from "next-intl";
import { WishlistGrid } from "@/components/account/wishlist-grid";

export default function ProfileWishlistsPage() {
  const t = useTranslations("account.account");

  return (
    <section>
      <h1 className="text-2xl font-semibold text-foreground rtl:normal-case rtl:tracking-normal">
        {t("labels.my_wishlists")}
      </h1>
      <div className="mt-8">
        <WishlistGrid />
      </div>
    </section>
  );
}
