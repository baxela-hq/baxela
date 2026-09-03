"use client";

import { useState } from "react";
import { useFormatter, useTranslations } from "next-intl";
import { useAuth } from "@/context/auth-context";
import { api, ApiError } from "@/lib/api/client";
import type { ApiProductComment } from "@/lib/api/types";
import { Button } from "@/components/ui/button";
import { Link, useRouter } from "@/i18n/navigation";

type TabKey = "description" | "reviews";

function CommentItem({
  comment,
  formatDate,
  t,
}: {
  comment: ApiProductComment;
  formatDate: (iso: string) => string;
  t: ReturnType<typeof useTranslations>;
}) {
  return (
    <li className="rounded-default border border-border-light p-6">
      <div className="flex items-center justify-between gap-4">
        <p className="text-sm font-medium text-foreground rtl:normal-case rtl:tracking-normal">
          {comment.user && typeof comment.user === "object" && "name" in comment.user
            ? String(comment.user.name)
            : t("reviews.texts.anonymous")}
        </p>
        <p className="text-xs text-secondary-text rtl:normal-case rtl:tracking-normal">
          {formatDate(comment.created_at)}
        </p>
      </div>
      <p className="mt-3 text-sm leading-6 text-secondary-text rtl:normal-case rtl:tracking-normal">
        {comment.body}
      </p>
      {comment.replies.length > 0 ? (
        <ul className="mt-4 space-y-4 border-t border-border-light pt-4">
          {comment.replies.map((reply) => (
            <li key={reply.id} className="rounded-default bg-muted p-4">
              <p className="text-sm font-medium text-foreground rtl:normal-case rtl:tracking-normal">
                {reply.user && typeof reply.user === "object" && "name" in reply.user
                  ? String(reply.user.name)
                  : t("reviews.texts.anonymous")}
              </p>
              <p className="mt-2 text-sm leading-6 text-secondary-text rtl:normal-case rtl:tracking-normal">
                {reply.body}
              </p>
            </li>
          ))}
        </ul>
      ) : null}
    </li>
  );
}

/**
 * Product description + reviews tabs. Comments come from the public
 * comments endpoint (moderated server-side); writing one requires an
 * account and goes through admin approval before it appears.
 */
export function ProductTabs({
  productId,
  content,
  comments,
  commentsTotal,
}: {
  productId: number;
  content: string | null;
  comments: ApiProductComment[];
  commentsTotal: number;
}) {
  const [tab, setTab] = useState<TabKey>("description");
  const [body, setBody] = useState("");
  const [pending, setPending] = useState(false);
  const [notice, setNotice] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  const t = useTranslations("catalog.product");
  const tCommon = useTranslations("shared.common");
  const format = useFormatter();
  const { status, token } = useAuth();
  const router = useRouter();

  const formatDate = (iso: string) =>
    format.dateTime(new Date(iso), { dateStyle: "medium" });

  const TABS: { key: TabKey; label: string }[] = [
    { key: "description", label: t("tabs.labels.description") },
    { key: "reviews", label: t("tabs.labels.reviews", { count: commentsTotal }) },
  ];

  const onSubmit = async (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    if (!body.trim()) return;

    if (status !== "authenticated" || !token) {
      router.replace(`/login?next=/products/${productId}`);
      return;
    }

    setPending(true);
    setError(null);
    setNotice(null);
    try {
      await api.post(
        `/catalog/user/products/${productId}/comments`,
        { body: body.trim() },
        { token },
      );
      setBody("");
      setNotice(t("reviews.form.messages.success.submitted"));
    } catch (cause) {
      setError(
        cause instanceof ApiError ? cause.message : tCommon("messages.error.general"),
      );
    } finally {
      setPending(false);
    }
  };

  return (
    <section className="mx-auto max-w-7xl px-6 pb-12">
      <div
        role="tablist"
        aria-label={t("tabs.labels.group")}
        className="flex flex-wrap items-center gap-6 border-b border-border-light"
      >
        {TABS.map(({ key, label }) => (
          <button
            key={key}
            type="button"
            role="tab"
            aria-selected={tab === key}
            onClick={() => setTab(key)}
            className={`-mb-px border-b-2 pb-4 text-sm font-medium transition-colors rtl:normal-case rtl:tracking-normal ${
              tab === key
                ? "border-primary text-foreground"
                : "border-transparent text-secondary-text hover:text-foreground"
            }`}
          >
            {label}
          </button>
        ))}
      </div>

      <div role="tabpanel" className="pt-8">
        {tab === "description" ? (
          <div className="max-w-3xl space-y-4 text-sm leading-6 text-secondary-text rtl:normal-case rtl:tracking-normal">
            {content ? (
              // Backend-authored rich content (admin CMS / translations)
              <div dangerouslySetInnerHTML={{ __html: content }} />
            ) : (
              <p>{t("tabs.texts.no_description")}</p>
            )}
          </div>
        ) : null}

        {tab === "reviews" ? (
          <div className="grid grid-cols-1 gap-12 lg:grid-cols-[320px_1fr]">
            <div>
              <p className="text-5xl font-bold text-foreground rtl:normal-case rtl:tracking-normal">
                {commentsTotal.toLocaleString()}
              </p>
              <p className="mt-2 text-sm text-secondary-text rtl:normal-case rtl:tracking-normal">
                {t("reviews.texts.based_on", { count: commentsTotal })}
              </p>
            </div>

            <div>
              {comments.length > 0 ? (
                <ul className="space-y-6">
                  {comments.map((comment) => (
                    <CommentItem
                      key={comment.id}
                      comment={comment}
                      formatDate={formatDate}
                      t={t}
                    />
                  ))}
                </ul>
              ) : (
                <p className="text-sm text-secondary-text rtl:normal-case rtl:tracking-normal">
                  {t("reviews.texts.empty")}
                </p>
              )}

              <form className="mt-10 max-w-xl" onSubmit={onSubmit}>
                <h3 className="text-lg font-semibold text-foreground rtl:normal-case rtl:tracking-normal">
                  {t("reviews.form.texts.title")}
                </h3>

                {status === "authenticated" ? (
                  <>
                    <textarea
                      rows={4}
                      required
                      value={body}
                      onChange={(event) => setBody(event.target.value)}
                      placeholder={t("reviews.form.placeholders.review")}
                      aria-label={t("reviews.form.labels.review")}
                      className="mt-4 w-full rounded-default border border-border bg-white px-4 py-3 text-sm text-foreground placeholder:text-secondary-text focus:border-primary focus:outline-none"
                    />
                    <Button type="submit" className="mt-6" disabled={pending}>
                      {pending
                        ? tCommon("messages.info.loading")
                        : t("reviews.form.actions.submit")}
                    </Button>
                  </>
                ) : (
                  <p className="mt-4 text-sm text-secondary-text rtl:normal-case rtl:tracking-normal">
                    {t("reviews.form.messages.info.login_required")}{" "}
                    <Link
                      href={`/login?next=/products/${productId}`}
                      className="font-medium text-accent hover:underline"
                    >
                      {t("reviews.form.messages.info.login_link")}
                    </Link>
                  </p>
                )}

                {notice ? (
                  <p
                    role="status"
                    className="mt-4 text-sm text-accent rtl:normal-case rtl:tracking-normal"
                  >
                    {notice}
                  </p>
                ) : null}
                {error ? (
                  <p
                    role="alert"
                    className="mt-4 text-sm text-red-600 rtl:normal-case rtl:tracking-normal"
                  >
                    {error}
                  </p>
                ) : null}
              </form>
            </div>
          </div>
        ) : null}
      </div>
    </section>
  );
}
