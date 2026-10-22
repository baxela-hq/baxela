"use client";

import { useEffect, useRef, useState } from "react";
import { CheckIcon, ChevronDownIcon } from "@/components/ui/icons";
import { useTranslations } from "next-intl";
import { useRouter } from "next/navigation";

import { writeCurrencyPreference } from "@/lib/currency";
import { useCurrency } from "@/context/currency-context";

/**
 * Switches the visitor's display currency. The choice lives in the
 * baxela-currency cookie (server-readable, theme-mode pattern); after
 * writing it, router.refresh() re-renders the server components so every
 * price re-resolves against the new selection. Charging always stays in
 * the store's base currency.
 */
export function CurrencySwitcher() {
  const t = useTranslations("shared.layout");
  const router = useRouter();
  const { currency, currencies } = useCurrency();
  const [open, setOpen] = useState(false);
  const rootRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    if (!open) return;
    const onPointerDown = (event: MouseEvent) => {
      if (!rootRef.current?.contains(event.target as Node)) setOpen(false);
    };
    document.addEventListener("mousedown", onPointerDown);
    return () => document.removeEventListener("mousedown", onPointerDown);
  }, [open]);

  // A single active currency leaves nothing to switch between
  if (currencies.length < 2) return null;

  const switchTo = (code: string) => {
    setOpen(false);
    if (code === currency.code) return;
    writeCurrencyPreference(code);
    router.refresh();
  };

  return (
    <div ref={rootRef} className="relative">
      <button
        type="button"
        aria-label={t("header.actions.currency")}
        aria-haspopup="listbox"
        aria-expanded={open}
        onClick={() => setOpen((value) => !value)}
        className="flex items-center gap-1 rounded-default px-2 py-1.5 text-xs font-medium text-foreground transition-colors hover:bg-muted"
      >
        {currency.code}
        <ChevronDownIcon className="size-3.5 text-secondary-text" />
      </button>
      {open ? (
        <ul
          role="listbox"
          aria-label={t("header.actions.currency")}
          className="absolute end-0 top-full z-20 mt-1 min-w-36 rounded-default border border-border bg-background py-1 shadow-lg"
        >
          {currencies.map((row) => (
            <li
              key={row.code}
              role="option"
              aria-selected={row.code === currency.code}
              onClick={() => switchTo(row.code)}
              className="flex cursor-pointer items-center justify-between gap-2 px-3 py-1.5 text-xs text-foreground transition-colors hover:bg-muted"
            >
              <span className="flex items-center gap-1.5">
                <span className="font-medium">{row.code}</span>
                <span className="text-secondary-text">
                  {row.symbol ?? row.code}
                </span>
              </span>
              {row.code === currency.code ? (
                <CheckIcon className="size-3.5 text-accent" />
              ) : null}
            </li>
          ))}
        </ul>
      ) : null}
    </div>
  );
}
