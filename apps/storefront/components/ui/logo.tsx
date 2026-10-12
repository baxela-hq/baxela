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
      className={cn("inline-flex items-center", className)}
    >
      <img
        src="/brand/baxela-symbol-lockup.svg"
        alt="Baxela"
        width={327}
        height={96}
        className="h-9 w-auto sm:h-10"
      />
    </Link>
  );
}
