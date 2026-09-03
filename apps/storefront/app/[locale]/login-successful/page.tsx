"use client";

import { useEffect } from "react";
import { useTranslations } from "next-intl";
import { useAuth } from "@/context/auth-context";
import { AuthShell } from "@/components/auth/auth-shell";
import { Button } from "@/components/ui/button";
import { useRouter } from "@/i18n/navigation";

export default function LoginSuccessfulPage() {
  const t = useTranslations("auth.auth");
  const { status, user, signOut } = useAuth();
  const router = useRouter();

  // Landing here signed out (e.g. reload after the session was dropped)
  // sends the visitor back to the form.
  useEffect(() => {
    if (status === "unauthenticated") {
      router.replace("/login");
    }
  }, [status, router]);

  return (
    <AuthShell image="/images/auth-success.jpg">
      <div className="flex flex-col items-center py-24 text-center">
        <h1 className="text-2xl font-semibold text-foreground rtl:normal-case rtl:tracking-normal">
          {t("login_successful.texts.title")}
        </h1>
        <p className="mt-3 text-sm text-secondary-text rtl:normal-case rtl:tracking-normal">
          {status === "authenticated" && user
            ? t("login_successful.texts.signed_in_as", { email: user.email })
            : t("login_successful.texts.description")}
        </p>
        <div className="mt-10 flex flex-wrap items-center justify-center gap-4">
          <Button onClick={() => router.replace("/")}>
            {t("login_successful.actions.continue_shopping")}
          </Button>
          <Button variant="outline" onClick={signOut}>
            {t("login_successful.actions.sign_out")}
          </Button>
        </div>
      </div>
    </AuthShell>
  );
}
