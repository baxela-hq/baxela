"use client";

import { useCallback, useEffect, useState } from "react";
import { useTranslations } from "next-intl";
import { api, ApiError } from "@/lib/api/client";
import { useAuth } from "@/context/auth-context";
import type { ApiOrderReturn } from "@/lib/api/types";

const CANCELLABLE_STATUSES = ["requested", "approved"];

const STATUS_BADGE_CLASSES: Record<string, string> = {
  requested: "bg-amber-100 text-amber-900 dark:bg-amber-900/40 dark:text-amber-200",
  approved: "bg-teal-100 text-teal-900 dark:bg-teal-900/40 dark:text-teal-200",
  rejected: "bg-rose-100 text-rose-900 dark:bg-rose-900/40 dark:text-rose-200",
  shipped: "bg-sky-100 text-sky-900 dark:bg-sky-900/40 dark:text-sky-200",
  received:
    "bg-indigo-100 text-indigo-900 dark:bg-indigo-900/40 dark:text-indigo-200",
  completed:
    "bg-emerald-100 text-emerald-900 dark:bg-emerald-900/40 dark:text-emerald-200",
  cancelled: "bg-muted text-secondary-text",
};

/**
 * The customer's return requests: status timeline, cancellation while
 * still awaiting approval-to-ship, and marking the goods shipped with an
 * optional tracking number. Refunds stay a separate, server-side flow —
 * this page only tracks the physical return.
 */
