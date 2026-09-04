"use client";

import { useState, type FormEvent } from "react";
import { useTranslations } from "next-intl";
import { toast } from "sonner";
import { api, ApiError } from "@/lib/api/client";
import { AuthShell } from "@/components/auth/auth-shell";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Logo } from "@/components/ui/logo";
import { MailIcon, ArrowLeftIcon } from "@/components/ui/icons";
import { Link, useRouter } from "@/i18n/navigation";

export default function ForgotPasswordPage() {
  const t = useTranslations("auth.auth");
  const tCommon = useTranslations("shared.common");
  const router = useRouter();

  const [email, setEmail] = useState("");
  const [pending, setPending] = useState(false);

  const onSubmit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setPending(true);
    try {
      await api.post("/auth/public/auth/reset-password/request", { email });
      toast.success(t("forgot_password.messages.success.code_sent"));
      router.replace(
        `/enter-otp?mode=reset&email=${encodeURIComponent(email)}`,
      );
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

  return (
    <AuthShell>
      <div className="flex flex-col items-center">
        <Link
          href="/login"
          className="self-start mb-10 inline-flex items-center gap-2 text-sm font-medium text-secondary-text transition-colors hover:text-foreground rtl:normal-case rtl:tracking-normal"
        >
          <ArrowLeftIcon className="size-4 rtl:rotate-180" />
          {tCommon("form.actions.back")}
        </Link>
        <Logo />
        <h1 className="mt-16 text-2xl font-semibold text-foreground rtl:normal-case rtl:tracking-normal">
          {t("forgot_password.texts.title")}
        </h1>
        <p className="mt-3 text-center text-sm text-secondary-text rtl:normal-case rtl:tracking-normal">
          {t("forgot_password.texts.description")}
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
          <Button type="submit" disabled={pending}>
            {pending
              ? tCommon("messages.info.loading")
              : t("forgot_password.actions.submit")}
          </Button>
        </form>
      </div>
    </AuthShell>
  );
}
