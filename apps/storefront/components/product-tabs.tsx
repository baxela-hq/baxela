"use client";

import { useState } from "react";

import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import {
  StarIcon,
  StarSolidIcon,
} from "@/components/ui/icons";

type TabKey = "description" | "additional" | "reviews";

const TABS: { key: TabKey; label: string }[] = [
  { key: "description", label: "Description" },
  { key: "additional", label: "Additional Information" },
  { key: "reviews", label: "Reviews (12)" },
];

const SPECS = [
  { label: "Material", value: "Premium cotton blend, 280 GSM" },
  { label: "Dimensions", value: "12 × 8 × 4 in" },
  { label: "Weight", value: "1.2 lbs" },
  { label: "Care", value: "Machine wash cold, tumble dry low" },
  { label: "Country of origin", value: "Portugal" },
];

const REVIEWS = [
  {
    name: "Amelia Chen",
    date: "March 14, 2024",
    rating: 5,
    title: "Exceeded my expectations",
    body: "The quality is outstanding and the fit is true to size. I have already ordered a second one in a different color.",
  },
  {
    name: "Daniel Okafor",
    date: "February 2, 2024",
    rating: 4,
    title: "Great everyday piece",
    body: "Comfortable and well made. Docked one star because shipping took a little longer than expected, but the product itself is excellent.",
  },
];

const RATING_DISTRIBUTION = [
  { stars: 5, percent: 72 },
  { stars: 4, percent: 18 },
  { stars: 3, percent: 6 },
  { stars: 2, percent: 3 },
  { stars: 1, percent: 1 },
];

function RatingStars({
  rating,
  size = "size-5",
}: {
  rating: number;
  size?: string;
}) {
  return (
    <span className="flex items-center gap-1" aria-label={`Rated ${rating} out of 5`}>
      {[1, 2, 3, 4, 5].map((star) =>
        star <= rating ? (
          <StarSolidIcon key={star} className={`${size} text-accent`} />
        ) : (
          <StarIcon key={star} className={`${size} text-accent`} />
        )
      )}
    </span>
  );
}

export function ProductTabs() {
  const [tab, setTab] = useState<TabKey>("description");

  return (
    <section className="mx-auto max-w-7xl px-6 pb-12">
      <div
        role="tablist"
        aria-label="Product information"
        className="flex flex-wrap items-center gap-6 border-b border-border-light"
      >
        {TABS.map(({ key, label }) => (
          <button
            key={key}
            type="button"
            role="tab"
            aria-selected={tab === key}
            onClick={() => setTab(key)}
            className={`-mb-px border-b-2 pb-4 text-sm font-medium transition-colors ${
              tab === key
                ? "border-primary text-foreground"
                : "border-transparent text-secondary-text hover:text-foreground"
            }`}
          >
            {label}
          </button>
        ))}
      </div>

      <div role="tabpanel" className="pt-8">
        {tab === "description" ? (
          <div className="max-w-3xl space-y-4 text-sm leading-6 text-secondary-text">
            <p>
              Built for everyday wear, this piece pairs a clean silhouette with
              materials chosen to keep their shape and feel wash after wash.
              Reinforced stitching at the seams adds durability without adding
              bulk.
            </p>
            <p>
              The relaxed fit moves with you through long days, while the
              understated design makes it easy to dress up or down. Every detail
              — from the hardware to the finish — is chosen to last.
            </p>
          </div>
        ) : null}

        {tab === "additional" ? (
          <dl className="max-w-3xl divide-y divide-border-light overflow-hidden rounded-default border border-border-light">
            {SPECS.map(({ label, value }) => (
              <div
                key={label}
                className="grid grid-cols-1 gap-1 px-6 py-4 sm:grid-cols-[200px_1fr] sm:gap-6"
              >
                <dt className="text-sm font-medium text-foreground">{label}</dt>
                <dd className="text-sm text-secondary-text">{value}</dd>
              </div>
            ))}
          </dl>
        ) : null}

        {tab === "reviews" ? (
          <div className="grid grid-cols-1 gap-12 lg:grid-cols-[320px_1fr]">
            <div>
              <p className="text-5xl font-bold text-foreground">4.8</p>
              <div className="mt-3">
                <RatingStars rating={5} />
              </div>
              <p className="mt-2 text-sm text-secondary-text">
                Based on 12 reviews
              </p>

              <div className="mt-6 space-y-3">
                {RATING_DISTRIBUTION.map(({ stars, percent }) => (
                  <div key={stars} className="flex items-center gap-3">
                    <span className="w-12 text-sm text-secondary-text">
                      {stars} star{stars === 1 ? "" : "s"}
                    </span>
                    <div
                      className="h-2 flex-1 overflow-hidden rounded-full bg-muted"
                      role="presentation"
                    >
                      <div
                        className="h-full rounded-full bg-accent"
                        style={{ width: `${percent}%` }}
                      />
                    </div>
                    <span className="w-10 text-right text-sm text-secondary-text">
                      {percent}%
                    </span>
                  </div>
                ))}
              </div>
            </div>

            <div>
              <ul className="space-y-6">
                {REVIEWS.map(({ name, date, rating, title, body }) => (
                  <li
                    key={name}
                    className="rounded-default border border-border-light p-6"
                  >
                    <div className="flex items-center justify-between gap-4">
                      <div className="flex items-center gap-3">
                        <span className="flex size-10 items-center justify-center rounded-full bg-muted text-sm font-medium text-foreground">
                          {name
                            .split(" ")
                            .map((part) => part[0])
                            .join("")}
                        </span>
                        <div>
                          <p className="text-sm font-medium text-foreground">
                            {name}
                          </p>
                          <p className="text-xs text-secondary-text">{date}</p>
                        </div>
                      </div>
                      <RatingStars rating={rating} size="size-4" />
                    </div>
                    <h3 className="mt-4 text-sm font-semibold text-foreground">
                      {title}
                    </h3>
                    <p className="mt-1 text-sm leading-6 text-secondary-text">
                      {body}
                    </p>
                  </li>
                ))}
              </ul>

              <form className="mt-10 max-w-xl">
                <h3 className="text-lg font-semibold text-foreground">
                  Write a review
                </h3>
                <div className="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2">
                  <Input type="text" placeholder="Your name" aria-label="Your name" />
                  <Input
                    type="email"
                    placeholder="Your email"
                    aria-label="Your email"
                  />
                </div>
                <div className="mt-4">
                  <Input
                    type="text"
                    placeholder="Rating (1–5)"
                    aria-label="Rating"
                  />
                </div>
                <textarea
                  rows={4}
                  placeholder="Share your thoughts"
                  aria-label="Your review"
                  className="mt-4 w-full rounded-default border border-border bg-white px-4 py-3 text-sm text-foreground placeholder:text-secondary-text focus:border-primary focus:outline-none"
                />
                <Button type="submit" className="mt-6">
                  Submit review
                </Button>
              </form>
            </div>
          </div>
        ) : null}
      </div>
    </section>
  );
}
