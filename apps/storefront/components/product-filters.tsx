"use client";

import { useState, type ReactNode } from "react";
import { useSearchParams } from "next/navigation";
import { useTranslations } from "next-intl";
import { usePathname, useRouter } from "@/i18n/navigation";

export interface ProductFilterCategory {
  id: number;
  title: string | null;
}

interface ProductFiltersProps {
  categories: ProductFilterCategory[];
  selectedCategoryId: number | null;
  sortValue: string;
  children: ReactNode;
}

/**
 * Filter sidebar + toolbar for the products listing. Category and sort are
 * real: they update URL search params so the server-rendered grid refetches.
 * Size and price stay visual mocks until public option/price endpoints exist.
 */
export default function ProductFilters({
  categories,
  selectedCategoryId,
  sortValue,
  children,
}: ProductFiltersProps) {
  const [expanded, setExpanded] = useState(true);
  const t = useTranslations("catalog.products");
  const pathname = usePathname();
  const router = useRouter();
  const searchParams = useSearchParams();

  const pushParams = (mutate: (params: URLSearchParams) => void) => {
    const params = new URLSearchParams(searchParams.toString());
    mutate(params);
    params.delete("page"); // any filter change resets pagination
    const qs = params.toString();
    router.push(qs ? `${pathname}?${qs}` : pathname);
  };

  const toggleCategory = (id: number) => {
    pushParams((params) => {
      if (selectedCategoryId === id) {
        params.delete("category");
      } else {
        params.set("category", String(id));
      }
    });
  };

  const changeSort = (value: string) => {
    pushParams((params) => {
      params.set("sort", value);
    });
  };

  const hasActiveFilters =
    selectedCategoryId !== null || sortValue !== "featured";

  const resetFilters = () => {
    pushParams((params) => {
      params.delete("category");
      params.delete("sort");
    });
  };

  const sortOptions = [
    { value: "featured", label: t("sort.options.featured") },
    { value: "newest", label: t("sort.options.newest") },
    { value: "price_asc", label: t("sort.options.price_low_high") },
    { value: "price_desc", label: t("sort.options.price_high_low") },
  ];

  return (
    <div
      className={
        expanded ? "mt-8 grid gap-10 lg:grid-cols-[240px_1fr]" : "mt-8"
      }
    >
      {expanded ? (
        <aside aria-label={t("filters.labels.aside")} className="space-y-8">
          <button
            type="button"
            onClick={() => setExpanded(false)}
            aria-expanded={expanded}
            className="text-sm text-secondary-text transition-colors hover:text-accent"
          >
            {t("filters.actions.hide")}
          </button>

          <div>
            <h2 className="text-sm font-semibold uppercase tracking-wide text-foreground rtl:normal-case rtl:tracking-normal">
              {t("filters.labels.category")}
            </h2>
            <ul className="mt-4 space-y-3">
              {categories.map((category) => (
                <li key={category.id}>
                  <label className="flex cursor-pointer items-center gap-3 text-sm text-foreground rtl:normal-case rtl:tracking-normal">
                    <input
                      type="checkbox"
                      checked={selectedCategoryId === category.id}
                      onChange={() => toggleCategory(category.id)}
                      className="size-4 rounded-default border border-border accent-accent"
                    />
                    {category.title}
                  </label>
                </li>
              ))}
            </ul>
          </div>

          <div>
            <h2 className="text-sm font-semibold uppercase tracking-wide text-foreground rtl:normal-case rtl:tracking-normal">
              {t("filters.labels.size")}
            </h2>
            <div className="mt-4 flex flex-wrap gap-2">
              {["XS", "S", "M", "L", "XL"].map((size) => (
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
            <h2 className="text-sm font-semibold uppercase tracking-wide text-foreground rtl:normal-case rtl:tracking-normal">
              {t("filters.labels.price")}
            </h2>
            <p className="mt-4 text-sm text-secondary-text">$0 — $150</p>
            <input
              type="range"
              min={0}
              max={150}
              defaultValue={150}
              aria-label={t("filters.labels.max_price")}
              className="mt-3 w-full accent-accent"
            />
          </div>
        </aside>
      ) : null}

      <div>
        <div className="flex flex-wrap items-center justify-between gap-4 border-b border-border-light pb-4">
          {expanded ? (
            <label
              htmlFor="sort"
              className="text-sm text-secondary-text rtl:normal-case rtl:tracking-normal"
            >
              {t("sort.labels.by")}
            </label>
          ) : (
            <button
              type="button"
              onClick={() => setExpanded(true)}
              aria-expanded={expanded}
              className="rounded-default border border-border px-4 py-2 text-sm font-medium text-foreground transition-colors hover:bg-muted"
            >
              {t("filters.actions.show")}
            </button>
          )}

          <div className="flex items-center gap-4">
            {hasActiveFilters ? (
              <button
                type="button"
                onClick={resetFilters}
                className="text-sm text-secondary-text underline underline-offset-2 transition-colors hover:text-accent rtl:normal-case rtl:tracking-normal"
              >
                {t("filters.actions.reset")}
              </button>
            ) : null}
            {!expanded ? (
              <label
                htmlFor="sort"
                className="text-sm text-secondary-text rtl:normal-case rtl:tracking-normal"
              >
                {t("sort.labels.by")}
              </label>
            ) : null}
            <select
              id="sort"
              value={sortValue}
              onChange={(event) => changeSort(event.target.value)}
              className="h-11 rounded-default border border-border bg-white px-4 text-sm text-foreground focus:border-primary focus:outline-none"
            >
              {sortOptions.map((option) => (
                <option key={option.value} value={option.value}>
                  {option.label}
                </option>
              ))}
            </select>
          </div>
        </div>

        <div className="mt-8">{children}</div>
      </div>
    </div>
  );
}
