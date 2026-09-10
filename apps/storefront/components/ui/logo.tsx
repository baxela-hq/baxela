import { cn } from "@/lib/utils";
import { Link } from "@/i18n/navigation";

export function Logo({
  href = "/",
  className,
}: {
  href?: string;
  className?: string;
}) {
  return (
    <Link
      href={href}
      aria-label="Baxela home"
      className={cn("inline-flex items-center gap-2 sm:gap-3", className)}
    >
      <span className="grid size-9 place-items-center rounded-default bg-primary text-lg font-bold text-primary-foreground sm:size-10">
        B
      </span>
      <span className="text-xl font-bold leading-none tracking-tight text-primary sm:text-[26px]">
        Baxela
      </span>
    </Link>
  );
}
