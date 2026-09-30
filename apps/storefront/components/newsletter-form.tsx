"use client";

import { useState, type FormEvent } from "react";
import { useTranslations } from "next-intl";
import { toast } from "sonner";
import { ApiError, api } from "@/lib/api/client";
import { Button } from "@/components/ui/button";

export function NewsletterForm() {
  const t = useTranslations("home.home.newsletter");

  const [email, setEmail] = useState("");
  const [pending, setPending] = useState(false);

  const onSubmit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setPending(true);
    try {
      await api.post("contact/public/newsletter-subscribers", { email });
      toast.success(t("messages.success"));
      setEmail("");
    } catch (cause) {
      toast.error(
        cause instanceof ApiError
          ? cause.message
          : t("messages.error_general"),
      );
    } finally {
      setPending(false);
    }
  };

  return (
    <form className="mt-8 flex gap-3" onSubmit={onSubmit}>
      <input
        type="email"
        name="email"
        required
        value={email}
        onChange={(event) => setEmail(event.target.value)}
        placeholder={t("placeholders.email")}
        aria-label={t("labels.email")}
        className="h-14 min-w-0 flex-1 rounded-default border border-border bg-white px-4 text-base outline-none transition-colors placeholder:text-secondary-text focus:border-primary"
      />
      <Button type="submit" disabled={pending} fullWidth={false} className="shrink-0 px-8">
        {t("actions.subscribe")}
      </Button>
    </form>
  );
}
