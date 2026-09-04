"use client";

import { useState, type FormEvent } from "react";
import { useTranslations } from "next-intl";
import { toast } from "sonner";
import { api, ApiError } from "@/lib/api/client";
import { AuthShell } from "@/components/auth/auth-shell";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { LockIcon, MailIcon, UserIcon } from "@/components/ui/icons";
import { Link, useRouter } from "@/i18n/navigation";

export default function SignupPage() {
  const t = useTranslations("auth.auth");
  const tCommon = useTranslations("shared.common");
  const router = useRouter();

  const [name, setName] = useState("");
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [confirmation, setConfirmation] = useState("");
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const onSubmit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    if (password !== confirmation) {
      setError(tCommon("form.validation.password_mismatch"));
      return;
    }

    setPending(true);
    setError(null);
    try {
      await api.post("/auth/public/auth/signup", {
        email,
        password,
        password_confirmation: confirmation,
      });
      // Activation OTP was emailed — continue to verification
      toast.success(t("signup.messages.success.signed_up"));
      router.replace(
        `/enter-otp?mode=activation&email=${encodeURIComponent(email)}`,
      );
    } catch (cause) {
      if (cause instanceof ApiError && cause.errors) {
        const first = Object.values(cause.errors).flat()[0];
        setError(first ?? cause.message);
      } else {
        setError(
          cause instanceof ApiError
            ? cause.message
            : tCommon("messages.error.general"),
        );
      }
    } finally {
      setPending(false);
    }
  };

  return (
    <AuthShell image="/images/auth-photo.jpg">
      <h1 className="text-2xl font-semibold text-foreground rtl:normal-case rtl:tracking-normal">
        {t("signup.texts.title")}
      </h1>
      <form className="mt-10 flex flex-col gap-6" onSubmit={onSubmit}>
        <Input
          required
          label={t("signup.labels.name")}
          placeholder={t("signup.placeholders.name")}
          icon={<UserIcon />}
          value={name}
          onChange={(event) => setName(event.target.value)}
        />
        <Input
          type="email"
          required
          label={tCommon("form.labels.email")}
          placeholder={tCommon("form.placeholders.email")}
          icon={<MailIcon />}
          value={email}
          onChange={(event) => setEmail(event.target.value)}
        />
        <Input
          type="password"
          required
          minLength={8}
          label={tCommon("form.labels.password")}
          placeholder={tCommon("form.placeholders.password")}
          icon={<LockIcon />}
          value={password}
          onChange={(event) => setPassword(event.target.value)}
        />
        <Input
          type="password"
          required
          minLength={8}
          label={t("signup.labels.confirm_password")}
          placeholder={t("signup.placeholders.confirm_password")}
          icon={<LockIcon />}
          value={confirmation}
          onChange={(event) => setConfirmation(event.target.value)}
        />
        {error ? (
          <p
            role="alert"
            className="text-sm text-red-600 rtl:normal-case rtl:tracking-normal"
          >
            {error}
          </p>
        ) : null}
        <Button type="submit" disabled={pending}>
          {pending
            ? tCommon("messages.info.loading")
            : t("signup.actions.submit")}
        </Button>
      </form>
      <p className="mt-6 text-center text-sm text-secondary-text rtl:normal-case rtl:tracking-normal">
        {t("signup.texts.agree_intro")}{" "}
        <Link
          href="/"
          className="text-accent hover:underline rtl:normal-case rtl:tracking-normal"
        >
          {t("signup.links.terms_of_service")}
        </Link>{" "}
        {t("signup.texts.agree_join")}{" "}
        <Link
          href="/"
          className="text-accent hover:underline rtl:normal-case rtl:tracking-normal"
        >
          {t("signup.links.privacy_policy")}
        </Link>
      </p>
      <p className="mt-6 text-center text-sm text-secondary-text rtl:normal-case rtl:tracking-normal">
        {t("signup.texts.already_have_account")}{" "}
        <Link
          href="/login"
          className="font-medium text-accent hover:underline rtl:normal-case rtl:tracking-normal"
        >
          {t("signup.links.login")}
        </Link>
      </p>
    </AuthShell>
  );
}
