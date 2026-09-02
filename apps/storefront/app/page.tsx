import Link from "next/link";
import { Button } from "@/components/ui/button";
import { Logo } from "@/components/ui/logo";
import {
  HeartIcon,
  MenuIcon,
  SearchIcon,
  ShoppingCartIcon,
  StarSolidIcon,
} from "@/components/ui/icons";
import ProductCard from "@/components/product-card";
import { MegaMenuNavItem } from "@/components/mega-menu";

const NAV_LINKS = [
  { label: "Home", href: "/" },
  { label: "Products", href: "/products" },
  { label: "About", href: "/about" },
  { label: "Contact", href: "/contact" },
];

const CATEGORIES = [
  { name: "Sneakers", count: 123 },
  { name: "Apparel", count: 89 },
  { name: "Accessories", count: 56 },
  { name: "Footwear", count: 45 },
];

const FEATURED_PRODUCTS = [
  { id: 1, name: "Product 1", price: 29.99 },
  { id: 2, name: "Product 2", price: 39.99 },
  { id: 3, name: "Product 3", price: 49.99 },
  { id: 4, name: "Product 4", price: 59.99 },
];

export default function HomePage() {
  return (
    <div className="flex min-h-screen flex-col bg-background">
      {/* Announcement bar */}
      <div className="bg-primary py-2.5 text-center text-sm text-primary-foreground">
        Free shipping on orders over $75 — 30-day returns, no questions asked.
      </div>

      {/* Header */}
      <header className="sticky top-0 z-40 border-b border-border-light bg-white">
        <div className="mx-auto flex h-20 max-w-7xl items-center justify-between px-6">
          <Logo variant="dark" />
          <nav aria-label="Main navigation" className="hidden items-center gap-8 md:flex">
            {NAV_LINKS.map((link) =>
              link.label === "Products" ? (
                <MegaMenuNavItem
                  key={link.label}
                  label={link.label}
                  href={link.href}
                  className="text-sm font-medium text-foreground transition-colors hover:text-accent"
                />
              ) : (
                <Link
                  key={link.label}
                  href={link.href}
                  className="text-sm font-medium text-foreground transition-colors hover:text-accent"
                >
                  {link.label}
                </Link>
              )
            )}
          </nav>
          <div className="flex items-center gap-1">
            <button type="button" aria-label="Search" className="rounded-default p-2.5 text-foreground transition-colors hover:bg-muted">
              <SearchIcon className="size-5" />
            </button>
            <button type="button" aria-label="Wishlist" className="rounded-default p-2.5 text-foreground transition-colors hover:bg-muted">
              <HeartIcon className="size-5" />
            </button>
            <button type="button" aria-label="Cart" className="rounded-default p-2.5 text-foreground transition-colors hover:bg-muted">
              <ShoppingCartIcon className="size-5" />
            </button>
            <button type="button" aria-label="Open menu" className="rounded-default p-2.5 text-foreground transition-colors hover:bg-muted md:hidden">
              <MenuIcon className="size-5" />
            </button>
          </div>
        </div>
      </header>

      <main className="flex-1">
        {/* Hero */}
        <section className="border-b border-border-light">
          <div className="mx-auto grid max-w-7xl items-center gap-12 px-6 py-20 md:grid-cols-2 md:py-28">
            <div>
              <p className="text-sm font-medium uppercase tracking-widest text-accent">New season, new staples</p>
              <h1 className="mt-4 text-5xl font-bold leading-tight text-foreground md:text-6xl">Everyday essentials, built to last.</h1>
              <p className="mt-6 max-w-md text-lg text-secondary-text">
                Timeless sneakers, apparel and accessories made from responsibly
                sourced materials — designed in small batches, priced fairly.
              </p>
              <div className="mt-10 flex flex-wrap items-center gap-4">
                <Button fullWidth={false} className="px-10">Shop now</Button>
                <Button variant="outline" fullWidth={false} className="px-10">Our story</Button>
              </div>
            </div>
            <div className="flex aspect-[4/3] items-center justify-center rounded-default bg-muted">
              <span className="text-sm text-secondary-text">Featured campaign</span>
            </div>
          </div>
        </section>

        {/* Categories */}
        <section className="border-b border-border-light">
          <div className="mx-auto max-w-7xl px-6 py-16">
            <div className="flex items-end justify-between">
              <h2 className="text-3xl font-bold text-foreground">Shop by category</h2>
              <Link href="/products" className="text-sm font-medium text-accent hover:underline">View all</Link>
            </div>
            <div className="mt-8 grid grid-cols-2 gap-6 lg:grid-cols-4">
              {CATEGORIES.map((category) => (
                <Link key={category.name} href="/products" className="group flex flex-col items-center rounded-default border border-border-light p-8 transition-colors hover:border-primary">
                  <div className="flex aspect-square w-full max-w-40 items-center justify-center rounded-default bg-muted transition-colors group-hover:bg-border-light">
                    <span className="text-sm text-secondary-text">{category.name}</span>
                  </div>
                  <h3 className="mt-4 text-base font-medium text-foreground">{category.name}</h3>
                  <p className="mt-1 text-sm text-secondary-text">{category.count} products</p>
                </Link>
              ))}
            </div>
          </div>
        </section>

        {/* Featured products */}
        <section className="border-b border-border-light">
          <div className="mx-auto max-w-7xl px-6 py-16">
            <div className="flex items-end justify-between">
              <h2 className="text-3xl font-bold text-foreground">Featured products</h2>
              <Link href="/products" className="text-sm font-medium text-accent hover:underline">View all</Link>
            </div>
            <div className="mt-8 grid grid-cols-2 gap-x-6 gap-y-12 lg:grid-cols-4">
              {FEATURED_PRODUCTS.map((product) => (
                <ProductCard key={product.id} product={product} />
              ))}
            </div>
          </div>
        </section>

        {/* Promo banner */}
        <section className="border-b border-border-light bg-primary">
          <div className="mx-auto flex max-w-7xl flex-col items-center gap-6 px-6 py-16 text-center">
            <h2 className="max-w-2xl text-3xl font-bold text-primary-foreground md:text-4xl">Members get 10% off their first order.</h2>
            <p className="max-w-xl text-base text-primary-foreground/80">
              Create an account to unlock early access to drops, free returns
              and a permanent member discount.
            </p>
            <Link
              href="/signup"
              className="inline-flex h-14 items-center justify-center rounded-default bg-accent px-10 text-base font-medium text-accent-foreground transition-colors hover:bg-accent/90"
            >
              Create an account
            </Link>
          </div>
        </section>

        {/* Newsletter */}
        <section>
          <div className="mx-auto max-w-7xl px-6 py-16">
            <div className="mx-auto max-w-xl text-center">
              <h2 className="text-3xl font-bold text-foreground">Join our newsletter</h2>
              <p className="mt-3 text-base text-secondary-text">
                Get 10% off your first order plus early access to new releases.
              </p>
              <form className="mt-8 flex gap-3" action="/newsletter" method="post">
                <input
                  type="email"
                  name="email"
                  required
                  placeholder="Enter your email address"
                  aria-label="Email address"
                  className="h-14 flex-1 rounded-default border border-border bg-white px-4 text-base outline-none transition-colors placeholder:text-secondary-text focus:border-primary"
                />
                <Button type="submit" fullWidth={false} className="px-8">Subscribe</Button>
              </form>
            </div>
          </div>
        </section>
      </main>

      {/* Footer */}
      <footer className="border-t border-border-light bg-white">
        <div className="mx-auto grid max-w-7xl gap-10 px-6 py-14 md:grid-cols-4">
          <div>
            <Logo variant="dark" />
            <p className="mt-4 max-w-xs text-sm text-secondary-text">
              Everyday essentials, built to last. Designed in small batches
              since 2019.
            </p>
          </div>
          <div>
            <h3 className="text-sm font-semibold uppercase tracking-wide text-foreground">Shop</h3>
            <ul className="mt-4 space-y-3 text-sm text-secondary-text">
              {CATEGORIES.map((category) => (
                <li key={category.name}>
                  <Link href="/products" className="transition-colors hover:text-foreground">{category.name}</Link>
                </li>
              ))}
            </ul>
          </div>
          <div>
            <h3 className="text-sm font-semibold uppercase tracking-wide text-foreground">Support</h3>
            <ul className="mt-4 space-y-3 text-sm text-secondary-text">
              <li><Link href="/contact" className="transition-colors hover:text-foreground">Contact us</Link></li>
              <li><Link href="/contact" className="transition-colors hover:text-foreground">Shipping &amp; returns</Link></li>
              <li><Link href="/contact" className="transition-colors hover:text-foreground">Size guide</Link></li>
              <li><Link href="/contact" className="transition-colors hover:text-foreground">FAQ</Link></li>
            </ul>
          </div>
          <div>
            <h3 className="text-sm font-semibold uppercase tracking-wide text-foreground">Account</h3>
            <ul className="mt-4 space-y-3 text-sm text-secondary-text">
              <li><Link href="/login" className="transition-colors hover:text-foreground">Login</Link></li>
              <li><Link href="/signup" className="transition-colors hover:text-foreground">Create account</Link></li>
              <li><Link href="/forgot-password" className="transition-colors hover:text-foreground">Forgot password</Link></li>
            </ul>
          </div>
        </div>
        <div className="border-t border-border-light">
          <div className="mx-auto flex max-w-7xl flex-col items-center justify-between gap-3 px-6 py-6 text-sm text-secondary-text md:flex-row">
            <p>© {new Date().getFullYear()} Baxela. All rights reserved.</p>
            <div className="flex gap-6">
              <Link href="/contact" className="transition-colors hover:text-foreground">Privacy policy</Link>
              <Link href="/contact" className="transition-colors hover:text-foreground">Terms of service</Link>
            </div>
          </div>
        </div>
      </footer>
    </div>
  );
}
