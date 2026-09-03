import { getTranslations } from "next-intl/server";
import { AuthShell } from "@/components/auth/auth-shell";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Logo } from "@/components/ui/logo";
import { MailIcon } from "@/components/ui/icons";

export const metadata = { title: "Forgot Password — Baxela Storefront" };

export default async function ForgotPasswordPage() {
  const [t, tCommon] = await Promise.all([
    getTranslations("auth.auth"),
    getTranslations("shared.common"),
  ]);

  return (
    <AuthShell>
      <div className="flex flex-col items-center">
        <Logo />
        <h1 className="mt-16 text-2xl font-semibold text-foreground rtl:normal-case rtl:tracking-normal">
          {t("forgot_password.texts.title")}
        </h1>
        <p className="mt-3 text-center text-sm text-secondary-text rtl:normal-case rtl:tracking-normal">
          {t("forgot_password.texts.description")}
        </p>
        <form
          className="mt-10 flex w-full flex-col gap-6"
          action="/enter-otp"
          method="post"
        >
          <Input
            type="email"
            label={tCommon("form.labels.email")}
            placeholder={tCommon("form.placeholders.email")}
            icon={<MailIcon />}
          />
          <Button type="submit">{t("forgot_password.actions.submit")}</Button>
        </form>
      </div>
    </AuthShell>
  );
}
