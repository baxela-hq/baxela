"use client";

import { useEffect, useRef, useState, type FormEvent } from "react";
import { useTranslations } from "next-intl";
import { useRouter } from "@/i18n/navigation";

import type { NavItem } from "@/lib/menu";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { CloseIcon, SearchIcon } from "@/components/ui/icons";
import { Link } from "@/i18n/navigation";
import { api } from "@/lib/api/client";
import type { Paginated, ApiProduct } from "@/lib/api/types";

/**
 * Search trigger + overlay panel for the header. Mirrors the MobileMenu
 * overlay pattern: always-mounted panel with CSS transitions, inert when
 * closed, Escape to close and body scroll lock while open. Submitting
 * navigates to the products page filtered by the `q` search parameter.
 * The popular-category shortcuts come from the header Menu API.
 *
 * Instant suggestions (alpha.3): typing ≥2 characters queries the same
 * products endpoint (per_page=5) after a 300ms debounce and renders the
 * top results inline — identical search semantics to the full results
 * page. Stale-response safety: an AbortController cancels in-flight
 * requests (dialog close included) and a monotonic request id is the
 * final render condition — only the response of the LATEST submitted
 * query may update the panel, even if a raced request completes last.
 */
const DEBOUNCE_MS = 300;
const MIN_QUERY_LENGTH = 2;
const SUGGESTION_COUNT = 5;

