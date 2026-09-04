"use client";

import { Suspense, useState, type FormEvent } from "react";
import { useTranslations } from "next-intl";
import { toast } from "sonner";
import { useSearchParams } from "next/navigation";
import { api, ApiError } from "@/lib/api/client";
import { AuthShell } from "@/components/auth/auth-shell";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Logo } from "@/components/ui/logo";
import { ArrowLeftIcon, LockIcon, MailIcon } from "@/components/ui/icons";
import { Link, useRouter } from "@/i18n/navigation";

/**
 * Dual-purpose OTP screen: account activation (email + code) and password
 * reset (email + code + new password), driven by ?mode=activation|reset.
 */
function EnterOtpForm() {
  const t = useTranslations("auth.auth");
  const tCommon = useTranslations("shared.common");
  const router = useRouter();
  const searchParams = useSearchParams();

  const isReset = searchParams.get("mode") === "reset";
  const [email, setEmail] = useState(searchParams.get("email") ?? "");
  const [code, setCode] = useState("");
  const [password, setPassword] = useState("");
  const [confirmation, setConfirmation] = useState("");
  const [pending, setPending] = useState(false);
  const [resending, setResending] = useState(false);

  const onSubmit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    if (isReset && password !== confirmation) {
      toast.error(tCommon("form.validation.password_mismatch"));
      return;
    }

    setPending(true);
    try {
      if (isReset) {
        await api.post("/auth/public/auth/reset-password/verify", {
          email,
          code,
          password,
          password_confirmation: confirmation,
        });
      } else {
        await api.post("/auth/public/auth/account-activation/verify", {
          email,
          code,
        });
      }
      toast.success(
        isReset
          ? t("enter_otp.messages.success.password_reset")
          : t("enter_otp.messages.success.activated"),
      );
      router.replace("/login");
    } catch (cause) {
      toast.error(
        cause instanceof ApiError
          ? cause.message
          : tCommon("messages.error.general"),
      );
    } finally {
      setPending(false);
    }
  };

  const onResend = async () => {
    setResending(true);
    try {
      await api.post(
        isReset
          ? "/auth/public/auth/reset-password/request"
          : "/auth/public/auth/account-activation/request",
        { email },
      );
      toast.success(t("enter_otp.messages.info.resent"));
    } catch (cause) {
      toast.error(
        cause instanceof ApiError
          ? cause.message
          : tCommon("messages.error.general"),
      );
    } finally {
      setResending(false);
    }
  };

  return (
    <AuthShell>
      <div className="flex flex-col items-center">
        {/* The flows land here via router.replace, so the browser back
            button skips the source page — offer an explicit way back. */}
        <Link
          href={isReset ? "/forgot-password" : "/signup"}
          className="self-start mb-10 inline-flex items-center gap-2 text-sm font-medium text-secondary-text transition-colors hover:text-foreground rtl:normal-case rtl:tracking-normal"
        >
          <ArrowLeftIcon className="size-4 rtl:rotate-180" />
          {tCommon("form.actions.back")}
        </Link>
        <Logo />
        <h1 className="mt-16 text-2xl font-semibold text-foreground rtl:normal-case rtl:tracking-normal">
          {t("enter_otp.texts.title")}
        </h1>
        <p className="mt-3 text-center text-sm text-secondary-text rtl:normal-case rtl:tracking-normal">
          {isReset
            ? t("enter_otp.texts.reset_description")
            : t("enter_otp.texts.description")}
        </p>
        <form
          className="mt-10 flex w-full flex-col gap-6"
          onSubmit={onSubmit}
        >
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
            required
            inputMode="numeric"
            maxLength={6}
            label={t("enter_otp.labels.code")}
            placeholder={t("enter_otp.placeholders.code")}
            icon={<LockIcon />}
            value={code}
            onChange={(event) => setCode(event.target.value)}
          />
          {isReset ? (
            <>
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
            </>
          ) : null}

          <button
            type="button"
            disabled={resending || !email}
            onClick={onResend}
            className="-mt-2 text-sm font-medium text-accent hover:underline disabled:opacity-50 rtl:normal-case rtl:tracking-normal"
          >
            {resending
              ? tCommon("messages.info.loading")
              : t("enter_otp.actions.resend")}
          </button>

          <Button type="submit" disabled={pending}>
            {pending
              ? tCommon("messages.info.loading")
              : t("enter_otp.actions.submit")}
          </Button>
        </form>
      </div>
    </AuthShell>
  );
}

export default function EnterOtpPage() {
  return (
    <Suspense>
      <EnterOtpForm />
    </Suspense>
  );
}