export default function ReturnsClient() {
  const t = useTranslations("account.returns");
  const tCommon = useTranslations("shared.common");
  const { status, token } = useAuth();

  const [returns, setReturns] = useState<ApiOrderReturn[] | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [pendingId, setPendingId] = useState<number | null>(null);
  const [trackingFor, setTrackingFor] = useState<number | null>(null);
  const [trackingInput, setTrackingInput] = useState("");

  const load = useCallback(async () => {
    if (!token) return;
    try {
      const page = await api.get<{ data: ApiOrderReturn[] }>(
        "/order/user/returns?per_page=50",
        { token },
      );
      setReturns(page.data);
      setError(null);
    } catch (cause) {
      setError(
        cause instanceof ApiError
          ? cause.message
          : tCommon("messages.error.general"),
      );
    }
  }, [token, tCommon]);

  useEffect(() => {
    // fetch-on-auth external sync; setState only runs in the async
    // continuation.
    // eslint-disable-next-line react-hooks/set-state-in-effect
    if (status === "authenticated") void load();
  }, [status, load]);

  const cancelReturn = async (row: ApiOrderReturn) => {
    if (!token) return;
    setPendingId(row.id);
    try {
      await api.patch(`/order/user/returns/${row.id}/cancel`, undefined, {
        token,
      });
      await load();
    } catch (cause) {
      setError(
        cause instanceof ApiError
          ? cause.message
          : tCommon("messages.error.general"),
      );
    } finally {
      setPendingId(null);
    }
  };

  const markShipped = async (row: ApiOrderReturn) => {
    if (!token) return;
    setPendingId(row.id);
    try {
      await api.patch(
        `/order/user/returns/${row.id}/shipped`,
        { tracking_number: trackingInput.trim() || null },
        { token },
      );
      setTrackingFor(null);
      setTrackingInput("");
      await load();
    } catch (cause) {
      setError(
        cause instanceof ApiError
          ? cause.message
          : tCommon("messages.error.general"),
      );
    } finally {
      setPendingId(null);
    }
  };

  if (status === "unauthenticated") return null;

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-foreground rtl:normal-case rtl:tracking-normal">
          {t("title")}
        </h1>
        <p className="mt-2 text-sm text-secondary-text rtl:normal-case rtl:tracking-normal">
          {t("subtitle")}
        </p>
      </div>

      {error ? (
        <p
          role="alert"
          className="text-sm text-red-600 rtl:normal-case rtl:tracking-normal"
        >
          {error}
        </p>
      ) : null}

      {returns === null ? (
        <p className="text-sm text-secondary-text rtl:normal-case rtl:tracking-normal">
          {tCommon("messages.info.loading")}
        </p>
      ) : returns.length === 0 ? (
        <p className="text-sm text-secondary-text rtl:normal-case rtl:tracking-normal">
          {t("empty")}
        </p>
      ) : (
        <ul className="space-y-4">
          {returns.map((row) => {
            const cancellable = CANCELLABLE_STATUSES.includes(row.status);
            const shippable = row.status === "approved";

            return (
              <li
                key={row.id}
                className="rounded-default border border-border-light p-6"
              >
                <div className="flex flex-wrap items-center justify-between gap-x-4 gap-y-2">
                  <div className="flex flex-wrap items-center gap-x-3 gap-y-1">
                    <p className="font-mono text-sm font-medium text-foreground">
                      {row.order_code ?? `#${row.order_id}`}
                    </p>
                    <span
                      className={`inline-flex items-center rounded-default px-2.5 py-0.5 text-xs font-medium rtl:normal-case rtl:tracking-normal ${
                        STATUS_BADGE_CLASSES[row.status] ?? ""
                      }`}
                    >
                      {t(`statuses.${row.status}`)}
                    </span>
                  </div>
                  <p className="text-xs text-secondary-text rtl:normal-case rtl:tracking-normal">
                    {new Date(row.created_at ?? "").toLocaleDateString()}
                  </p>
                </div>

                <p className="mt-3 text-sm text-secondary-text rtl:normal-case rtl:tracking-normal">
                  {t(`reasons.${row.reason}`)}
                  {row.note ? ` — ${row.note}` : ""}
                </p>

                {row.tracking_number ? (
                  <p className="mt-2 font-mono text-xs text-secondary-text">
                    {t("tracking")}: {row.tracking_number}
                  </p>
                ) : null}

                {trackingFor === row.id ? (
                  <div className="mt-4 space-y-3">
                    <label className="block space-y-1">
                      <span className="text-xs text-secondary-text rtl:normal-case rtl:tracking-normal">
                        {t("tracking_placeholder")}
                      </span>
                      <input
                        value={trackingInput}
                        onChange={(event) => setTrackingInput(event.target.value)}
                        className="w-full rounded-default border border-border bg-background px-3 py-2 text-sm text-foreground focus:border-primary focus:outline-none rtl:normal-case rtl:tracking-normal"
                      />
                    </label>
                    <div className="flex flex-wrap gap-3">
                      <button
                        type="button"
                        disabled={pendingId === row.id}
                        onClick={() => markShipped(row)}
                        className="inline-flex h-10 items-center justify-center rounded-default bg-foreground px-5 text-sm font-medium text-background transition-colors hover:opacity-90 disabled:opacity-60 rtl:normal-case rtl:tracking-normal"
                      >
                        {pendingId === row.id
                          ? tCommon("messages.info.loading")
                          : t("actions.confirm_shipped")}
                      </button>
                      <button
                        type="button"
                        onClick={() => setTrackingFor(null)}
                        className="inline-flex h-10 items-center justify-center rounded-default border border-border bg-background px-5 text-sm font-medium text-foreground transition-colors hover:bg-muted rtl:normal-case rtl:tracking-normal"
                      >
                        {t("actions.back")}
                      </button>
                    </div>
                  </div>
                ) : (
                  <div className="mt-4 flex flex-wrap gap-3">
                    {shippable ? (
                      <button
                        type="button"
                        onClick={() => setTrackingFor(row.id)}
                        className="inline-flex h-10 items-center justify-center rounded-default bg-foreground px-5 text-sm font-medium text-background transition-colors hover:opacity-90 rtl:normal-case rtl:tracking-normal"
                      >
                        {t("actions.mark_shipped")}
                      </button>
                    ) : null}
                    {cancellable ? (
                      <button
                        type="button"
                        disabled={pendingId === row.id}
                        onClick={() => cancelReturn(row)}
                        className="inline-flex h-10 items-center justify-center rounded-default border border-border bg-background px-5 text-sm font-medium text-foreground transition-colors hover:bg-muted disabled:opacity-60 rtl:normal-case rtl:tracking-normal"
                      >
                        {t("actions.cancel_return")}
                      </button>
                    ) : null}
                  </div>
                )}
              </li>
            );
          })}
        </ul>
      )}
    </div>
  );
}
