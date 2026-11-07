// Image variant selection helpers (gap-C3): only completed variants are
// advertised by the API; anything missing falls back to the original URL.
// srcset descriptors use the variant's ACTUAL intrinsic width — presets
// never upscale, so a 300px original's thumb is 300px wide.

import type { ApiImageVariants } from "@/lib/api/types";

export function variantUrl(
  variants: ApiImageVariants | undefined | null,
  name: string,
  fallback: string | null | undefined,
): string | null {
  return variants?.[name]?.url ?? fallback ?? null;
}

/**
 * srcset built from the named variants in the given order, each with its
 * real width descriptor; null when fewer than two widths are available
 * (a single candidate needs no srcset).
 */
export function variantSrcSet(
  variants: ApiImageVariants | undefined | null,
  names: string[],
): string | null {
  const entries = names
    .map((name) => ({ variant: variants?.[name] }))
    .filter((e): e is { variant: ApiImageVariants[string] } => Boolean(e.variant));

  if (entries.length < 2) {
    return null;
  }

  return entries
    .map((e) => `${e.variant.url} ${e.variant.width}w`)
    .join(", ");
}
