import type { Metadata } from "next";
import { getTranslations } from "next-intl/server";
import { serverApiGet } from "@/lib/api/server";
import NotFoundContent from "@/components/not-found-content";
import type { ApiPage } from "@/lib/api/types";
import { Link } from "@/i18n/navigation";
import { canonicalOnlyLocaleUrls } from "@/lib/seo/entity-urls";
import { pageSeo } from "@/lib/seo/metadata";
import type { Locale } from "@/lib/seo/urls";

export async function generateMetadata({
  params,
}: PageProps<"/[locale]/pages/[slug]">): Promise<Metadata> {
  const { slug, locale: localeParam } = await params;
  const locale = localeParam as Locale;
  const page = await serverApiGet<ApiPage>(`/content/public/pages/${slug}`).catch(
    () => null,
  );

  // The missing-page body renders inline (HTTP 200) — keep it out of
  // the index. CMS slugs are locale-scoped and the payload carries only
  // the current one — self-canonical, no alternates (gap-B2 D1 v2).
  if (!page) {
    return { robots: { index: false, follow: false } };
  }

  return pageSeo({
    title: page.title || "Pages — Baxela Storefront",
    description: page.description,
    locale,
    localeUrls: canonicalOnlyLocaleUrls("/pages", page.slug ?? slug, locale),
  });
}

export default async function CmsPage({
  params,
}: PageProps<"/[locale]/pages/[slug]">) {
  const { slug } = await params;
  const tLayout = await getTranslations("shared.layout");
  const page = await serverApiGet<ApiPage>(`/content/public/pages/${slug}`).catch(
    () => null,
  );
  // Unknown CMS slugs render the 404 body inline — notFound() itself
  // renders blank under the app/[locale] root layout.
  if (!page) {
    return <NotFoundContent />;
  }

  const paragraphs = (page.content ?? "")
    .split(/\n{2,}/)
    .map((paragraph) => paragraph.trim())
    .filter(Boolean);

  return (
    <>
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
          <li className="font-medium text-foreground rtl:normal-case rtl:tracking-normal">
            {page.title}
          </li>
        </ol>
      </nav>

      <section className="border-t border-border-light">
        <div className="mx-auto max-w-3xl px-6 py-16">
          <h1 className="text-3xl font-semibold text-foreground md:text-4xl rtl:normal-case rtl:tracking-normal">
            {page.title}
          </h1>
          {page.description ? (
            <p className="mt-4 text-lg text-secondary-text rtl:normal-case rtl:tracking-normal">
              {page.description}
            </p>
          ) : null}
          {paragraphs.length > 0 ? (
            <div className="mt-12 space-y-6">
              {paragraphs.map((paragraph, index) => (
                <p
                  key={index}
                  className="leading-7 text-secondary-text rtl:normal-case rtl:tracking-normal"
                >
                  {paragraph}
                </p>
              ))}
            </div>
          ) : null}
        </div>
      </section>
    </>
  );
}