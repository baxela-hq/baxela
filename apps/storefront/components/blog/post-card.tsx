import { useFormatter } from "next-intl";

import { cn } from "@/lib/utils";
import { Link } from "@/i18n/navigation";
import type { ApiPost } from "@/lib/api/types";

export interface PostCardProps {
  post: ApiPost;
  className?: string;
}

/** Blog card for the list page: cover image, title, publish date, excerpt. */
export default function PostCard({ post, className }: PostCardProps) {
  const format = useFormatter();

  // The admin keeps positions ordered (first = featured photo).
  const cover = [...(post.images ?? [])]
    .sort((a, b) => (a.position ?? 0) - (b.position ?? 0))
    .at(0);
  const date = post.published_at ?? post.created_at;

  return (
    <Link
      href={`/blog/${post.slug ?? post.id}`}
      className={cn("group block", className)}
    >
      <div className="flex aspect-video w-full items-center justify-center overflow-hidden rounded-default bg-muted transition-colors group-hover:bg-border-light">
        {cover ? (
          // Backend-served images from arbitrary hosts — next/image would
          // need remotePatterns for every storage host, so use a plain img.
          // eslint-disable-next-line @next/next/no-img-element
          <img
            src={cover.url}
            alt={post.title ?? ""}
            className="size-full object-cover"
            loading="lazy"
          />
        ) : (
          <span className="px-4 text-center text-sm text-secondary-text rtl:normal-case rtl:tracking-normal">
            {post.title}
          </span>
        )}
      </div>
      <div className="mt-4">
        {post.title ? (
          <h3 className="text-base font-medium text-foreground rtl:normal-case rtl:tracking-normal">
            {post.title}
          </h3>
        ) : null}
        <p className="mt-1 text-xs text-secondary-text rtl:normal-case rtl:tracking-normal">
          {format.dateTime(new Date(date), { dateStyle: "medium" })}
        </p>
        {post.description ? (
          <p className="mt-2 line-clamp-2 text-sm leading-6 text-secondary-text rtl:normal-case rtl:tracking-normal">
            {post.description}
          </p>
        ) : null}
      </div>
    </Link>
  );
}
