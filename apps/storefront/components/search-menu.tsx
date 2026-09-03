"use client";

import { useEffect, useRef, useState, type FormEvent } from "react";
import { useTranslations } from "next-intl";
import { useRouter } from "@/i18n/navigation";

import { MEGA_MENU_COLUMNS } from "@/components/mega-menu-columns";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { CloseIcon, SearchIcon } from "@/components/ui/icons";
import { Link } from "@/i18n/navigation";

/**
 * Search trigger + overlay panel for the header. Mirrors the MobileMenu
 * overlay pattern: always-mounted panel with CSS transitions, inert when
 * closed, Escape to close and body scroll lock while open. Submitting
 * navigates to the products page filtered by the `q` search parameter.
 */
export function SearchMenu() {
  const [open, setOpen] = useState(false);
  const [query, setQuery] = useState("");
  const inputRef = useRef<HTMLInputElement>(null);
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

  const close = () => setOpen(false);

  const onSubmit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    const term = query.trim();
    close();
    router.push(term ? `/products?q=${encodeURIComponent(term)}` : "/products");
  };

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
        className={`fixed left-1/2 top-24 z-50 w-[min(92vw,40rem)] -translate-x-1/2 rounded-default border border-border-light bg-white shadow-[0_24px_48px_-24px_rgba(23,23,23,0.15)] transition duration-200 ${
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
            onChange={(event) => setQuery(event.target.value)}
            placeholder={t("search.placeholders.query")}
            aria-label={t("search.labels.input")}
            className="h-12"
            icon={<SearchIcon />}
          />
          <Button type="submit" fullWidth={false} className="h-12 self-center">
            {t("search.actions.submit")}
          </Button>
        </form>

        <div className="px-6 pb-6 pt-6">
          <p className="text-sm font-semibold uppercase tracking-wide text-secondary-text rtl:normal-case rtl:tracking-normal">
            {t("search.texts.popular_categories")}
          </p>
          <div className="mt-4 flex flex-wrap gap-2">
            {MEGA_MENU_COLUMNS.map((column) => (
              <Link
                key={column.title}
                href={column.links[0].href}
                onClick={close}
                className="rounded-default border border-border px-4 py-2 text-sm text-foreground transition-colors hover:border-primary hover:text-accent"
              >
                {column.title}
              </Link>
            ))}
          </div>
        </div>
      </div>
    </div>
  );
}
