"use client";

import { useState } from "react";
import { useFormatter, useTranslations } from "next-intl";
import { useAuth } from "@/context/auth-context";
import { api, ApiError } from "@/lib/api/client";
import type { ApiPostComment } from "@/lib/api/types";
import { Button } from "@/components/ui/button";
import { Link, useRouter } from "@/i18n/navigation";

function CommentItem({
  comment,
  formatDate,
  t,
}: {
  comment: ApiPostComment;
  formatDate: (iso: string) => string;
  t: ReturnType<typeof useTranslations>;
}) {
  return (
    <li className="rounded-default border border-border-light p-6">
      <div className="flex items-center justify-between gap-4">
        <p className="text-sm font-medium text-foreground rtl:normal-case rtl:tracking-normal">
          {comment.user?.name ?? t("texts.anonymous")}
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
                {reply.user?.name ?? t("texts.anonymous")}
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
 * Approved comments for a blog post plus the submit form. Comments require
 * an account to write and go through admin approval before they appear.
 */
export function PostComments({
  postId,
  postPath,
  comments,
  commentsTotal,
}: {
  postId: number;
  postPath: string;
  comments: ApiPostComment[];
  commentsTotal: number;
}) {
  const [body, setBody] = useState("");
  const [pending, setPending] = useState(false);
  const [notice, setNotice] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  const t = useTranslations("blog.post");
  const tCommon = useTranslations("shared.common");
  const format = useFormatter();
  const { status, token } = useAuth();
  const router = useRouter();

  const formatDate = (iso: string) =>
    format.dateTime(new Date(iso), { dateStyle: "medium" });

  const onSubmit = async (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    if (!body.trim()) return;

    if (status !== "authenticated" || !token) {
      router.replace(`/login?next=${postPath}`);
      return;
    }

    setPending(true);
    setError(null);
    setNotice(null);
    try {
      await api.post(
        `/content/user/posts/${postId}/comments`,
        { body: body.trim() },
        { token },
      );
      setBody("");
      setNotice(t("comments.form.messages.success.submitted"));
    } catch (cause) {
      setError(
        cause instanceof ApiError ? cause.message : tCommon("messages.error.general"),
      );
    } finally {
      setPending(false);
    }
  };

  return (
    <section className="mx-auto max-w-3xl px-6 pb-16">
      <h2 className="text-2xl font-semibold text-foreground rtl:normal-case rtl:tracking-normal">
        {t("comments.title")}
        <span className="ms-2 text-base font-normal text-secondary-text rtl:normal-case rtl:tracking-normal">
          {t("texts.comments_count", { count: commentsTotal })}
        </span>
      </h2>

      {comments.length > 0 ? (
        <ul className="mt-8 space-y-6">
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
        <p className="mt-8 text-sm text-secondary-text rtl:normal-case rtl:tracking-normal">
          {t("texts.empty_comments")}
        </p>
      )}

      <form className="mt-10 max-w-xl" onSubmit={onSubmit}>
        <h3 className="text-lg font-semibold text-foreground rtl:normal-case rtl:tracking-normal">
          {t("comments.form.texts.title")}
        </h3>

        {status === "authenticated" ? (
          <>
            <textarea
              rows={4}
              required
              value={body}
              onChange={(event) => setBody(event.target.value)}
              placeholder={t("comments.form.placeholders.comment")}
              aria-label={t("comments.form.labels.comment")}
              className="mt-4 w-full rounded-default border border-border bg-background px-4 py-3 text-sm text-foreground placeholder:text-secondary-text focus:border-primary focus:outline-none"
            />
            <Button type="submit" className="mt-6" disabled={pending}>
              {pending
                ? tCommon("messages.info.loading")
                : t("comments.form.actions.submit")}
            </Button>
          </>
        ) : (
          <p className="mt-4 text-sm text-secondary-text rtl:normal-case rtl:tracking-normal">
            {t("comments.form.messages.info.login_required")}{" "}
            <Link
              href={`/login?next=${postPath}`}
              className="font-medium text-accent hover:underline"
            >
              {t("comments.form.messages.info.login_link")}
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
    </section>
  );
}
