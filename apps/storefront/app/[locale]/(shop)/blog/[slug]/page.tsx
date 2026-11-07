import type { Metadata } from "next";
import { getFormatter, getTranslations } from "next-intl/server";
import { JsonLd } from "@/components/json-ld";
import NotFoundContent from "@/components/not-found-content";
import { PostComments } from "@/components/blog/post-comments";
import ProductCard from "@/components/product-card";
import { serverApiGet } from "@/lib/api/server";
import { sanitizeHtml } from "@/lib/sanitize-html";
import type { ApiPost, ApiPostComment, Paginated } from "@/lib/api/types";
import { Link } from "@/i18n/navigation";
import { canonicalOnlyLocaleUrls } from "@/lib/seo/entity-urls";
import { pageSeo } from "@/lib/seo/metadata";
import { articleLd, webPageLd } from "@/lib/seo/schema-org";
import { absoluteAssetUrl, absoluteUrl, type Locale } from "@/lib/seo/urls";

export async function generateMetadata({
  params,
}: PageProps<"/[locale]/blog/[slug]">): Promise<Metadata> {
  const { slug, locale: localeParam } = await params;
  const locale = localeParam as Locale;
  const post = await serverApiGet<ApiPost>(`/content/public/posts/${slug}`).catch(
    () => null,
  );

  // The missing-post body renders inline (HTTP 200) — keep it out of
  // the index.
  if (!post) {
    return { robots: { index: false, follow: false } };
  }

  // Post slugs are locale-scoped but the show payload carries only the
  // current locale's — v1 emits a self-canonical without alternates
  // rather than asserting URLs that may not resolve (gap-B2 D1 v2).
  return pageSeo({
    title: post.title ? `${post.title} — Blog` : "Blog — Baxela Storefront",
    description: post.description,
    locale,
    localeUrls: canonicalOnlyLocaleUrls("/blog", post.slug ?? slug, locale),
    imageUrl: post.image_url,
    ogType: "article",
  });
}

export default async function BlogPostPage({
  params,
}: PageProps<"/[locale]/blog/[slug]">) {
  const { slug, locale: localeParam } = await params;
  const locale = localeParam as Locale;
  const [tLayout, tRelated, format] = await Promise.all([
    getTranslations("shared.layout"),
    getTranslations("blog.post.related_products"),
    getFormatter(),
  ]);

  const post = await serverApiGet<ApiPost>(`/content/public/posts/${slug}`).catch(
    () => null,
  );
  // Scheduled/draft/unknown slugs 404 at the API; notFound() itself
  // renders blank here, so the 404 body is rendered inline instead.
  if (!post) {
    return <NotFoundContent />;
  }

  const commentsPage = await serverApiGet<Paginated<ApiPostComment>>(
    `/content/public/posts/${post.id}/comments?per_page=10`,
  ).catch(() => null);

  const comments = commentsPage?.data ?? [];
  const commentsTotal = commentsPage?.meta.total ?? 0;
  const date = post.published_at ?? post.created_at;
  const postPath = `/blog/${post.slug ?? post.id}`;
  const postUrl = absoluteUrl(postPath, locale as Locale);

  const postGraph = [
    articleLd({
      url: postUrl,
      name: post.title ?? "Post",
      description: post.description,
      image: absoluteAssetUrl(post.image_url),
      datePublished: post.published_at,
      dateModified: post.updated_at,
    }),
    webPageLd({
      url: postUrl,
      name: post.title ?? "Post",
      mainEntityId: `${postUrl}#webpage`,
    }),
  ];

  return (
    <>
      <JsonLd data={postGraph} />
      <nav
        aria-label={tLayout("breadcrumb.labels.navigation")}
        className="border-b border-border-light bg-muted"
      >
        <ol className="mx-auto flex max-w-7xl items-center gap-2 px-6 py-4 text-sm text-secondary-text">
          <li>
            <Link
              href="/"
              className="transition-colors hover:text-accent rtl:normal-case rtl:tracking-normal"
            >
              {tLayout("breadcrumb.links.home")}
            </Link>
          </li>
          <li aria-hidden="true">/</li>
          <li>
            <Link
              href="/blog"
              className="transition-colors hover:text-accent rtl:normal-case rtl:tracking-normal"
            >
              {tLayout("header.nav.blog")}
            </Link>
          </li>
          <li aria-hidden="true">/</li>
          <li
            aria-current="page"
            className="truncate font-medium text-foreground rtl:normal-case rtl:tracking-normal"
          >
            {post.title}
          </li>
        </ol>
      </nav>

      <article className="mx-auto max-w-3xl px-6 py-16">
        <header>
          {post.categories && post.categories.length > 0 ? (
            <div className="flex flex-wrap items-center gap-2">
              {post.categories.map((category) =>
                category.slug ? (
                  <Link
                    key={category.id}
                    href={`/blog/cat/${encodeURIComponent(category.slug)}`}
                    className="rounded-default bg-muted px-3 py-1 text-xs text-secondary-text transition-colors hover:text-accent rtl:normal-case rtl:tracking-normal"
                  >
                    {category.title}
                  </Link>
                ) : null,
              )}
            </div>
          ) : null}
          <h1 className="mt-4 text-3xl font-semibold text-foreground md:text-4xl rtl:normal-case rtl:tracking-normal">
            {post.title}
          </h1>
          <p className="mt-3 text-sm text-secondary-text rtl:normal-case rtl:tracking-normal">
            {format.dateTime(new Date(date), { dateStyle: "long" })}
          </p>
          {post.image_url ? (
            // Backend-served images from arbitrary hosts — next/image would
            // need remotePatterns for every storage host, so use a plain img.
            // eslint-disable-next-line @next/next/no-img-element
            <img
              src={post.image_url}
              alt={post.title ?? ""}
              className="mt-8 aspect-video w-full rounded-default object-cover"
            />
          ) : null}
        </header>

        {post.content ? (
          // Backend-authored rich content (admin Tiptap editor / translations),
          // sanitized before rendering to prevent stored XSS.
          <div
            className="mt-10 space-y-4 text-base leading-7 text-secondary-text rtl:normal-case rtl:tracking-normal [&_a]:font-medium [&_a]:text-accent [&_a]:underline [&_blockquote]:border-s-4 [&_blockquote]:border-border-light [&_blockquote]:ps-4 [&_h2]:mt-10 [&_h2]:text-2xl [&_h2]:font-semibold [&_h2]:text-foreground [&_h3]:mt-8 [&_h3]:text-xl [&_h3]:font-semibold [&_h3]:text-foreground [&_img]:rounded-default [&_li]:ms-5 [&_li]:list-disc [&_ol_li]:list-decimal [&_p]:leading-7 [&_ul]:space-y-2"
            dangerouslySetInnerHTML={{ __html: sanitizeHtml(post.content) }}
          />
        ) : null}
      </article>

      {post.products && post.products.length > 0 ? (
        <section className="mx-auto max-w-3xl px-6 pb-4">
          <h2 className="text-2xl font-semibold text-foreground rtl:normal-case rtl:tracking-normal">
            {tRelated("title")}
          </h2>
          <div className="mt-6 grid grid-cols-1 gap-x-6 gap-y-10 sm:grid-cols-2">
            {post.products.map((product) => (
              <ProductCard key={product.id} product={product} />
            ))}
          </div>
        </section>
      ) : null}

      <PostComments
        postId={post.id}
        postPath={postPath}
        comments={comments}
        commentsTotal={commentsTotal}
      />
    </>
  );
}
