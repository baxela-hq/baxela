import Link from "next/link";
import type { Metadata } from "next";

import ProductCard, {
  type Product,
} from "@/components/product-card";
import { ProductTabs } from "@/components/product-tabs";
import { Button } from "@/components/ui/button";
import {
  HeartIcon,
  ShoppingCartIcon,
  StarSolidIcon,
} from "@/components/ui/icons";
import { Logo } from "@/components/ui/logo";
import { MobileMenu } from "@/components/mobile-menu";
import { SearchMenu } from "@/components/search-menu";

export interface ProductDetail {
  id: number;
  name: string;
  price: number;
  rating: number;
  reviewCount: number;
  sizes: string[];
}

const PRODUCT: ProductDetail = {
  id: 1,
  name: "Product 1",
  price: 74.99,
  rating: 4.8,
  reviewCount: 12,
  sizes: ["XS", "S", "M", "L", "XL"],
};

const RELATED: Product[] = [
  { id: 2, name: "Product 2", price: 39.99 },
  { id: 3, name: "Product 3", price: 49.99 },
  { id: 4, name: "Product 4", price: 59.99 },
];

const NAV_LINKS = [
  { label: "Home", href: "/" },
  { label: "Products", href: "/products" },
  { label: "About", href: "/about" },
  { label: "Contact", href: "/contact" },
];

export const metadata: Metadata = {
  title: "Product — Baxela Storefront",
};

