import Link from "next/link";

import { StarSolidIcon } from "@/components/ui/icons";
import { cn } from "@/lib/utils";

export interface Product {
  id: number;
  name: string;
  price: number;
}

export interface ProductCardProps {
  product: Product;
  className?: string;
}

export default function ProductCard({ product, className }: ProductCardProps) {
  return (
    <Link href={`/products/${product.id}`} className={cn("group block", className)}>
      <div className="flex aspect-square w-full items-center justify-center rounded-default bg-muted transition-colors group-hover:bg-border-light">
        <span className="text-sm text-secondary-text">{product.name}</span>
      </div>
      <div className="mt-4">
        <h3 className="text-base font-medium text-foreground">{product.name}</h3>
        <div className="mt-1 flex items-center justify-between">
          <span className="text-sm font-semibold text-foreground">
            ${product.price.toFixed(2)}
          </span>
          <span className="flex items-center gap-1 text-sm text-secondary-text">
            <StarSolidIcon className="size-4 text-accent" />
            4.8
          </span>
        </div>
      </div>
    </Link>
  );
}
