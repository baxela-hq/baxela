import Link from "next/link";
import type { Metadata } from "next";

import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import {
  HeartIcon,
  MenuIcon,
  SearchIcon,
  ShoppingCartIcon,
} from "@/components/ui/icons";
import { Logo } from "@/components/ui/logo";

const NAV_LINKS = [
  { label: "Home", href: "/" },
  { label: "Products", href: "/products" },
  { label: "About", href: "/about" },
  { label: "Contact", href: "/contact" },
];

const CART_ITEMS = [
  { id: 1, name: "Product 1", price: 74.99, quantity: 1 },
  { id: 2, name: "Product 2", price: 64.99, quantity: 2 },
  { id: 3, name: "Product 3", price: 49.99, quantity: 1 },
];

const SHIPPING_METHODS = [
  { id: "free", label: "Free", detail: "5–7 business days", price: "Free" },
  { id: "standard", label: "Standard", detail: "3–5 business days", price: "$9.99" },
  { id: "express", label: "Express", detail: "1–2 business days", price: "$19.99" },
];

export const metadata: Metadata = {
  title: "Checkout — Baxela Storefront",
};

export default function CheckoutPage() {
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
                className="text-sm font-medium text-foreground transition-colors hover:text-accent"
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
            <button
              type="button"
              aria-label="Open menu"
              className="rounded-default p-2.5 text-foreground transition-colors hover:bg-muted md:hidden"
            >
              <MenuIcon className="size-5" />
            </button>
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
            <li className="font-medium text-foreground">Checkout</li>
          </ol>
        </nav>

        <section className="mx-auto max-w-7xl px-6 py-12">
          <h1 className="text-3xl font-bold text-foreground">Checkout</h1>

          <div className="mt-8 grid grid-cols-1 gap-12 lg:grid-cols-[1fr_420px]">
            <div className="space-y-10">
              <div>
                <h2 className="text-lg font-semibold text-foreground">
                  Contact information
                </h2>
                <div className="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                  <Input type="email" placeholder="Email" aria-label="Email" />
                  <Input
                    type="tel"
                    placeholder="Phone (optional)"
                    aria-label="Phone"
                  />
                </div>
              </div>

              <div>
                <h2 className="text-lg font-semibold text-foreground">
                  Shipping address
                </h2>
                <div className="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                  <Input
                    type="text"
                    placeholder="First name"
                    aria-label="First name"
                  />
                  <Input
                    type="text"
                    placeholder="Last name"
                    aria-label="Last name"
                  />
                  <div className="sm:col-span-2">
                    <Input
                      type="text"
                      placeholder="Street address"
                      aria-label="Street address"
                    />
                  </div>
                  <Input
                    type="text"
                    placeholder="Apartment, suite, etc. (optional)"
                    aria-label="Apartment"
                  />
                  <Input type="text" placeholder="City" aria-label="City" />
                  <Input
                    type="text"
                    placeholder="State"
                    aria-label="State"
                  />
                  <Input
                    type="text"
                    placeholder="ZIP code"
                    aria-label="ZIP code"
                  />
                </div>
              </div>

              <div>
                <h2 className="text-lg font-semibold text-foreground">
                  Shipping method
                </h2>
                <div className="mt-4 space-y-3">
                  {SHIPPING_METHODS.map((method) => (
                    <label
                      key={method.id}
                      className="flex cursor-pointer items-center justify-between gap-4 rounded-default border border-border px-4 py-4 transition-colors hover:bg-muted"
                    >
                      <span className="flex items-center gap-3">
                        <input
                          type="radio"
                          name="shipping"
                          value={method.id}
                          defaultChecked={method.id === "free"}
                          className="size-4 accent-accent"
                        />
                        <span>
                          <span className="block text-sm font-medium text-foreground">
                            {method.label}
                          </span>
                          <span className="block text-sm text-secondary-text">
                            {method.detail}
                          </span>
                        </span>
                      </span>
                      <span className="text-sm font-medium text-foreground">
                        {method.price}
                      </span>
                    </label>
                  ))}
                </div>
              </div>
            </div>

            <aside
              aria-label="Order summary"
              className="h-fit rounded-default border border-border-light p-6 lg:sticky lg:top-28"
            >
              <h2 className="text-lg font-semibold text-foreground">
                Order summary
              </h2>
              <ul className="mt-6 space-y-4">
                {CART_ITEMS.map((item) => (
                  <li key={item.id} className="flex items-center gap-4">
                    <span
                      className="size-16 shrink-0 rounded-default border border-border bg-muted"
                      aria-hidden="true"
                    />
                    <span className="min-w-0 flex-1">
                      <span className="block truncate text-sm font-medium text-foreground">
                        {item.name}
                      </span>
                      <span className="block text-sm text-secondary-text">
                        Qty {item.quantity}
                      </span>
                    </span>
                    <span className="text-sm font-medium text-foreground">
                      ${(item.price * item.quantity).toFixed(2)}
                    </span>
                  </li>
                ))}
              </ul>

              <div className="mt-6 space-y-3 border-t border-border-light pt-6 text-sm">
                <p className="flex items-center justify-between text-secondary-text">
                  <span>Subtotal</span>
                  <span className="font-medium text-foreground">$254.96</span>
                </p>
                <p className="flex items-center justify-between text-secondary-text">
                  <span>Shipping</span>
                  <span className="font-medium text-foreground">Free</span>
                </p>
                <p className="flex items-center justify-between text-secondary-text">
                  <span>Tax (estimated)</span>
                  <span className="font-medium text-foreground">$20.40</span>
                </p>
                <p className="flex items-center justify-between border-t border-border-light pt-3 text-base font-semibold text-foreground">
                  <span>Total</span>
                  <span>$275.36</span>
                </p>
              </div>

              <div className="mt-6 flex gap-2">
                <Input
                  type="text"
                  placeholder="Discount code"
                  aria-label="Discount code"
                />
                <Button type="button" variant="outline" fullWidth={false}>
                  Apply
                </Button>
              </div>

              <Button type="button" variant="accent" fullWidth className="mt-6">
                Place order
              </Button>
            </aside>
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
