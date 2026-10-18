"use client";

import { useTranslations } from "next-intl";

import { cn } from "@/lib/utils";
import { StarIcon, StarSolidIcon } from "@/components/ui/icons";

/**
 * Read-only average rating rendered as five stars. Fractional averages
 * round to the nearest star; callers decide whether to show the numeric
 * value alongside.
 */
export function StarRating({
  rating,
  size = "size-3.5",
  className,
}: {
  /** Backend decimals arrive as strings ("4.50"); both forms are accepted. */
  rating: number | string | null;
  size?: string;
  className?: string;
}) {
  const t = useTranslations("catalog.product");

  if (rating === null) {
    return null;
  }

  const value = Number(rating);
  const rounded = Math.round(value);

  return (
    <span
      className={cn("inline-flex items-center gap-0.5", className)}
      role="img"
      aria-label={t("reviews.aria.average_rating", { rating: value })}
    >
      {[1, 2, 3, 4, 5].map((star) =>
        star <= rounded ? (
          <StarSolidIcon key={star} className={cn(size, "text-accent")} />
        ) : (
          <StarIcon key={star} className={cn(size, "text-secondary-text/50")} />
        ),
      )}
    </span>
  );
}

/** Interactive 1–5 star picker for the review form (radiogroup semantics). */
export function StarRatingInput({
  value,
  onChange,
  disabled = false,
}: {
  value: number;
  onChange: (value: number) => void;
  disabled?: boolean;
}) {
  const t = useTranslations("catalog.product");

  return (
    <div
      role="radiogroup"
      aria-label={t("reviews.form.labels.rating")}
      className="flex items-center gap-1"
    >
      {[1, 2, 3, 4, 5].map((star) => (
        <button
          key={star}
          type="button"
          role="radio"
          aria-checked={value === star}
          aria-label={t("reviews.form.labels.rating_value", { rating: star })}
          disabled={disabled}
          onClick={() => onChange(star)}
          className="rounded-default transition-transform hover:scale-110 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent disabled:pointer-events-none disabled:opacity-50"
        >
          {star <= value ? (
            <StarSolidIcon className="size-6 text-accent" />
          ) : (
            <StarIcon className="size-6 text-secondary-text" />
          )}
        </button>
      ))}
    </div>
  );
}