export default async function ProductPage({
  params,
}: PageProps<"/products/[id]">) {
  const { id } = await params;

  return (
    <div className="flex min-h-screen flex-col bg-background">
      <p className="bg-primary py-2.5 text-center text-sm text-primary-foreground">
        Free shipping on orders over $75 — 30-day returns, no questions asked.
      </p>

      <header className="sticky top-0 z-40 border-b border-border-light bg-white">
        <div className="mx-auto flex h-20 max-w-7xl items-center justify-between px-4 sm:px-6">
          <Logo />
          <nav
            aria-label="Main navigation"
            className="hidden items-center gap-8 md:flex"
          >
            {NAV_LINKS.map((link) => (
              <Link
                key={link.label}
                href={link.href}
                className="text-sm font-medium text-foreground transition-colors hover:text-accent"
              >
                {link.label}
              </Link>
            ))}
          </nav>
          <div className="flex items-center gap-0.5 sm:gap-1">
            <SearchMenu />
            <button
              type="button"
              aria-label="Wishlist"
              className="rounded-default p-2.5 text-foreground transition-colors hover:bg-muted"
            >
              <HeartIcon className="size-5" />
            </button>
            <button
              type="button"
              aria-label="Cart"
              className="rounded-default p-2.5 text-foreground transition-colors hover:bg-muted"
            >
              <ShoppingCartIcon className="size-5" />
            </button>
            <MobileMenu />
          </div>
        </div>
      </header>

      <main className="flex-1">
        <nav
          aria-label="Breadcrumb"
          className="border-b border-border-light bg-muted"
        >
          <ol className="mx-auto flex max-w-7xl items-center gap-2 px-6 py-4 text-sm text-secondary-text">
            <li>
              <Link href="/" className="transition-colors hover:text-accent">
                Home
              </Link>
            </li>
            <li aria-hidden="true">/</li>
            <li>
              <Link
                href="/products"
                className="transition-colors hover:text-accent"
              >
                Products
              </Link>
            </li>
            <li aria-hidden="true">/</li>
            <li className="font-medium text-foreground">{PRODUCT.name}</li>
          </ol>
        </nav>

        <section className="mx-auto max-w-7xl px-6 py-12">
          <div className="grid grid-cols-1 gap-12 lg:grid-cols-2">
            <div className="space-y-4">
              <div
                className="aspect-square w-full rounded-default border border-border bg-muted"
                role="img"
                aria-label={`${PRODUCT.name} main image`}
              />
              <div className="grid grid-cols-4 gap-4">
                {[1, 2, 3, 4].map((thumb) => (
                  <div
                    key={thumb}
                    className="aspect-square rounded-default border border-border bg-muted"
                  />
                ))}
              </div>
            </div>

            <div>
              <p className="text-sm text-secondary-text">Sneakers</p>
              <h1 className="mt-2 text-3xl font-bold text-foreground">
                {PRODUCT.name}
              </h1>
              <div className="mt-3 flex items-center gap-2">
                <StarSolidIcon className="size-5 text-accent" />
                <span className="text-sm text-foreground">{PRODUCT.rating}</span>
                <span className="text-sm text-secondary-text">
                  ({PRODUCT.reviewCount} reviews)
                </span>
              </div>
              <p className="mt-4 text-2xl font-semibold text-foreground">
                ${PRODUCT.price.toFixed(2)}
              </p>
              <p className="mt-4 text-sm leading-6 text-secondary-text">
                Built for everyday wear with materials chosen to keep their
                shape and feel wash after wash. A relaxed fit and understated
                design make this an easy piece to live in.
              </p>

              <div className="mt-6">
                <h2 className="text-sm font-semibold uppercase tracking-wide text-foreground">
                  Size
                </h2>
                <div className="mt-3 flex flex-wrap gap-2">
                  {PRODUCT.sizes.map((size) => (
                    <button
                      key={size}
                      type="button"
                      aria-pressed={size === "M"}
                      className={
                        size === "M"
                          ? "rounded-default border border-primary bg-primary px-4 py-2 text-sm text-primary-foreground"
                          : "rounded-default border border-border px-4 py-2 text-sm text-foreground transition-colors hover:bg-muted"
                      }
                    >
                      {size}
                    </button>
                  ))}
                </div>
              </div>

              <div className="mt-8 flex items-center gap-4">
                <div className="flex items-center rounded-default border border-border">
                  <button
                    type="button"
                    aria-label="Decrease quantity"
                    className="px-4 py-3 text-foreground transition-colors hover:bg-muted"
                  >
                    −
                  </button>
                  <span className="px-4 text-sm text-foreground" aria-live="polite">
                    1
                  </span>
                  <button
                    type="button"
                    aria-label="Increase quantity"
                    className="px-4 py-3 text-foreground transition-colors hover:bg-muted"
                  >
                    +
                  </button>
                </div>
                <Button type="button" className="flex-1">
                  Add to cart
                </Button>
                <button
                  type="button"
                  aria-label="Add to wishlist"
                  className="rounded-default border border-border p-3.5 text-foreground transition-colors hover:bg-muted"
                >
                  <HeartIcon className="size-5" />
                </button>
              </div>
            </div>
          </div>
        </section>

        <ProductTabs />

        <section className="mx-auto max-w-7xl px-6 pb-16">
          <h2 className="text-2xl font-bold text-foreground">
            You may also like
          </h2>
          <div className="mt-8 grid grid-cols-1 gap-x-6 gap-y-10 sm:grid-cols-2 xl:grid-cols-3">
            {RELATED.map((product) => (
              <ProductCard key={product.id} product={product} />
            ))}
          </div>
        </section>
      </main>

      <footer className="border-t border-border-light bg-white">
        <div className="mx-auto max-w-7xl px-6 py-12">
          <div className="grid gap-8 md:grid-cols-4">
            <div>
              <Logo />
              <p className="mt-4 text-sm text-secondary-text">
                Everyday essentials, built to last.
              </p>
            </div>
            <div>
              <h2 className="text-sm font-semibold text-foreground">Shop</h2>
              <ul className="mt-4 space-y-3 text-sm text-secondary-text">
                <li>
                  <Link href="/products" className="hover:text-accent">
                    All Products
                  </Link>
                </li>
                <li>
                  <Link href="/about" className="hover:text-accent">
                    About
                  </Link>
                </li>
              </ul>
            </div>
            <div>
              <h2 className="text-sm font-semibold text-foreground">Support</h2>
              <ul className="mt-4 space-y-3 text-sm text-secondary-text">
                <li>
                  <Link href="/contact" className="hover:text-accent">
                    Contact
                  </Link>
                </li>
                <li>
                  <Link href="/login" className="hover:text-accent">
                    Account
                  </Link>
                </li>
              </ul>
            </div>
            <div>
              <h2 className="text-sm font-semibold text-foreground">
                Newsletter
              </h2>
              <p className="mt-4 text-sm text-secondary-text">
                Sign up for new arrivals and offers.
              </p>
            </div>
          </div>
          <p className="mt-10 border-t border-border-light pt-6 text-center text-sm text-secondary-text">
            © {new Date().getFullYear()} Baxela. All rights reserved.
          </p>
        </div>
      </footer>
    </div>
  );
}
