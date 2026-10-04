"use client";

import { useState } from "react";
import { useTranslations } from "next-intl";
import {
  TicketList,
  TicketStatusFilterTabs,
  type TicketStatusFilter,
} from "@/components/account/tickets/ticket-list";
import { Link } from "@/i18n/navigation";

export default function ProfileTicketsPage() {
  const t = useTranslations("account.tickets");
  const [statusFilter, setStatusFilter] = useState<TicketStatusFilter>("all");

  return (
    <div>
      <div className="flex flex-wrap items-center justify-between gap-4">
        <h1 className="text-2xl font-semibold text-foreground rtl:normal-case rtl:tracking-normal">
          {t("labels.title")}
        </h1>
        <Link
          href="/profile/tickets/new"
          className="inline-flex h-12 items-center justify-center rounded-default bg-primary px-8 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90 rtl:normal-case rtl:tracking-normal"
        >
          {t("actions.new_ticket")}
        </Link>
      </div>

      <div className="mt-6">
        <TicketStatusFilterTabs
          value={statusFilter}
          onChange={setStatusFilter}
        />
      </div>

      <div className="mt-8">
        <TicketList statusFilter={statusFilter} />
      </div>
    </div>
  );
}