export function SearchMenu({ categories }: { categories: NavItem[] }) {
  const [open, setOpen] = useState(false);
  const [query, setQuery] = useState("");
  const [suggestions, setSuggestions] = useState<ApiProduct[]>([]);
  const [suggesting, setSuggesting] = useState(false);
  const [suggestError, setSuggestError] = useState(false);
  const inputRef = useRef<HTMLInputElement>(null);
  const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const abortRef = useRef<AbortController | null>(null);
  const requestSeqRef = useRef(0);
  const router = useRouter();
  const t = useTranslations("shared.layout");

  useEffect(() => {
    if (!open) return;

    const onKeyDown = (event: KeyboardEvent) => {
      if (event.key === "Escape") setOpen(false);
    };
    document.addEventListener("keydown", onKeyDown);
    document.body.style.overflow = "hidden";

    return () => {
      document.removeEventListener("keydown", onKeyDown);
      document.body.style.overflow = "";
    };
  }, [open]);

  useEffect(() => {
    if (open) inputRef.current?.focus();
  }, [open]);

  const close = () => {
    setOpen(false);
    // In-flight suggestion requests are cancelled with the dialog: the
    // response of a closed panel must never paint or race a later one.
    abortRef.current?.abort();
    abortRef.current = null;
    if (debounceRef.current !== null) {
      clearTimeout(debounceRef.current);
      debounceRef.current = null;
    }
    requestSeqRef.current += 1;
    setSuggesting(false);
    setSuggestions([]);
    setSuggestError(false);
  };

  const querySuggestions = (term: string) => {
    const trimmed = term.trim();

    // Below the minimum length: clear suggestions and stop.
    if (trimmed.length < MIN_QUERY_LENGTH) {
      abortRef.current?.abort();
      requestSeqRef.current += 1;
      setSuggestions([]);
      setSuggesting(false);
      setSuggestError(false);

      return;
    }

    const requestId = ++requestSeqRef.current;
    setSuggesting(true);
    setSuggestError(false);

    abortRef.current?.abort();
    const controller = new AbortController();
    abortRef.current = controller;

    api
      .get<Paginated<ApiProduct>>(
        `/catalog/public/products?per_page=${SUGGESTION_COUNT}&filter[title]=${encodeURIComponent(trimmed)}`,
        { signal: controller.signal },
      )
      .then((page) => {
        // Only the response of the latest submitted query may update the
        // panel (stale-response protection beyond abort alone).
        if (requestId !== requestSeqRef.current) return;
        setSuggestions(page.data);
        setSuggesting(false);
      })
      .catch((error: unknown) => {
        if (controller.signal.aborted || requestId !== requestSeqRef.current) return;
        setSuggestions([]);
        setSuggestError(true);
        setSuggesting(false);
        void error;
      });
  };

  const onQueryChange = (event: React.ChangeEvent<HTMLInputElement>) => {
    const value = event.target.value;
    setQuery(value);

    if (debounceRef.current !== null) clearTimeout(debounceRef.current);
    if (value.trim().length < MIN_QUERY_LENGTH) {
      abortRef.current?.abort();
      requestSeqRef.current += 1;
      setSuggestions([]);
      setSuggesting(false);
      setSuggestError(false);

      return;
    }

    debounceRef.current = setTimeout(() => querySuggestions(value), DEBOUNCE_MS);
  };

  const onSubmit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    const term = query.trim();
    close();
    router.push(term ? `/products?q=${encodeURIComponent(term)}` : "/products");
  };

  const hasSuggestions = suggestions.length > 0;
  const hasSuggestionState = suggesting || suggestError || hasSuggestions;

  return (
    <div>
      <button
        type="button"
        aria-label={t("header.actions.search")}
        aria-expanded={open}
        aria-controls="search-menu"
        onClick={() => setOpen(true)}
        className="rounded-default p-2.5 text-foreground transition-colors hover:bg-muted"
      >
        <SearchIcon className="size-5" />
      </button>

      <div
        aria-hidden="true"
        onClick={close}
        className={`fixed inset-0 z-50 bg-foreground/40 transition-opacity duration-200 ${
          open ? "opacity-100" : "pointer-events-none opacity-0"
        }`}
      />

      {/* Always mounted so the fade/slide transition runs both ways; inert
          keeps the closed panel out of the tab order and accessibility tree. */}
      <div
        id="search-menu"
        role="dialog"
        aria-modal="true"
        aria-label={t("search.labels.dialog")}
        inert={!open}
        className={`fixed left-1/2 top-24 z-50 w-[min(92vw,40rem)] -translate-x-1/2 rounded-default border border-border-light bg-background shadow-[0_24px_48px_-24px_rgba(23,23,23,0.15)] transition duration-200 ${
          open
            ? "translate-y-0 opacity-100"
            : "pointer-events-none -translate-y-2 opacity-0"
        }`}
      >
        <div className="flex items-center justify-between border-b border-border-light px-6 py-4">
          <p className="text-base font-medium text-foreground rtl:normal-case rtl:tracking-normal">
            {t("search.texts.title")}
          </p>
          <button
            type="button"
            aria-label={t("search.actions.close")}
            onClick={close}
            className="rounded-default p-2.5 text-foreground transition-colors hover:bg-muted"
          >
            <CloseIcon className="size-5" />
          </button>
        </div>

        <form onSubmit={onSubmit} className="flex gap-3 px-6 pt-6">
          <Input
            ref={inputRef}
            type="search"
            name="q"
            value={query}
            onChange={onQueryChange}
            placeholder={t("search.placeholders.query")}
            aria-label={t("search.labels.input")}
            className="h-12"
            icon={<SearchIcon />}
          />
          <Button type="submit" fullWidth={false} className="h-12 self-center">
            {t("search.actions.submit")}
          </Button>
        </form>

        {hasSuggestionState ? (
          <div className="border-t border-border-light px-6 py-4">
            {suggesting ? (
              <p
                aria-live="polite"
                className="text-sm text-secondary-text rtl:normal-case rtl:tracking-normal"
              >
                {t("search.texts.suggesting")}
              </p>
            ) : suggestError ? (
              <p
                aria-live="polite"
                className="text-sm text-secondary-text rtl:normal-case rtl:tracking-normal"
              >
                {t("search.texts.suggest_error")}
              </p>
            ) : hasSuggestions ? (
              <ul className="divide-y divide-border-light">
                {suggestions.map((product) => (
                  <li key={product.id}>
                    <Link
                      href={`/products/${product.slug}`}
                      onClick={close}
                      className="flex items-center justify-between gap-3 px-2 py-2.5 text-sm text-foreground transition-colors hover:bg-muted"
                    >
                      <span className="truncate">{product.title}</span>
                      <SearchIcon className="size-4 shrink-0 text-secondary-text" />
                    </Link>
                  </li>
                ))}
              </ul>
            ) : (
              <p
                aria-live="polite"
                className="text-sm text-secondary-text rtl:normal-case rtl:tracking-normal"
              >
                {t("search.texts.no_suggestions")}
              </p>
            )}
          </div>
        ) : null}

        {categories.length > 0 ? (
          <div className="px-6 pb-6 pt-6">
            <p className="text-sm font-semibold uppercase tracking-wide text-secondary-text rtl:normal-case rtl:tracking-normal">
              {t("search.texts.popular_categories")}
            </p>
            <div className="mt-4 flex flex-wrap gap-2">
              {categories.map((category) => (
                <Link
                  key={category.href}
                  href={category.href}
                  onClick={close}
                  className="rounded-default border border-border px-4 py-2 text-sm text-foreground transition-colors hover:border-primary hover:text-accent"
                >
                  {category.label}
                </Link>
              ))}
            </div>
          </div>
        ) : null}
      </div>
    </div>
  );
}
