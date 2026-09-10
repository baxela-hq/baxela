"use client";

import {
  useEffect,
  useId,
  useMemo,
  useRef,
  useState,
  type KeyboardEvent,
} from "react";
import { useTranslations } from "next-intl";
import { cn } from "@/lib/utils";
import {
  CheckIcon,
  ChevronDownIcon,
  SearchIcon,
} from "@/components/ui/icons";

export interface SearchableSelectOption {
  value: string;
  label: string;
}

export interface SearchableSelectProps {
  label?: string;
  options: SearchableSelectOption[];
  value: string;
  onChange: (value: string) => void;
  disabled?: boolean;
  error?: string | null;
  id?: string;
}

/**
 * A select box for longer option lists (e.g. countries): the trigger opens a
 * search input that filters options client-side by prefix — typing "ir" narrows
 * the list to Iran, Iraq, Ireland, … Options match when their label or value
 * starts with the query (case-insensitive), so country codes work too ("us"
 * finds United States). Keyboard support mirrors a native combobox: arrows
 * move, Enter picks, Escape closes the dropdown only.
 */
export function SearchableSelect({
  label,
  options,
  value,
  onChange,
  disabled,
  error,
  id,
}: SearchableSelectProps) {
  const tCommon = useTranslations("shared.common");
  const autoId = useId();
  const rootId = id ?? autoId;
  const listId = `${rootId}-listbox`;

  const [open, setOpen] = useState(false);
  const [query, setQuery] = useState("");
  const [activeIndex, setActiveIndex] = useState(0);

  const rootRef = useRef<HTMLDivElement>(null);
  const inputRef = useRef<HTMLInputElement>(null);
  const triggerRef = useRef<HTMLButtonElement>(null);
  const activeOptionRef = useRef<HTMLLIElement>(null);

  const selected = options.find((option) => option.value === value) ?? null;

  const filtered = useMemo(() => {
    const term = query.trim().toLowerCase();
    if (!term) return options;
    return options.filter(
      (option) =>
        option.label.toLowerCase().startsWith(term) ||
        option.value.toLowerCase().startsWith(term),
    );
  }, [options, query]);

  const closeList = (returnFocus = true) => {
    setOpen(false);
    setQuery("");
    if (returnFocus) triggerRef.current?.focus();
  };

  const select = (option: SearchableSelectOption) => {
    onChange(option.value);
    closeList();
  };

  const openList = () => {
    const selectedIdx = options.findIndex((option) => option.value === value);
    setActiveIndex(selectedIdx >= 0 ? selectedIdx : 0);
    setQuery("");
    setOpen(true);
  };

  // The dropdown closes on any pointer press outside the widget; presses on
  // the backdrop keep their own meaning (closing the dialog) via the mouse
  // event, not this handler.
  useEffect(() => {
    if (!open) return;

    const onPointerDown = (event: MouseEvent) => {
      if (!rootRef.current?.contains(event.target as Node)) closeList(false);
    };
    document.addEventListener("mousedown", onPointerDown);
    return () => document.removeEventListener("mousedown", onPointerDown);
  }, [open]);

  // Focus the search box each time the dropdown opens.
  useEffect(() => {
    if (open) inputRef.current?.focus();
  }, [open]);

  // Keep the highlighted option visible while arrowing through the list.
  useEffect(() => {
    activeOptionRef.current?.scrollIntoView({ block: "nearest" });
  }, [activeIndex, open]);

  const onSearchKeyDown = (event: KeyboardEvent<HTMLInputElement>) => {
    if (event.key === "ArrowDown") {
      event.preventDefault();
      if (filtered.length > 0) {
        setActiveIndex((current) => (current + 1) % filtered.length);
      }
    } else if (event.key === "ArrowUp") {
      event.preventDefault();
      if (filtered.length > 0) {
        setActiveIndex(
          (current) => (current - 1 + filtered.length) % filtered.length,
        );
      }
    } else if (event.key === "Enter") {
      // Keep the surrounding form from submitting on Enter.
      event.preventDefault();
      const option = filtered[activeIndex];
      if (option) select(option);
    } else if (event.key === "Escape") {
      // Escape dismisses the dropdown without dismissing the dialog behind
      // it — stopImmediatePropagation keeps document-level Escape handlers
      // (the address modal) from firing as well.
      event.preventDefault();
      event.stopPropagation();
      event.nativeEvent.stopImmediatePropagation();
      closeList();
    } else if (event.key === "Tab") {
      setOpen(false);
      setQuery("");
    }
  };

  return (
    <div className="w-full">
      {label ? (
        <label
          htmlFor={rootId}
          className="mb-2 block text-sm text-secondary-text rtl:normal-case rtl:tracking-normal"
        >
          {label}
        </label>
      ) : null}
      <div ref={rootRef} className="relative">
        {open ? (
          <>
            <span className="pointer-events-none absolute start-4 top-1/2 -translate-y-1/2 text-secondary-text">
              <SearchIcon className="size-5" />
            </span>
            <input
              ref={inputRef}
              id={rootId}
              role="combobox"
              type="text"
              autoComplete="off"
              aria-expanded="true"
              aria-controls={listId}
              aria-autocomplete="list"
              aria-activedescendant={
                filtered.length > 0
                  ? `${listId}-option-${activeIndex}`
                  : undefined
              }
              aria-invalid={error ? true : undefined}
              value={query}
              disabled={disabled}
              placeholder={tCommon("form.placeholders.search")}
              onChange={(event) => {
                setQuery(event.target.value);
                setActiveIndex(0);
              }}
              onKeyDown={onSearchKeyDown}
              className={cn(
                "h-14 w-full rounded-default border bg-white ps-12 pe-4 text-base text-foreground outline-none transition-colors placeholder:text-secondary-text",
                error
                  ? "border-red-500"
                  : "border-border focus:border-primary",
              )}
            />
          </>
        ) : (
          <button
            ref={triggerRef}
            type="button"
            id={rootId}
            disabled={disabled}
            aria-haspopup="listbox"
            aria-expanded="false"
            onClick={openList}
            className={cn(
              "flex h-14 w-full items-center justify-between gap-2 rounded-default border bg-white px-4 text-start text-base outline-none transition-colors",
              error
                ? "border-red-500"
                : "border-border focus:border-primary",
              disabled && "opacity-60",
            )}
          >
            <span
              className={cn(
                "truncate rtl:normal-case rtl:tracking-normal",
                selected ? "text-foreground" : "text-secondary-text",
              )}
            >
              {selected
                ? selected.label
                : tCommon("form.placeholders.select")}
            </span>
            <ChevronDownIcon className="size-5 shrink-0 text-secondary-text" />
          </button>
        )}

        {open ? (
          <ul
            role="listbox"
            id={listId}
            aria-label={label}
            className="absolute inset-x-0 top-full z-10 mt-2 max-h-60 overflow-y-auto rounded-default border border-border bg-white py-2 shadow-lg"
          >
            {filtered.length === 0 ? (
              <li className="px-4 py-2 text-sm text-secondary-text rtl:normal-case rtl:tracking-normal">
                {tCommon("messages.info.no_results")}
              </li>
            ) : (
              filtered.map((option, index) => {
                const isActive = index === activeIndex;
                const isSelected = option.value === value;
                return (
                  <li
                    key={option.value}
                    id={`${listId}-option-${index}`}
                    ref={isActive ? activeOptionRef : undefined}
                    role="option"
                    aria-selected={isSelected}
                    onMouseEnter={() => setActiveIndex(index)}
                    // Keep the search input focused so its onKeyDown keeps
                    // working while clicking through the list.
                    onMouseDown={(event) => event.preventDefault()}
                    onClick={() => select(option)}
                    className={cn(
                      "flex cursor-pointer items-center justify-between gap-2 px-4 py-2.5 text-sm rtl:normal-case rtl:tracking-normal",
                      isActive
                        ? "bg-muted text-foreground"
                        : "text-secondary-text",
                    )}
                  >
                    <span className="truncate">{option.label}</span>
                    {isSelected ? (
                      <CheckIcon className="size-4 shrink-0 text-accent" />
                    ) : null}
                  </li>
                );
              })
            )}
          </ul>
        ) : null}
      </div>
      {error ? (
        <p className="mt-2 text-sm text-red-600 rtl:normal-case rtl:tracking-normal">
          {error}
        </p>
      ) : null}
    </div>
  );
}
