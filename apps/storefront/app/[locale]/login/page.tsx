"use client";

import { Suspense, useEffect, useState, type FormEvent } from "react";
import { useTranslations } from "next-intl";
import { toast } from "sonner";
import { useSearchParams } from "next/navigation";
import { useAuth } from "@/context/auth-context";
import { ApiError } from "@/lib/api/client";
import { AuthShell } from "@/components/auth/auth-shell";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";
import { LockIcon, MailIcon } from "@/components/ui/icons";
import { Logo } from "@/components/ui/logo";
import { Link, useRouter } from "@/i18n/navigation";

function LoginForm() {
  const t = useTranslations("auth.auth");
  const tCommon = useTranslations("shared.common");
  const { signIn } = useAuth();
  const router = useRouter();
  const searchParams = useSearchParams();

  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [pending, setPending] = useState(false);

  // The API client's 401 interceptor lands here with session_expired=1
  // after clearing a dead session. Toast once, then strip the flag so a
  // refresh/re-render doesn't repeat it (next is kept for post-login).
  useEffect(() => {
    if (searchParams.get("session_expired") !== "1") return;
    toast.error(t("login.messages.error.session_expired"));
    const params = new URLSearchParams(searchParams);
    params.delete("session_expired");
    const query = params.toString();
    router.replace(query ? `/login?${query}` : "/login");
  }, [searchParams, router, t]);

  const onSubmit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setPending(true);
    try {
      await signIn({ email, password });
      toast.success(t("login.messages.success.signed_in"));
      const next = searchParams.get("next");
      router.replace(next && next.startsWith("/") ? next : "/profile");
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
        <Logo />
        <div className="mt-6 w-full rounded-default border border-border-light bg-white p-6 shadow-sm">
          <h1 className="text-2xl font-semibold text-foreground rtl:normal-case rtl:tracking-normal">
            {t("login.texts.title")}
          </h1>
          <form className="mt-8 flex flex-col gap-6" onSubmit={onSubmit}>
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
            <div className="flex items-center justify-between">
              <Checkbox label={t("login.labels.remember_me")} />
              <Link
                href="/forgot-password"
                className="text-sm text-secondary-text underline underline-offset-2 hover:text-foreground rtl:normal-case rtl:tracking-normal"
              >
                {t("login.actions.forgot_password")}
              </Link>
            </div>
            <Button type="submit" disabled={pending}>
              {pending
                ? tCommon("messages.info.loading")
                : t("login.actions.submit")}
            </Button>
          </form>
          <p className="mt-6 text-center text-sm text-secondary-text rtl:normal-case rtl:tracking-normal">
            {t("login.texts.no_account")}{" "}
            <Link
              href="/signup"
              className="font-medium text-accent hover:underline rtl:normal-case rtl:tracking-normal"
            >
              {t("login.actions.signup_link")}
            </Link>
          </p>
        </div>
      </div>
    </AuthShell>
  );
}

export default function LoginPage() {
  return (
    <Suspense>
      <LoginForm />
    </Suspense>
  );
}
