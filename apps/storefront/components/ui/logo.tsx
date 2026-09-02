import Link from "next/link";
import { cn } from "@/lib/utils";

export function Logo({
  variant = "dark",
  href = "/",
  className,
}: {
  variant?: "dark" | "light";
  href?: string;
  className?: string;
}) {
  return (
    <Link
      href={href}
      aria-label="Baxela home"
      className={cn("inline-flex items-center gap-2 sm:gap-3", className)}
    >
      <span
        className={cn(
          "grid size-9 place-items-center rounded-default text-lg font-bold sm:size-10",
          variant === "dark"
            ? "bg-primary text-primary-foreground"
            : "bg-white text-primary",
        )}
      >
        B
      </span>
      <span
        className={cn(
          "text-xl font-bold leading-none tracking-tight sm:text-[26px]",
          variant === "dark" ? "text-primary" : "text-white",
        )}
      >
        Baxela
      </span>
    </Link>
  );
}
