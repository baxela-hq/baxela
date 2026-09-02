import Link from "next/link";

import ProductCard, {
  type Product,
} from "@/components/product-card";
import {
  HeartIcon,
  SearchIcon,
  ShoppingCartIcon,
} from "@/components/ui/icons";
import { Logo } from "@/components/ui/logo";
import ProductFilters from "@/components/product-filters";
import { MobileMenu } from "@/components/mobile-menu";

const NAV_LINKS = [
  { label: "Home", href: "/" },
  { label: "Products", href: "/products" },
  { label: "About", href: "/about" },
  { label: "Contact", href: "/contact" },
];

const CATEGORY_FILTERS = [
  { label: "Sneakers", count: 123, checked: true },
  { label: "Apparel", count: 89, checked: false },
  { label: "Accessories", count: 56, checked: false },
  { label: "Footwear", count: 45, checked: false },
];

const SIZE_FILTERS = ["XS", "S", "M", "L", "XL"];

const PRODUCTS: Product[] = [
  { id: 1, name: "Product 1", price: 29.99 },
  { id: 2, name: "Product 2", price: 39.99 },
  { id: 3, name: "Product 3", price: 49.99 },
  { id: 4, name: "Product 4", price: 59.99 },
  { id: 5, name: "Product 5", price: 34.99 },
  { id: 6, name: "Product 6", price: 44.99 },
  { id: 7, name: "Product 7", price: 54.99 },
  { id: 8, name: "Product 8", price: 64.99 },
  { id: 9, name: "Product 9", price: 74.99 },
];

const SORT_OPTIONS = ["Featured", "Newest", "Price: Low to High", "Price: High to Low"];

export const metadata = {
  title: "Products — Baxela Storefront",
};

export default function ProductsPage() {
  return (
    <div className="flex min-h-screen flex-col bg-background">
      <p className="bg-primary py-2.5 text-center text-sm text-primary-foreground">
        Free shipping on orders over $75 — 30-day returns, no questions asked.
      </p>

      <header className="sticky top-0 z-40 border-b border-border-light bg-white">
        <div className="mx-auto flex h-20 max-w-7xl items-center justify-between px-6">
          <Logo />
          <nav
            aria-label="Main navigation"
            className="hidden items-center gap-8 md:flex"
          >
            {NAV_LINKS.map((link) => (
              <Link
                key={link.label}
                href={link.href}
                className={
                  link.label === "Products"
                    ? "text-sm font-medium text-foreground underline underline-offset-4"
                    : "text-sm font-medium text-foreground transition-colors hover:text-accent"
                }
              >
                {link.label}
              </Link>
            ))}
          </nav>
          <div className="flex items-center gap-1">
            <button
              type="button"
              aria-label="Search"
              className="rounded-default p-2.5 text-foreground transition-colors hover:bg-muted"
            >
              <SearchIcon className="size-5" />
            </button>
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
            <li className="font-medium text-foreground">Products</li>
          </ol>
        </nav>

        <section className="mx-auto max-w-7xl px-6 py-12">
          <h1 className="text-3xl font-bold text-foreground">All Products</h1>
          <p className="mt-2 text-sm text-secondary-text">
            Showing 1–9 of 123 results
          </p>

          <ProductFilters
            categories={CATEGORY_FILTERS}
            sizes={SIZE_FILTERS}
            sortOptions={SORT_OPTIONS}
          >
            <div className="grid grid-cols-1 gap-x-6 gap-y-10 sm:grid-cols-2 xl:grid-cols-3">
                {PRODUCTS.map((product) => (
                  <ProductCard key={product.id} product={product} />
                ))}
              </div>

              <nav
                aria-label="Pagination"
                className="mt-12 flex items-center justify-center gap-2"
              >
                <button
                  type="button"
                  className="rounded-default border border-border px-4 py-2 text-sm text-foreground transition-colors hover:bg-muted"
                >
                  Previous
                </button>
                {[1, 2, 3].map((page) => (
                  <button
                    key={page}
                    type="button"
                    aria-current={page === 1 ? "page" : undefined}
                    className={
                      page === 1
                        ? "rounded-default bg-primary px-4 py-2 text-sm text-primary-foreground"
                        : "rounded-default border border-border px-4 py-2 text-sm text-foreground transition-colors hover:bg-muted"
                    }
                  >
                    {page}
                  </button>
                ))}
                <button
                  type="button"
                  className="rounded-default border border-border px-4 py-2 text-sm text-foreground transition-colors hover:bg-muted"
                >
                  Next
                </button>
              </nav>
          </ProductFilters>
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
