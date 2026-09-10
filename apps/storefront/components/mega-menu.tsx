"use client";

import { cn } from "@/lib/utils";
import type { MegaMenuColumn } from "@/lib/menu";
import { Link } from "@/i18n/navigation";

/**
 * Screen 07 — Mega menu (Figma node 3235:1719).
 *
 * The Figma frame is a flattened image mockup, so the panel layout follows the
 * storefront's established conventions: a centered dropdown card under the
 * triggering nav link with category columns on a white surface. Columns come
 * from the header Menu API (the triggering nav link's children).
 */

export function MegaMenu({
  columns,
  className,
}: {
  columns: MegaMenuColumn[];
  className?: string;
}) {
  return (
    <div
      className={cn(
        "bg-white shadow-[0_24px_48px_-24px_rgba(23,23,23,0.15)]",
        className,
      )}
    >
      <div className="mx-auto grid max-w-7xl grid-cols-2 gap-10 px-6 py-10 md:grid-cols-4">
        {columns.map((column) => (
          <div key={column.title ?? column.links[0]?.href}>
            <p className="text-sm font-semibold uppercase tracking-wide text-secondary-text rtl:normal-case rtl:tracking-normal">
              {column.title}
            </p>
            <ul className="mt-4 space-y-3">
              {column.links.map((link) => (
                <li key={`${link.href}-${link.label ?? ""}`}>
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
  columns,
  className,
}: {
  label: string;
  href: string;
  columns: MegaMenuColumn[];
  className?: string;
}) {
  return (
    <div className="group relative">
      <Link href={href} className={className}>
        {label}
      </Link>
      {columns.length > 0 ? (
        <div className="invisible absolute left-1/2 top-full z-50 -translate-x-1/2 pt-4 opacity-0 transition-opacity duration-150 group-hover:visible group-hover:opacity-100 group-focus-within:visible group-focus-within:opacity-100">
          <MegaMenu
            columns={columns}
            className="w-[min(90vw,64rem)] rounded-default border border-border-light"
          />
        </div>
      ) : null}
    </div>
  );
}