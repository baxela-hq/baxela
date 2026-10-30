"use client";

import { useEffect, useRef, useState } from "react";
import { CheckIcon, ChevronDownIcon } from "@/components/ui/icons";
import { useLocale, useTranslations } from "next-intl";
import { usePathname, useRouter } from "@/i18n/navigation";
import { locales, nativeName } from "@/lib/i18n";
import type { ApiActiveLanguage } from "@/lib/api/types";

type LanguageSwitcherProps = {
  /**
   * Languages the store actually offers (admin-controlled), resolved
   * server-side. Intersected with the build-time routing locales — an
   * activated language without UI translations stays hidden until its
   * message files ship. Falls back to the full locale list when null.
   */
  supported?: ApiActiveLanguage[];
};

/**
 * Switches the active locale, keeping the current path. With two effective
 * locales it stays a compact toggle showing the language it switches TO;
 * with more it becomes a dropdown.
 */
export function LanguageSwitcher({ supported }: LanguageSwitcherProps) {
  const locale = useLocale();
  const t = useTranslations("shared.layout");
  const pathname = usePathname();
  const router = useRouter();
  const [open, setOpen] = useState(false);
  const rootRef = useRef<HTMLDivElement>(null);

  // Admin-controlled offering ∩ locales this build can actually route
  const offered = (supported ?? []).map((l) => l.code).filter((code) => locales.includes(code as never));
  const effective = offered.length > 0 ? offered : locales;
  const nameOf = (code: string) =>
    supported?.find((l) => l.code === code)?.native_name || nativeName(code);

  useEffect(() => {
    if (!open) return;
    const onPointerDown = (event: MouseEvent) => {
      if (!rootRef.current?.contains(event.target as Node)) setOpen(false);
    };
    document.addEventListener("mousedown", onPointerDown);
    return () => document.removeEventListener("mousedown", onPointerDown);
  }, [open]);

  if (effective.length <= 2) {
    const nextLocale = effective.find((candidate) => candidate !== locale) ?? locale;
    return (
      <button
        type="button"
        aria-label={t("header.actions.language")}
        onClick={() => router.replace(pathname, { locale: nextLocale })}
        className="rounded-default px-2 py-1.5 text-xs font-medium text-foreground transition-colors hover:bg-muted"
      >
        {nameOf(nextLocale)}
      </button>
    );
  }

  const switchTo = (nextLocale: string) => {
    setOpen(false);
    if (nextLocale !== locale) router.replace(pathname, { locale: nextLocale });
  };

  return (
    <div ref={rootRef} className="relative">
      <button
        type="button"
        aria-label={t("header.actions.language")}
        aria-haspopup="listbox"
        aria-expanded={open}
        onClick={() => setOpen((value) => !value)}
        className="flex items-center gap-1 rounded-default px-2 py-1.5 text-xs font-medium text-foreground transition-colors hover:bg-muted"
      >
        {nameOf(locale)}
        <ChevronDownIcon className="size-3.5 text-secondary-text" />
      </button>
      {open ? (
        <ul
          role="listbox"
          aria-label={t("header.actions.language")}
          className="absolute end-0 top-full z-20 mt-1 min-w-32 rounded-default border border-border bg-background py-1 shadow-lg"
        >
          {effective.map((candidate) => (
            <li
              key={candidate}
              role="option"
              aria-selected={candidate === locale}
              onClick={() => switchTo(candidate)}
              className="flex cursor-pointer items-center justify-between gap-2 px-3 py-1.5 text-xs text-foreground transition-colors hover:bg-muted"
            >
              {nameOf(candidate)}
              {candidate === locale ? (
                <CheckIcon className="size-3.5 text-accent" />
              ) : null}
            </li>
          ))}
        </ul>
      ) : null}
    </div>
  );
}
