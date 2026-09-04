import { getLocale } from "next-intl/server";
import { redirect } from "@/i18n/navigation";

// The account hub lives on the orders tab (the default section); `/profile`
// just forwards there. The shared shell (guard, profile, sidebar) is in
// profile/layout.tsx.
export default async function ProfilePage() {
  const locale = await getLocale();

  redirect({ href: "/profile/orders", locale });
}
