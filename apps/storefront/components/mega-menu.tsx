"use client";

import Link from "next/link";
import { cn } from "@/lib/utils";

/**
 * Screen 07 — Mega menu (Figma node 3235:1719).
 *
 * The Figma frame is a flattened image mockup, so the panel layout follows the
 * storefront's established conventions: a centered dropdown card under the
 * "Products" nav link with category columns on a white surface.
 */

interface MegaMenuCategory {
  title: string;
  links: { label: string; href: string }[];
}

export const MEGA_MENU_COLUMNS: MegaMenuCategory[] = [
  {
    title: "Sneakers",
    links: [
      { label: "Low top", href: "/products?category=sneakers" },
      { label: "High top", href: "/products?category=sneakers" },
      { label: "Runners", href: "/products?category=sneakers" },
    ],
  },
  {
    title: "Apparel",
    links: [
      { label: "Tees", href: "/products?category=apparel" },
      { label: "Hoodies", href: "/products?category=apparel" },
      { label: "Joggers", href: "/products?category=apparel" },
    ],
  },
  {
    title: "Accessories",
    links: [
      { label: "Caps", href: "/products?category=accessories" },
      { label: "Bags", href: "/products?category=accessories" },
      { label: "Socks", href: "/products?category=accessories" },
    ],
  },
  {
    title: "Footwear",
    links: [
      { label: "Slip-ons", href: "/products?category=footwear" },
      { label: "Sandals", href: "/products?category=footwear" },
      { label: "Boots", href: "/products?category=footwear" },
    ],
  },
];

export function MegaMenu({ className }: { className?: string }) {
  return (
    <div
      className={cn(
        "bg-white shadow-[0_24px_48px_-24px_rgba(23,23,23,0.15)]",
        className,
      )}
    >
      <div className="mx-auto grid max-w-7xl grid-cols-2 gap-10 px-6 py-10 md:grid-cols-4">
        {MEGA_MENU_COLUMNS.map((column) => (
          <div key={column.title}>
            <p className="text-sm font-semibold uppercase tracking-wide text-secondary-text">
              {column.title}
            </p>
            <ul className="mt-4 space-y-3">
              {column.links.map((link) => (
                <li key={link.label}>
                  <Link
                    href={link.href}
                    className="text-base text-foreground transition-colors hover:text-accent"
                  >
                    {link.label}
                  </Link>
                </li>
              ))}
            </ul>
          </div>
        ))}
      </div>
    </div>
  );
}

/**
 * Nav link that reveals the mega menu on hover and keyboard focus.
 */
export function MegaMenuNavItem({
  label,
  href,
  className,
}: {
  label: string;
  href: string;
  className?: string;
}) {
  return (
    <div className="group relative">
      <Link href={href} className={className}>
        {label}
      </Link>
      <div className="invisible absolute left-1/2 top-full z-50 -translate-x-1/2 pt-4 opacity-0 transition-opacity duration-150 group-hover:visible group-hover:opacity-100 group-focus-within:visible group-focus-within:opacity-100">
        <MegaMenu className="w-[min(90vw,64rem)] rounded-default border border-border-light" />
      </div>
    </div>
  );
}
