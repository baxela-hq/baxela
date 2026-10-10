import { getTranslations } from "next-intl/server";
import { Button } from "@/components/ui/button";
import ProductCard from "@/components/product-card";
import PostCard from "@/components/blog/post-card";
import { NewsletterForm } from "@/components/newsletter-form";
import { serverApiGet } from "@/lib/api/server";
import type {
  ApiPost,
  ApiProduct,
  ApiPublicCategory,
  Paginated,
} from "@/lib/api/types";
import { Link } from "@/i18n/navigation";

export default async function HomePage() {
  const t = await getTranslations("home.home");

  // Curated featured products; a fresh store with nothing curated yet
  // falls back to the newest products so the section never sits empty.
  const [categoriesPage, featuredPage, featuredPostsPage] = await Promise.all([
    serverApiGet<Paginated<ApiPublicCategory>>(
      "/catalog/public/categories?featured=true&per_page=4"
    ).catch(() => null),
    serverApiGet<Paginated<ApiProduct>>(
      "/catalog/public/products?featured=true&per_page=4"
    ).catch(() => null),
    serverApiGet<Paginated<ApiPost>>(
      "/content/public/posts?featured=true&per_page=3"
    ).catch(() => null),
  ]);

  const categories = (categoriesPage?.data ?? []).slice(0, 4);
  let featured = featuredPage?.data ?? [];

  if (featured.length === 0) {
    const newestPage = await serverApiGet<Paginated<ApiProduct>>(
      "/catalog/public/products?per_page=4"
    ).catch(() => null);
    featured = newestPage?.data ?? [];
  }

  // Same curation fallback as products: nothing curated yet → newest posts.
  let featuredPosts = featuredPostsPage?.data ?? [];

  if (featuredPosts.length === 0) {
    const newestPostsPage = await serverApiGet<Paginated<ApiPost>>(
      "/content/public/posts?per_page=3"
    ).catch(() => null);
    featuredPosts = newestPostsPage?.data ?? [];
  }

  return (
    <>
      {/* Hero */}
      <section className="border-b border-border-light">
        <div className="mx-auto grid max-w-7xl items-center gap-12 px-6 py-20 md:grid-cols-2 md:py-28">
          <div>
            <p className="text-sm font-medium uppercase tracking-widest text-accent rtl:normal-case rtl:tracking-normal">
              {t("hero.texts.eyebrow")}
            </p>
            <h1 className="mt-4 text-5xl font-bold leading-tight text-foreground md:text-6xl rtl:normal-case rtl:tracking-normal">
              {t("hero.texts.title")}
            </h1>
            <p className="mt-6 max-w-md text-lg text-secondary-text rtl:normal-case rtl:tracking-normal">
              {t("hero.texts.description")}
            </p>
            <div className="mt-10 flex flex-wrap items-center gap-4">
              <Button fullWidth={false} className="px-10">
                {t("hero.actions.primary")}
              </Button>
              <Button variant="outline" fullWidth={false} className="px-10">
                {t("hero.actions.secondary")}
              </Button>
            </div>
          </div>
          <div className="flex aspect-[4/3] items-center justify-center rounded-default bg-muted">
            <span className="text-sm text-secondary-text rtl:normal-case rtl:tracking-normal">
              {t("hero.texts.image_placeholder")}
            </span>
          </div>
        </div>
      </section>

      {/* Categories */}
      {categories.length > 0 ? (
        <section className="border-b border-border-light">
          <div className="mx-auto max-w-7xl px-6 py-16">
            <div className="flex items-end justify-between">
              <h2 className="text-3xl font-bold text-foreground rtl:normal-case rtl:tracking-normal">
                {t("categories.texts.title")}
              </h2>
              <Link
                href="/products"
                className="text-sm font-medium text-accent hover:underline"
              >
                {t("categories.actions.view_all")}
              </Link>
            </div>
            <div className="mt-8 grid grid-cols-2 gap-6 lg:grid-cols-4">
              {categories.map((category) => (
                <Link
                  key={category.id}
                  href={`/products?category=${category.slug ?? category.id}`}
                  className="group flex flex-col items-center rounded-default border border-border-light p-8 transition-colors hover:border-primary"
                >
                  <div className="flex aspect-square w-full max-w-40 items-center justify-center overflow-hidden rounded-default bg-muted transition-colors group-hover:bg-border-light">
                    {category.image_url ? (
                      // Backend-served images from arbitrary hosts — next/image would
                      // need remotePatterns for every storage host, so use a plain img.
                      // eslint-disable-next-line @next/next/no-img-element
                      <img
                        src={category.image_url}
                        alt={category.title ?? ""}
                        className="size-full object-cover"
                        loading="lazy"
                      />
                    ) : (
                      <span className="px-4 text-center text-sm text-secondary-text rtl:normal-case rtl:tracking-normal">
                        {category.title}
                      </span>
                    )}
                  </div>
                  <h3 className="mt-4 text-base font-medium text-foreground rtl:normal-case rtl:tracking-normal">
                    {category.title}
                  </h3>
                </Link>
              ))}
            </div>
          </div>
        </section>
      ) : null}

      {/* Featured products */}
      {featured.length > 0 ? (
        <section className="border-b border-border-light">
          <div className="mx-auto max-w-7xl px-6 py-16">
            <div className="flex items-end justify-between">
              <h2 className="text-3xl font-bold text-foreground rtl:normal-case rtl:tracking-normal">
                {t("featured.texts.title")}
              </h2>
              <Link
                href="/products"
                className="text-sm font-medium text-accent hover:underline"
              >
                {t("featured.actions.view_all")}
              </Link>
            </div>
            <div className="mt-8 grid grid-cols-2 gap-x-6 gap-y-12 lg:grid-cols-4">
              {featured.map((product) => (
                <ProductCard key={product.id} product={product} />
              ))}
            </div>
          </div>
        </section>
      ) : null}

      {/* Featured posts */}
      {featuredPosts.length > 0 ? (
        <section className="border-b border-border-light">
          <div className="mx-auto max-w-7xl px-6 py-16">
            <div className="flex items-end justify-between">
              <h2 className="text-3xl font-bold text-foreground rtl:normal-case rtl:tracking-normal">
                {t("featured-posts.texts.title")}
              </h2>
              <Link
                href="/blog"
                className="text-sm font-medium text-accent hover:underline"
              >
                {t("featured-posts.actions.view_all")}
              </Link>
            </div>
            <div className="mt-8 grid grid-cols-1 gap-x-6 gap-y-12 sm:grid-cols-2 lg:grid-cols-3">
              {featuredPosts.map((post) => (
                <PostCard key={post.id} post={post} />
              ))}
            </div>
          </div>
        </section>
      ) : null}

      {/* Promo banner */}
      <section className="border-b border-border-light bg-primary">
        <div className="mx-auto flex max-w-7xl flex-col items-center gap-6 px-6 py-16 text-center">
          <h2 className="max-w-2xl text-3xl font-bold text-primary-foreground md:text-4xl rtl:normal-case rtl:tracking-normal">
            {t("promo.texts.title")}
          </h2>
          <p className="max-w-xl text-base text-primary-foreground/80 rtl:normal-case rtl:tracking-normal">
            {t("promo.texts.description")}
          </p>
          <Link
            href="/signup"
            className="inline-flex h-14 items-center justify-center rounded-default bg-accent px-10 text-base font-medium text-accent-foreground transition-colors hover:bg-accent/90"
          >
            {t("promo.actions.create_account")}
          </Link>
        </div>
      </section>

      {/* Newsletter */}
      <section>
        <div className="mx-auto max-w-7xl px-6 py-16">
          <div className="mx-auto max-w-xl text-center">
            <h2 className="text-3xl font-bold text-foreground rtl:normal-case rtl:tracking-normal">
              {t("newsletter.texts.title")}
            </h2>
            <p className="mt-3 text-base text-secondary-text rtl:normal-case rtl:tracking-normal">
              {t("newsletter.texts.description")}
            </p>
            <NewsletterForm />
          </div>
        </div>
      </section>
    </>
  );
}
