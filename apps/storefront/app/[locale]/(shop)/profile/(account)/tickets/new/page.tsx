"use client";

import { useTranslations } from "next-intl";
import { TicketForm } from "@/components/account/tickets/ticket-form";

export default function ProfileNewTicketPage() {
  const t = useTranslations("account.tickets");

  return (
    <div>
      <h1 className="text-2xl font-semibold text-foreground rtl:normal-case rtl:tracking-normal">
        {t("labels.title")}
      </h1>
      <div className="mt-8">
        <TicketForm />
      </div>
    </div>
  );
}
