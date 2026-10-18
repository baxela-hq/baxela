"use client";

import { useEffect, useMemo, useState } from "react";
import { useFormatter, useTranslations } from "next-intl";
import { useAuth } from "@/context/auth-context";
import { api, ApiError } from "@/lib/api/client";
import { sanitizeHtml } from "@/lib/sanitize-html";
import type {
  ApiCommentReactionResult,
  ApiMyCommentReaction,
  ApiProductAttribute,
  ApiProductComment,
} from "@/lib/api/types";
import { Button } from "@/components/ui/button";
import { StarRating, StarRatingInput } from "@/components/star-rating";
import { ThumbsDownIcon, ThumbsUpIcon } from "@/components/ui/icons";
import { Link, useRouter } from "@/i18n/navigation";
import { cn } from "@/lib/utils";

type TabKey = "description" | "specifications" | "reviews";

type Reaction = "like" | "dislike";

type ReactionTally = { likes: number; dislikes: number };

function tallyOf(comment: ApiProductComment): ReactionTally {
  return { likes: comment.likes_count ?? 0, dislikes: comment.dislikes_count ?? 0 };
}

function ReactionButtons({
  commentId,
  myReaction,
  tally,
  disabled,
  onReact,
  t,
}: {
  commentId: number;
  myReaction: Reaction | null;
  tally: ReactionTally;
  disabled: boolean;
  onReact: (commentId: number, reaction: Reaction) => void;
  t: ReturnType<typeof useTranslations>;
}) {
  const base =
    "inline-flex items-center gap-1.5 rounded-default px-2 py-1 text-xs transition-colors disabled:pointer-events-none disabled:opacity-50";

  return (
    <div className="flex items-center gap-2">
      <button
        type="button"
        disabled={disabled}
        aria-pressed={myReaction === "like"}
        aria-label={t("reviews.actions.like")}
        onClick={() => onReact(commentId, "like")}
        className={cn(
          base,
          myReaction === "like"
            ? "bg-accent/10 text-accent"
            : "text-secondary-text hover:text-foreground",
        )}
      >
        <ThumbsUpIcon className="size-3.5" />
        <span className="tabular-nums">{tally.likes}</span>
      </button>
      <button
        type="button"
        disabled={disabled}
        aria-pressed={myReaction === "dislike"}
        aria-label={t("reviews.actions.dislike")}
        onClick={() => onReact(commentId, "dislike")}
        className={cn(
          base,
          myReaction === "dislike"
            ? "bg-accent/10 text-accent"
            : "text-secondary-text hover:text-foreground",
        )}
      >
        <ThumbsDownIcon className="size-3.5" />
        <span className="tabular-nums">{tally.dislikes}</span>
      </button>
    </div>
  );
}

