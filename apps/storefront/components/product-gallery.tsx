"use client";

import { useState } from "react";
import { useTranslations } from "next-intl";
import { cn } from "@/lib/utils";
import type { ApiImage } from "@/lib/api/types";

export function ProductGallery({
  photos,
  title,
}: {
  photos: ApiImage[];
  title: string;
}) {
  const t = useTranslations("catalog.product");
  const [activeIndex, setActiveIndex] = useState(0);
  const active = photos[activeIndex] ?? null;

  return (
    <div className="space-y-4">
      <div
        className="flex aspect-square w-full items-center justify-center overflow-hidden rounded-default border border-border bg-muted"
        role="img"
        aria-label={t("labels.main_image", { name: title })}
      >
        {active ? (
          // Backend-served images — see product-card for the img rationale
          // eslint-disable-next-line @next/next/no-img-element
          <img
            src={active.url}
            alt={title}
            className="size-full object-cover"
          />
        ) : null}
      </div>
      {photos.length > 1 ? (
        <div className="grid grid-cols-4 gap-4">
          {photos.map((photo, index) => (
            <button
              key={photo.id}
              type="button"
              aria-pressed={index === activeIndex}
              aria-label={t("labels.thumbnail_image", {
                name: title,
                index: index + 1,
              })}
              onClick={() => setActiveIndex(index)}
              className={cn(
                "aspect-square cursor-pointer overflow-hidden rounded-default border bg-muted transition-colors",
                index === activeIndex
                  ? "border-accent"
                  : "border-border hover:border-border-light",
              )}
            >
              {/* eslint-disable-next-line @next/next/no-img-element */}
              <img
                src={photo.url}
                alt=""
                className="size-full object-cover"
                loading="lazy"
              />
            </button>
          ))}
        </div>
      ) : null}
    </div>
  );
}
