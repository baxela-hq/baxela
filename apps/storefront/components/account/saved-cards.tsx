"use client";

import { useEffect, useState, type FormEvent } from "react";
import { useTranslations } from "next-intl";
import { toast } from "sonner";
import { PlusIcon, TrashIcon } from "@/components/ui/icons";
import { Input } from "@/components/ui/input";

// Mock UI awaiting backend card-storage endpoints — same as the mega-menu
// columns. Cards live in memory only and reset on reload, and no real card
// data is handled: adding keeps just the brand and a masked number.

type CardBrand = "visa" | "master_card" | "amex";

interface SavedCard {
  id: number;
  brand: CardBrand;
  maskedNumber: string;
}

// Seed matches the sample cards on the profile design screen.
const SEED_CARDS: SavedCard[] = [
  { id: 1, brand: "master_card", maskedNumber: "3456 XX78 9800 55X3" },
  { id: 2, brand: "visa", maskedNumber: "5677 3490 XX90 XX23" },
];

// Brand names are proper nouns, so they stay out of the locale files.
const BRAND_LABELS: Record<CardBrand, string> = {
  visa: "Visa Card",
  master_card: "Master Card",
  amex: "American Express",
};

function BrandMark({ brand }: { brand: CardBrand }) {
  if (brand === "visa") {
    return (
      <span
        aria-hidden="true"
        className="text-lg font-black italic tracking-tight text-[#1A1F71]"
      >
        VISA
      </span>
    );
  }
  if (brand === "amex") {
    return (
      <span
        aria-hidden="true"
        className="text-xs font-black italic tracking-tight text-[#016FD0]"
      >
        AMEX
      </span>
    );
  }
  return (
    <svg viewBox="0 0 32 20" aria-hidden="true" className="size-7">
      <circle cx="12" cy="10" r="8" fill="#EB001B" />
      <circle cx="20" cy="10" r="8" fill="#F79E1B" fillOpacity="0.9" />
    </svg>
  );
}

function brandFromNumber(digits: string): CardBrand | null {
  if (digits.startsWith("4")) return "visa";
  if (digits.startsWith("5")) return "master_card";
  if (digits.startsWith("3")) return "amex";
  return null;
}

/**
 * The "Saved Cards" tab: brand mark, label and masked number per row with
 * the design's delete chip, plus an in-memory add modal.
 */
