import { getTranslations } from "next-intl/server";
import { AuthShell } from "@/components/auth/auth-shell";
import { Button } from "@/components/ui/button";

export const metadata = { title: "Login Successful — Baxela Storefront" };

// NOTE: Right-panel copy is unverified (Figma capture for screen 05 was
// partially recorded). Skeleton below flags the unconfirmed text.
export default async function LoginSuccessfulPage() {
  const t = await getTranslations("auth.auth");

  return (
    <AuthShell image="/images/auth-success.jpg">
      <div className="flex flex-col items-center py-24 text-center">
        <h1 className="text-2xl font-semibold text-foreground rtl:normal-case rtl:tracking-normal">
          {t("login_successful.texts.title")}
        </h1>
        <p className="mt-3 text-sm text-secondary-text rtl:normal-case rtl:tracking-normal">
          {t("login_successful.texts.description")}
        </p>
        <Button className="mt-10" onClick={undefined} /* TODO: wire to home route */>
          {t("login_successful.actions.continue_shopping")}
        </Button>
      </div>
    </AuthShell>
  );
}
