import { getTranslations } from "next-intl/server";
import { AuthShell } from "@/components/auth/auth-shell";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import { LockIcon, MailIcon } from "@/components/ui/icons";
import { Link } from "@/i18n/navigation";

export const metadata = { title: "Login — Baxela Storefront" };

export default async function LoginPage() {
  const [t, tCommon] = await Promise.all([
    getTranslations("auth.auth"),
    getTranslations("shared.common"),
  ]);

  return (
    <AuthShell image="/images/auth-photo.jpg">
      <h1 className="text-2xl font-semibold text-foreground rtl:normal-case rtl:tracking-normal">
        {t("login.texts.title")}
      </h1>
      <form className="mt-10 flex flex-col gap-6" action="/login" method="post">
        <Input
          type="email"
          label={tCommon("form.labels.email")}
          placeholder={tCommon("form.placeholders.email")}
          icon={<MailIcon />}
        />
        <Input
          type="password"
          label={tCommon("form.labels.password")}
          placeholder={tCommon("form.placeholders.password")}
          icon={<LockIcon />}
        />
        <div className="flex items-center justify-between">
          <Checkbox label={t("login.labels.remember_me")} />
          <Link
            href="/forgot-password"
            className="text-sm text-secondary-text underline underline-offset-2 hover:text-foreground rtl:normal-case rtl:tracking-normal"
          >
            {t("login.actions.forgot_password")}
          </Link>
        </div>
        <Button type="submit">{t("login.actions.submit")}</Button>
      </form>
      <p className="mt-8 text-center text-sm text-secondary-text rtl:normal-case rtl:tracking-normal">
        {t("login.texts.no_account")}{" "}
        <Link
          href="/signup"
          className="font-medium text-accent hover:underline rtl:normal-case rtl:tracking-normal"
        >
          {t("login.actions.signup_link")}
        </Link>
      </p>
    </AuthShell>
  );
}