function CommentItem({
  comment,
  formatDate,
  t,
  myReactions,
  tallies,
  reactionsDisabled,
  onReact,
}: {
  comment: ApiProductComment;
  formatDate: (iso: string) => string;
  t: ReturnType<typeof useTranslations>;
  myReactions: Record<number, Reaction>;
  tallies: Record<number, ReactionTally>;
  reactionsDisabled: boolean;
  onReact: (commentId: number, reaction: Reaction) => void;
}) {
  const tally = tallies[comment.id] ?? tallyOf(comment);

  return (
    <li className="rounded-default border border-border-light p-6">
      <div className="flex flex-wrap items-center justify-between gap-x-4 gap-y-2">
        <div className="flex flex-wrap items-center gap-x-3 gap-y-1">
          <p className="text-sm font-medium text-foreground rtl:normal-case rtl:tracking-normal">
            {comment.user && typeof comment.user === "object" && "name" in comment.user
              ? String(comment.user.name)
              : t("reviews.texts.anonymous")}
          </p>
          {comment.rating !== null ? <StarRating rating={comment.rating} /> : null}
        </div>
        <p className="text-xs text-secondary-text rtl:normal-case rtl:tracking-normal">
          {formatDate(comment.created_at)}
        </p>
      </div>
      <p className="mt-3 text-sm leading-6 text-secondary-text rtl:normal-case rtl:tracking-normal">
        {comment.body}
      </p>
      <div className="mt-4">
        <ReactionButtons
          commentId={comment.id}
          myReaction={myReactions[comment.id] ?? null}
          tally={tally}
          disabled={reactionsDisabled}
          onReact={onReact}
          t={t}
        />
      </div>
      {comment.replies.length > 0 ? (
        <ul className="mt-4 space-y-4 border-t border-border-light pt-4">
          {comment.replies.map((reply) => {
            const replyTally = tallies[reply.id] ?? tallyOf(reply);

            return (
              <li key={reply.id} className="rounded-default bg-muted p-4">
                <div className="flex flex-wrap items-center justify-between gap-x-4 gap-y-2">
                  <p className="text-sm font-medium text-foreground rtl:normal-case rtl:tracking-normal">
                    {reply.user && typeof reply.user === "object" && "name" in reply.user
                      ? String(reply.user.name)
                      : t("reviews.texts.anonymous")}
                  </p>
                  <p className="text-xs text-secondary-text rtl:normal-case rtl:tracking-normal">
                    {formatDate(reply.created_at)}
                  </p>
                </div>
                <p className="mt-2 text-sm leading-6 text-secondary-text rtl:normal-case rtl:tracking-normal">
                  {reply.body}
                </p>
                <div className="mt-3">
                  <ReactionButtons
                    commentId={reply.id}
                    myReaction={myReactions[reply.id] ?? null}
                    tally={replyTally}
                    disabled={reactionsDisabled}
                    onReact={onReact}
                    t={t}
                  />
                </div>
              </li>
            );
          })}
        </ul>
      ) : null}
    </li>
  );
}

/**
 * Product description / specifications / reviews tabs. Attributes and
 * comments come from the public endpoints; reviews carry a required 1–5
 * star rating, go through admin approval before they appear, and every
 * comment (reviews and replies) accepts an authenticated like/dislike.
 */