export function SavedCards() {
  const t = useTranslations("account.account");

  const [cards, setCards] = useState<SavedCard[]>(SEED_CARDS);
  const [showForm, setShowForm] = useState(false);
  const [number, setNumber] = useState("");
  const [confirmingDeleteId, setConfirmingDeleteId] = useState<number | null>(
    null,
  );

  const closeForm = () => {
    setShowForm(false);
    setNumber("");
  };

  useEffect(() => {
    if (!showForm) return;

    const onKeyDown = (event: KeyboardEvent) => {
      if (event.key === "Escape") closeForm();
    };
    document.addEventListener("keydown", onKeyDown);
    document.body.style.overflow = "hidden";

    return () => {
      document.removeEventListener("keydown", onKeyDown);
      document.body.style.overflow = "";
    };
  }, [showForm]);

  const onAdd = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    const digits = number.replace(/\D/g, "");
    const brand = brandFromNumber(digits);
    if (digits.length < 12 || digits.length > 19 || brand === null) {
      toast.error(t("cards.messages.error.invalid"));
      return;
    }
    setCards((previous) => [
      ...previous,
      {
        id: Date.now(),
        brand,
        maskedNumber: `${digits.slice(0, 4)} XXXX XXXX ${digits.slice(-4)}`,
      },
    ]);
    closeForm();
  };

  return (
    <div>
      {!showForm ? (
        <button
          type="button"
          onClick={() => setShowForm(true)}
          className="inline-flex h-12 items-center justify-center gap-2 rounded-default bg-primary px-6 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90 rtl:normal-case rtl:tracking-normal"
        >
          <PlusIcon className="size-4" />
          {t("cards.actions.add")}
        </button>
      ) : null}

      {cards.length === 0 && !showForm ? (
        <div className="py-16 text-center">
          <p className="text-base text-foreground rtl:normal-case rtl:tracking-normal">
            {t("cards.texts.empty")}
          </p>
          <p className="mt-2 text-sm text-secondary-text rtl:normal-case rtl:tracking-normal">
            {t("cards.texts.empty_hint")}
          </p>
        </div>
      ) : null}

      {cards.length > 0 ? (
        <ul className="mt-2 divide-y divide-border-light">
          {cards.map((card) => (
            <li
              key={card.id}
              className="flex flex-wrap items-center gap-4 py-6 first:pt-4"
            >
              <span
                aria-hidden="true"
                className="grid size-14 shrink-0 place-items-center rounded-default bg-muted"
              >
                <BrandMark brand={card.brand} />
              </span>
              <div className="min-w-0 flex-1">
                <p className="text-lg font-bold text-foreground rtl:normal-case rtl:tracking-normal">
                  {BRAND_LABELS[card.brand]}
                </p>
                <p className="mt-1 text-sm text-secondary-text">
                  {card.maskedNumber}
                </p>
              </div>
              {confirmingDeleteId === card.id ? (
                <div className="flex items-center gap-2">
                  <button
                    type="button"
                    onClick={() => {
                      setCards((previous) =>
                        previous.filter((entry) => entry.id !== card.id),
                      );
                      setConfirmingDeleteId(null);
                    }}
                    className="inline-flex h-9 items-center justify-center gap-2 rounded-default bg-red-500 px-4 text-sm font-medium text-white transition-colors hover:bg-red-600 rtl:normal-case rtl:tracking-normal"
                  >
                    <TrashIcon className="size-4" />
                    {t("cards.actions.confirm_delete")}
                  </button>
                  <button
                    type="button"
                    onClick={() => setConfirmingDeleteId(null)}
                    className="inline-flex h-9 items-center justify-center rounded-default border border-border bg-white px-4 text-sm font-medium text-foreground transition-colors hover:bg-muted rtl:normal-case rtl:tracking-normal"
                  >
                    {t("cards.actions.keep")}
                  </button>
                </div>
              ) : (
                <button
                  type="button"
                  onClick={() => setConfirmingDeleteId(card.id)}
                  className="inline-flex h-9 items-center justify-center gap-2 rounded-default bg-red-50 px-4 text-sm font-medium text-red-600 transition-colors hover:bg-red-100 rtl:normal-case rtl:tracking-normal"
                >
                  <TrashIcon className="size-4" />
                  {t("cards.actions.delete")}
                </button>
              )}
            </li>
          ))}
        </ul>
      ) : null}

      {showForm ? (
        <div
          role="dialog"
          aria-modal="true"
          aria-label={t("cards.texts.add_title")}
          className="fixed inset-0 z-50 overflow-y-auto"
        >
          <div
            aria-hidden="true"
            onClick={closeForm}
            className="fixed inset-0 bg-foreground/40"
          />
          <div className="flex min-h-full items-center justify-center p-4">
            <div className="relative w-full max-w-md rounded-default bg-white p-6 sm:p-8">
              <h2 className="text-xl font-bold text-foreground rtl:normal-case rtl:tracking-normal">
                {t("cards.texts.add_title")}
              </h2>

              <form className="mt-6 flex flex-col gap-5" onSubmit={onAdd}>
                <Input
                  required
                  inputMode="numeric"
                  autoComplete="off"
                  label={t("cards.labels.number")}
                  placeholder={t("cards.placeholders.number")}
                  value={number}
                  onChange={(event) => setNumber(event.target.value)}
                />
                <div className="mt-2 flex items-center gap-3">
                  <button
                    type="button"
                    onClick={closeForm}
                    className="h-12 flex-1 rounded-default bg-muted text-sm font-medium text-foreground transition-colors hover:bg-border rtl:normal-case rtl:tracking-normal"
                  >
                    {t("cards.actions.cancel")}
                  </button>
                  <button
                    type="submit"
                    className="h-12 flex-1 rounded-default bg-primary text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90 rtl:normal-case rtl:tracking-normal"
                  >
                    {t("cards.actions.add")}
                  </button>
                </div>
              </form>
            </div>
          </div>
        </div>
      ) : null}
    </div>
  );
}
