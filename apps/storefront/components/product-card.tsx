import { useFormatter } from "next-intl";

import { cn } from "@/lib/utils";
import { Link } from "@/i18n/navigation";
import type { ApiProduct } from "@/lib/api/types";

export interface ProductCardProps {
  product: ApiProduct;
  className?: string;
}

export default function ProductCard({ product, className }: ProductCardProps) {
  const format = useFormatter();

  return (
    <Link
      href={`/products/${product.id}`}
      className={cn("group block", className)}
    >
      <div className="flex aspect-square w-full items-center justify-center overflow-hidden rounded-default bg-muted transition-colors group-hover:bg-border-light">
        {product.image_url ? (
          // Backend-served images from arbitrary hosts — next/image would
          // need remotePatterns for every storage host, so use a plain img.
          // eslint-disable-next-line @next/next/no-img-element
          <img
            src={product.image_url}
            alt={product.title ?? ""}
            className="size-full object-cover"
            loading="lazy"
          />
        ) : (
          <span className="px-4 text-center text-sm text-secondary-text rtl:normal-case rtl:tracking-normal">
            {product.title}
          </span>
        )}
      </div>
      <div className="mt-4">
        <h3 className="text-base font-medium text-foreground rtl:normal-case rtl:tracking-normal">
          {product.title}
        </h3>
        <div className="mt-1 flex items-center justify-between">
          <span className="text-sm font-semibold text-foreground">
            {product.price !== null
              ? format.number(Number(product.price), {
                  style: "currency",
                  currency: "USD",
                })
              : null}
          </span>
          {product.compare_price !== null ? (
            <span className="text-sm text-secondary-text line-through rtl:normal-case rtl:tracking-normal">
              {format.number(Number(product.compare_price), {
                style: "currency",
                currency: "USD",
              })}
            </span>
          ) : null}
        </div>
      </div>
    </Link>
  );
}
