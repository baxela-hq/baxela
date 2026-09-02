"use client";

import { useState, type ReactNode } from "react";

export interface ProductFilterCategory {
  label: string;
  count: number;
  checked?: boolean;
}

interface ProductFiltersProps {
  categories: ProductFilterCategory[];
  sizes: string[];
  sortOptions: string[];
  children: ReactNode;
}

/**
 * Filter sidebar + toolbar for the products listing.
 *
 * Expanded (default) renders the left sidebar (Category / Size / Price) next
 * to the sort row, matching the product-listing screen. Collapsed collapses
 * the sidebar into a single compact "Filters" trigger on the left of the
 * toolbar row with the sort control on the right, matching the
 * filter-collapsed screen. The product grid and pagination are passed as
 * children so they stay server-rendered.
 */
export default function ProductFilters({
  categories,
  sizes,
  sortOptions,
  children,
}: ProductFiltersProps) {
  const [expanded, setExpanded] = useState(true);

  return (
    <div
      className={
        expanded ? "mt-8 grid gap-10 lg:grid-cols-[240px_1fr]" : "mt-8"
      }
    >
      {expanded ? (
        <aside aria-label="Product filters" className="space-y-8">
          <button
            type="button"
            onClick={() => setExpanded(false)}
            aria-expanded={expanded}
            className="text-sm text-secondary-text transition-colors hover:text-accent"
          >
            Hide filters
          </button>

          <div>
            <h2 className="text-sm font-semibold uppercase tracking-wide text-foreground">
              Category
            </h2>
            <ul className="mt-4 space-y-3">
              {categories.map((category) => (
                <li key={category.label}>
                  <label className="flex cursor-pointer items-center gap-3 text-sm text-foreground">
                    <input
                      type="checkbox"
                      defaultChecked={category.checked}
                      className="size-4 rounded-default border border-border accent-accent"
                    />
                    {category.label}
                    <span className="text-secondary-text">
                      ({category.count})
                    </span>
                  </label>
                </li>
              ))}
            </ul>
          </div>

          <div>
            <h2 className="text-sm font-semibold uppercase tracking-wide text-foreground">
              Size
            </h2>
            <div className="mt-4 flex flex-wrap gap-2">
              {sizes.map((size) => (
                <button
                  key={size}
                  type="button"
                  className="rounded-default border border-border px-4 py-2 text-sm text-foreground transition-colors hover:bg-muted"
                >
                  {size}
                </button>
              ))}
            </div>
          </div>

          <div>
            <h2 className="text-sm font-semibold uppercase tracking-wide text-foreground">
              Price
            </h2>
            <p className="mt-4 text-sm text-secondary-text">$0 — $150</p>
            <input
              type="range"
              min={0}
              max={150}
              defaultValue={150}
              aria-label="Maximum price"
              className="mt-3 w-full accent-accent"
            />
          </div>
        </aside>
      ) : null}

      <div>
        <div className="flex flex-wrap items-center justify-between gap-4 border-b border-border-light pb-4">
          {expanded ? (
            <label htmlFor="sort" className="text-sm text-secondary-text">
              Sort by
            </label>
          ) : (
            <button
              type="button"
              onClick={() => setExpanded(true)}
              aria-expanded={expanded}
              className="rounded-default border border-border px-4 py-2 text-sm font-medium text-foreground transition-colors hover:bg-muted"
            >
              Filters
            </button>
          )}

          <div className="flex items-center gap-4">
            {!expanded ? (
              <label htmlFor="sort" className="text-sm text-secondary-text">
                Sort by
              </label>
            ) : null}
            <select
              id="sort"
              defaultValue="Featured"
              className="h-11 rounded-default border border-border bg-white px-4 text-sm text-foreground focus:border-primary focus:outline-none"
            >
              {sortOptions.map((option) => (
                <option key={option}>{option}</option>
              ))}
            </select>
          </div>
        </div>

        <div className="mt-8">{children}</div>
      </div>
    </div>
  );
}
