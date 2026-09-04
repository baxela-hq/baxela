"use client";

import { useState, type ReactNode } from "react";
import { useSearchParams } from "next/navigation";
import { useTranslations } from "next-intl";
import { usePathname, useRouter } from "@/i18n/navigation";
import { cn } from "@/lib/utils";
import { ChevronDownIcon } from "@/components/ui/icons";
import type { ApiOptionGroup } from "@/lib/api/types";

export interface ProductFilterCategory {
  id: number;
  title: string | null;
  parent_id: number | null;
}

interface ProductFiltersProps {
  categories: ProductFilterCategory[];
  selectedCategoryId: number | null;
  sortValue: string;
  optionGroups: ApiOptionGroup[];
  selectedOptionValueIds: number[];
  maxPriceValue: number | null;
  priceBound: number;
  children: ReactNode;
}

/**
 * Filter sidebar + toolbar for the products listing. Categories, option
 * values (size/color/…), price and sort all update URL search params so the
 * server-rendered grid refetches.
 */
export default function ProductFilters({
  categories,
  selectedCategoryId,
  sortValue,
  optionGroups,
  selectedOptionValueIds,
  maxPriceValue,
  priceBound,
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
    selectedCategoryId !== null ||
    selectedOptionValueIds.length > 0 ||
    maxPriceValue !== null ||
    sortValue !== "featured";

  const resetFilters = () => {
    pushParams((params) => {
      params.delete("category");
      params.delete("sizes");
      params.delete("max_price");
      params.delete("sort");
    });
  };

  const toggleOptionValue = (id: number) => {
    pushParams((params) => {
      const current = new Set(
        (params.get("sizes") ?? "")
          .split(",")
          .map((value) => Number.parseInt(value, 10))
          .filter((value) => Number.isFinite(value)),
      );
      if (current.has(id)) {
        current.delete(id);
      } else {
        current.add(id);
      }
      if (current.size > 0) {
        params.set("sizes", [...current].join(","));
      } else {
        params.delete("sizes");
      }
    });
  };

  const commitMaxPrice = (value: number) => {
    pushParams((params) => {
      if (value >= priceBound) {
        params.delete("max_price");
      } else {
        params.set("max_price", String(value));
      }
    });
  };

  const sortOptions = [
    { value: "featured", label: t("sort.options.featured") },
    { value: "newest", label: t("sort.options.newest") },
    { value: "price_asc", label: t("sort.options.price_low_high") },
    { value: "price_desc", label: t("sort.options.price_high_low") },
  ];

  // Tree view: parents first, their children nested and hidden behind the
  // + expander (like the design). A category whose parent is not in the
  // list is treated as a root so orphans still show.
  const roots = categories.filter(
    (category) =>
      category.parent_id === null ||
      !categories.some((other) => other.id === category.parent_id),
  );
  const childrenOf = (parentId: number) =>
    categories.filter((category) => category.parent_id === parentId);
  const [expandedParents, setExpandedParents] = useState<Set<number>>(
    new Set(),
  );
  const toggleExpanded = (id: number) => {
    setExpandedParents((previous) => {
      const next = new Set(previous);
      if (next.has(id)) {
        next.delete(id);
      } else {
        next.add(id);
      }
      return next;
    });
  };

  const categoryCheckbox = (category: ProductFilterCategory) => (
    <label className="flex min-w-0 cursor-pointer items-center gap-3 text-sm text-foreground rtl:normal-case rtl:tracking-normal">
      <input
        type="checkbox"
        checked={selectedCategoryId === category.id}
        onChange={() => toggleCategory(category.id)}
        className="size-4 shrink-0 rounded-default border border-border accent-accent"
      />
      <span className="truncate">{category.title}</span>
    </label>
  );

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
            <div className="mt-4 max-h-72 space-y-3 overflow-y-auto pe-1">
              {roots.map((category) => {
                const children = childrenOf(category.id);
                const expanded = expandedParents.has(category.id);

                return (
                  <div key={category.id}>
                    <div className="flex items-center justify-between gap-2">
                      {categoryCheckbox(category)}
                      {children.length > 0 ? (
                        <button
                          type="button"
                          aria-expanded={expanded}
                          aria-label={
                            expanded
                              ? t("filters.actions.collapse_category")
                              : t("filters.actions.expand_category")
                          }
                          onClick={() => toggleExpanded(category.id)}
                          className="shrink-0 p-1 text-secondary-text transition-colors hover:text-foreground"
                        >
                          <ChevronDownIcon
                            className={cn(
                              "size-4 transition-transform",
                              expanded
                                ? "rotate-0"
                                : "ltr:-rotate-90 rtl:rotate-90",
                            )}
                          />
                        </button>
                      ) : null}
                    </div>
                    {children.length > 0 && expanded ? (
                      <ul className="mt-3 space-y-3 ps-6">
                        {children.map((child) => (
                          <li key={child.id}>{categoryCheckbox(child)}</li>
                        ))}
                      </ul>
                    ) : null}
                  </div>
                );
              })}
            </div>
          </div>

          {optionGroups.map((group) => (
            <div key={group.id}>
              <h2 className="text-sm font-semibold uppercase tracking-wide text-foreground rtl:normal-case rtl:tracking-normal">
                {group.title}
              </h2>
              <ul className="mt-4 max-h-44 space-y-3 overflow-y-auto pe-1">
                {group.values.map((value) => (
                  <li key={value.id}>
                    <label className="flex cursor-pointer items-center gap-3 text-sm text-foreground rtl:normal-case rtl:tracking-normal">
                      <input
                        type="checkbox"
                        checked={selectedOptionValueIds.includes(value.id)}
                        onChange={() => toggleOptionValue(value.id)}
                        className="size-4 rounded-default border border-border accent-accent"
                      />
                      {value.title}
                    </label>
                  </li>
                ))}
              </ul>
            </div>
          ))}

          <PriceFilter
            key={`${priceBound}-${maxPriceValue ?? "max"}`}
            bound={priceBound}
            initialValue={maxPriceValue}
            label={t("filters.labels.max_price")}
            onCommit={commitMaxPrice}
          />
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

interface PriceFilterProps {
  bound: number;
  initialValue: number | null;
  label: string;
  onCommit: (value: number) => void;
}

/**
 * Max-price slider. Dragging updates the label locally; the filter commits
 * to the URL on release / keyboard step. Remounts (via key) when the bound
 * or the URL value changes so the knob never drifts from the applied state.
 */
function PriceFilter({ bound, initialValue, label, onCommit }: PriceFilterProps) {
  const t = useTranslations("catalog.products");
  const [value, setValue] = useState(initialValue ?? bound);

  return (
    <div>
      <h2 className="text-sm font-semibold uppercase tracking-wide text-foreground rtl:normal-case rtl:tracking-normal">
        {t("filters.labels.price")}
      </h2>
      <p className="mt-4 text-sm text-secondary-text">
        $0 — ${value.toLocaleString()}
      </p>
      <input
        type="range"
        min={0}
        max={bound}
        step={5}
        value={value}
        onChange={(event) => setValue(Number(event.target.value))}
        onPointerUp={() => onCommit(value)}
        onKeyUp={() => onCommit(value)}
        aria-label={label}
        className="mt-3 w-full accent-accent"
      />
    </div>
  );
}
