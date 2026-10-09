import { useFormatter, useTranslations } from "next-intl";

import { cn } from "@/lib/utils";

/**
 * Sale chip showing the ACTUAL saving (compare_price − price), never the
 * promotion's configured value — a clamped fixed discount (e.g. $40 off a
 * $30 product) must never display more than it really takes off. Renders
 * nothing when there is no positive saving.
 */
export default function SaleBadge({
  price,
  comparePrice,
  className,
}: {
  price: string | null;
  comparePrice: string | null;
  className?: string;
}) {
  const t = useTranslations("components.sale-badge");
  const format = useFormatter();

  if (price === null || comparePrice === null) return null;

  const saving = Number(comparePrice) - Number(price);
  if (!(saving > 0)) return null;

  return (
    <span
      className={cn(
        "inline-flex items-center rounded-default bg-destructive/10 px-2 py-0.5 text-xs font-semibold text-destructive uppercase tracking-wide rtl:normal-case rtl:tracking-normal",
        className,
      )}
    >
      {t("save_amount", {
        amount: format.number(saving, { style: "currency", currency: "USD" }),
      })}
    </span>
  );
}
