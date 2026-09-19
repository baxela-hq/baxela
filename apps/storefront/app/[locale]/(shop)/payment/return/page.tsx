"use client";

import { Suspense, useEffect, useRef, useState } from "react";
import { useSearchParams } from "next/navigation";
import { useTranslations } from "next-intl";
import { useAuth } from "@/context/auth-context";
import { api, ApiError } from "@/lib/api/client";
import type { ApiOrder } from "@/lib/api/types";
import { Link, useRouter } from "@/i18n/navigation";

/** Poll cadence for the order's payment status (webhook may lag the redirect). */
const POLL_INTERVAL_MS = 2_000;
const POLL_ATTEMPTS = 5;

type ReturnState = "verifying" | "paid" | "pending" | "cancelled" | "error";

/**
 * Landing page for hosted-checkout redirects (e.g. Stripe success/cancel
 * URLs). The gateway webhook is the settlement source of truth — this page
 * only observes the order's payment status and reports it.
 */
function PaymentReturnView() {
  const t = useTranslations("payment.return");
  const tCommon = useTranslations("shared.common");
  const { status, token } = useAuth();
  const router = useRouter();
  const searchParams = useSearchParams();

  const orderCode = searchParams.get("order_code");
  const cancelled = searchParams.get("status") === "cancel";

  const [state, setState] = useState<ReturnState>(
    cancelled ? "cancelled" : "verifying",
  );
  const [error, setError] = useState<string | null>(null);
  // Guards the polling effect against a second run in React StrictMode.
  const polled = useRef(false);

  useEffect(() => {
    if (status === "unauthenticated") {
      router.replace("/login?next=/payment/return");
    }
  }, [status, router]);

  useEffect(() => {
    if (status !== "authenticated" || !token) return;
    if (cancelled || polled.current || !orderCode) return;
    polled.current = true;

    let attempt = 0;
    const poll = async () => {
      attempt += 1;
      try {
        const order = await api.get<ApiOrder>(
          `/order/user/orders/${orderCode}`,
          { token },
        );
        if (order.payment_status === "paid") {
          setState("paid");
          return;
        }
      } catch (cause) {
        setError(
          cause instanceof ApiError
            ? cause.message
            : tCommon("messages.error.general"),
        );
        setState("error");
        return;
      }
      if (attempt < POLL_ATTEMPTS) {
        setTimeout(() => void poll(), POLL_INTERVAL_MS);
      } else {
        setState("pending");
      }
    };

    void poll();
  }, [status, token, orderCode, cancelled, tCommon]);

  if (status !== "authenticated" || !token) {
    return (
      <section className="mx-auto max-w-7xl px-6 py-24 text-center">
        <p className="text-base text-secondary-text rtl:normal-case rtl:tracking-normal">
          {tCommon("messages.info.loading")}
        </p>
      </section>
    );
  }

  const heading =
    state === "paid"
      ? t("texts.title_paid")
      : state === "cancelled"
        ? t("texts.title_cancelled")
        : state === "error"
          ? tCommon("messages.error.general")
          : state === "pending"
            ? t("texts.title_pending")
            : t("texts.title_verifying");

  const description =
    state === "paid"
      ? t("texts.description_paid")
      : state === "cancelled"
        ? t("texts.description_cancelled")
        : state === "error"
          ? (error ?? tCommon("messages.error.general"))
          : state === "pending"
            ? t("texts.description_pending")
            : t("texts.description_verifying");

  return (
    <section className="mx-auto max-w-2xl px-6 py-24 text-center">
      <h1 className="text-3xl font-bold text-foreground rtl:normal-case rtl:tracking-normal">
        {heading}
      </h1>
      {orderCode ? (
        <p className="mt-4 text-sm text-secondary-text rtl:normal-case rtl:tracking-normal">
          {t("texts.order_number", { code: orderCode })}
        </p>
      ) : (
        <p className="mt-4 text-sm text-secondary-text rtl:normal-case rtl:tracking-normal">
          {t("texts.error_missing_code")}
        </p>
      )}
      <p className="mt-2 text-sm text-secondary-text rtl:normal-case rtl:tracking-normal">
        {description}
      </p>
      <div className="mt-10 flex items-center justify-center gap-4">
        {orderCode ? (
          <Link
            href="/profile/orders"
            className="inline-flex h-12 items-center justify-center rounded-default border border-border px-8 text-sm font-medium text-foreground transition-colors hover:bg-muted rtl:normal-case rtl:tracking-normal"
          >
            {t("actions.view_orders")}
          </Link>
        ) : null}
        <Link
          href="/products"
          className="inline-flex h-12 items-center justify-center rounded-default bg-primary px-8 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90 rtl:normal-case rtl:tracking-normal"
        >
          {t("actions.continue_shopping")}
        </Link>
      </div>
    </section>
  );
}

export default function PaymentReturnPage() {
  return (
    <Suspense>
      <PaymentReturnView />
    </Suspense>
  );
}
