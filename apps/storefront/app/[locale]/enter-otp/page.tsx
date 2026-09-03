import { getTranslations } from "next-intl/server";
import { AuthShell } from "@/components/auth/auth-shell";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Logo } from "@/components/ui/logo";
import { LockIcon } from "@/components/ui/icons";

export const metadata = { title: "Verify Your Identity — Baxela Storefront" };

export default async function EnterOtpPage() {
  const t = await getTranslations("auth.auth");

  return (
    <AuthShell>
      <div className="flex flex-col items-center">
        <Logo />
        <h1 className="mt-16 text-2xl font-semibold text-foreground rtl:normal-case rtl:tracking-normal">
          {t("enter_otp.texts.title")}
        </h1>
        <p className="mt-3 text-center text-sm text-secondary-text rtl:normal-case rtl:tracking-normal">
          {t("enter_otp.texts.description")}
        </p>
        <form
          className="mt-10 flex w-full flex-col gap-6"
          action="/login"
          method="post"
        >
          <Input
            label={t("enter_otp.labels.code")}
            placeholder={t("enter_otp.placeholders.code")}
            icon={<LockIcon />}
          />
          <button
            type="button"
            className="-mt-2 text-sm font-medium text-accent hover:underline rtl:normal-case rtl:tracking-normal"
          >
            {t("enter_otp.actions.resend")}
          </button>
          <Button type="submit">{t("enter_otp.actions.submit")}</Button>
        </form>
      </div>
    </AuthShell>
  );
}