export function ProductTabs({
  productId,
  content,
  attributes,
  comments,
  commentsTotal,
  ratingAvg,
  ratingCount,
}: {
  productId: number;
  content: string | null;
  attributes: ApiProductAttribute[];
  comments: ApiProductComment[];
  commentsTotal: number;
  ratingAvg: string | null;
  ratingCount: number;
}) {
  const [tab, setTab] = useState<TabKey>("description");
  const [body, setBody] = useState("");
  const [rating, setRating] = useState(0);
  const [pending, setPending] = useState(false);
  const [notice, setNotice] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [reactionRows, setReactionRows] = useState<ApiMyCommentReaction[]>([]);
  const [tallies, setTallies] = useState<Record<number, ReactionTally>>({});

  const t = useTranslations("catalog.product");
  const tCommon = useTranslations("shared.common");
  const format = useFormatter();
  const { status, token } = useAuth();
  const router = useRouter();

  const formatDate = (iso: string) =>
    format.dateTime(new Date(iso), { dateStyle: "medium" });

  // The public thread payload is unauthenticated, so the signed-in user's
  // own reactions are fetched separately to render the pressed state.
  // Toggling below patches the rows so the map stays fresh without refetch.
  useEffect(() => {
    if (status !== "authenticated" || !token) {
      return;
    }

    let cancelled = false;
    api
      .get<ApiMyCommentReaction[]>(
        `/catalog/user/products/${productId}/comment-reactions`,
        { token },
      )
      .then((rows) => {
        if (!cancelled) {
          setReactionRows(rows);
        }
      })
      .catch(() => {
        // best effort — counts still render, just without the pressed state
      });

    return () => {
      cancelled = true;
    };
  }, [status, token, productId]);

  const myReactions = useMemo(() => {
    if (status !== "authenticated") {
      return {};
    }

    const map: Record<number, Reaction> = {};
    for (const row of reactionRows) {
      map[row.comment_id] = row.reaction;
    }
    return map;
  }, [reactionRows, status]);

  // Titles/values arrive localized from the backend; boolean rows have no
  // server-side wording, so they are mapped to locale strings here.
  const specs = attributes.filter((attribute) => attribute.value !== null);

  const TABS: { key: TabKey; label: string }[] = [
    { key: "description", label: t("tabs.labels.description") },
    ...(specs.length > 0
      ? [{ key: "specifications" as const, label: t("tabs.labels.specifications") }]
      : []),
    { key: "reviews", label: t("tabs.labels.reviews", { count: commentsTotal }) },
  ];

  const onReact = async (commentId: number, reaction: Reaction) => {
    if (status !== "authenticated" || !token) {
      router.replace(`/login?next=/products/${productId}`);
      return;
    }

    try {
      const result = await api.post<ApiCommentReactionResult>(
        `/catalog/user/product-comments/${commentId}/reaction`,
        { reaction },
        { token },
      );

      setReactionRows((previous) => {
        const next = previous.filter((row) => row.comment_id !== commentId);
        if (result.reaction !== null) {
          next.push({ comment_id: commentId, reaction: result.reaction });
        }
        return next;
      });
      setTallies((previous) => ({
        ...previous,
        [commentId]: { likes: result.likes_count, dislikes: result.dislikes_count },
      }));
    } catch (cause) {
      setError(
        cause instanceof ApiError ? cause.message : tCommon("messages.error.general"),
      );
    }
  };

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
        { body: body.trim(), rating },
        { token },
      );
      setBody("");
      setRating(0);
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
              // Backend-authored rich content (admin CMS / translations),
              // sanitized before rendering to prevent stored XSS.
              <div dangerouslySetInnerHTML={{ __html: sanitizeHtml(content) }} />
            ) : (
              <p>{t("tabs.texts.no_description")}</p>
            )}
          </div>
        ) : null}

        {tab === "specifications" ? (
          <dl className="max-w-3xl">
            {specs.map((attribute) => (
              <div
                key={attribute.id}
                className="flex flex-wrap items-baseline justify-between gap-x-8 gap-y-1 border-b border-border-light py-3"
              >
                <dt className="text-sm text-secondary-text rtl:normal-case rtl:tracking-normal">
                  {attribute.title ?? attribute.code}
                </dt>
                <dd className="text-sm font-medium text-foreground rtl:normal-case rtl:tracking-normal">
                  {typeof attribute.value === "boolean"
                    ? attribute.value
                      ? t("tabs.texts.yes")
                      : t("tabs.texts.no")
                    : String(attribute.value)}
                </dd>
              </div>
            ))}
          </dl>
        ) : null}

        {tab === "reviews" ? (
          <div className="grid grid-cols-1 gap-12 lg:grid-cols-[320px_1fr]">
            <div>
              <p className="text-5xl font-bold text-foreground rtl:normal-case rtl:tracking-normal">
                {ratingCount > 0
                  ? format.number(Number(ratingAvg), {
                      minimumFractionDigits: 1,
                      maximumFractionDigits: 1,
                    })
                  : "—"}
              </p>
              <StarRating rating={ratingAvg} size="size-5" className="mt-3" />
              <p className="mt-2 text-sm text-secondary-text rtl:normal-case rtl:tracking-normal">
                {t("reviews.texts.based_on", { count: ratingCount })}
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
                      myReactions={myReactions}
                      tallies={tallies}
                      reactionsDisabled={status !== "authenticated"}
                      onReact={onReact}
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
                    <div className="mt-4">
                      <StarRatingInput value={rating} onChange={setRating} disabled={pending} />
                    </div>
                    <textarea
                      rows={4}
                      required
                      value={body}
                      onChange={(event) => setBody(event.target.value)}
                      placeholder={t("reviews.form.placeholders.review")}
                      aria-label={t("reviews.form.labels.review")}
                      className="mt-4 w-full rounded-default border border-border bg-background px-4 py-3 text-sm text-foreground placeholder:text-secondary-text focus:border-primary focus:outline-none"
                    />
                    <Button
                      type="submit"
                      className="mt-6"
                      disabled={pending || rating === 0}
                    >
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
