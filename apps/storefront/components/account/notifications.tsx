"use client";

import { useTranslations } from "next-intl";
import { BoxIcon, LockIcon, StarIcon } from "@/components/ui/icons";

// Mock UI awaiting a notification API — the backend already persists
// notifications (the notifications table is filled by event listeners),
// but no endpoint exposes them yet. Rows mirror the profile design screen.

type NotificationItemKey =
  | "profile_updated"
  | "order_placed"
  | "order_delivered"
  | "feedback_shared"
  | "password_updated";

type NotificationTime = { literal: string } | { relative: "just_now" | "yesterday" };

interface NotificationRow {
  id: number;
  itemKey: NotificationItemKey;
  /** Design shows the user's photo for personal events; we render initials. */
  withAvatar: boolean;
  time: NotificationTime;
}

const SEED_ROWS: NotificationRow[] = [
  { id: 1, itemKey: "profile_updated", withAvatar: true, time: { relative: "just_now" } },
  { id: 2, itemKey: "order_placed", withAvatar: false, time: { literal: "11:16 AM" } },
  { id: 3, itemKey: "order_delivered", withAvatar: false, time: { literal: "09:00 AM" } },
  { id: 4, itemKey: "feedback_shared", withAvatar: true, time: { relative: "yesterday" } },
  { id: 5, itemKey: "password_updated", withAvatar: false, time: { relative: "yesterday" } },
];

const ITEM_ICONS: Record<
  Exclude<NotificationItemKey, "profile_updated">,
  typeof BoxIcon
> = {
  order_placed: BoxIcon,
  order_delivered: BoxIcon,
  password_updated: LockIcon,
  feedback_shared: StarIcon,
};

interface NotificationsProps {
  initials: string;
}

/**
 * The "Notifications" tab: a flat feed of rows — avatar or icon tile, bold
 * title, description and the time on the side.
 */
export function Notifications({ initials }: NotificationsProps) {
  const t = useTranslations("account.account");

  const renderTime = (time: NotificationTime) =>
    "relative" in time ? t(`notifications.texts.${time.relative}`) : time.literal;

  return (
    <ul className="divide-y divide-border-light">
      {SEED_ROWS.map((row) => {
        const Icon =
          row.itemKey === "profile_updated" ? null : ITEM_ICONS[row.itemKey];

        return (
          <li key={row.id} className="flex items-center gap-4 py-6 first:pt-0">
            <span
              aria-hidden="true"
              className="grid size-12 shrink-0 place-items-center rounded-full bg-muted text-foreground"
            >
              {Icon ? (
                <Icon className="size-5" />
              ) : (
                <span className="text-sm font-semibold">{initials}</span>
              )}
            </span>
            <div className="min-w-0 flex-1">
              <p className="text-base font-bold text-foreground rtl:normal-case rtl:tracking-normal">
                {t(`notifications.items.${row.itemKey}.title`)}
              </p>
              <p className="mt-1 truncate text-sm text-secondary-text rtl:normal-case rtl:tracking-normal">
                {t(`notifications.items.${row.itemKey}.description`)}
              </p>
            </div>
            <p className="shrink-0 text-sm text-secondary-text">
              {renderTime(row.time)}
            </p>
          </li>
        );
      })}
    </ul>
  );
}
