"use client";

import { useState, type FormEvent } from "react";
import { useTranslations } from "next-intl";
import { toast } from "sonner";
import { ApiError } from "@/lib/api/client";
import { api } from "@/lib/api/client";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import {
  MailIcon,
  PhoneIcon,
  UserIcon,
} from "@/components/ui/icons";

const EMPTY_FORM = {
  name: "",
  email: "",
  phone: "",
  subject: "",
  message: "",
};

export function ContactForm() {
  const t = useTranslations("contact.contact");
  const tCommon = useTranslations("shared.common");

  const [form, setForm] = useState(EMPTY_FORM);
  const [pending, setPending] = useState(false);

  const set = (key: keyof typeof EMPTY_FORM) => (value: string) =>
    setForm((prev) => ({ ...prev, [key]: value }));

  const onSubmit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setPending(true);
    try {
      await api.post("contact/public/messages", {
        name: form.name,
        email: form.email,
        phone: form.phone || undefined,
        subject: form.subject,
        content: form.message,
      });
      toast.success(t("messages.success"));
      setForm(EMPTY_FORM);
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
    <form
      className="flex flex-col gap-6 rounded-default border border-border-light bg-white p-6 md:p-8"
      onSubmit={onSubmit}
    >
      <h2 className="text-xl font-semibold text-foreground rtl:normal-case rtl:tracking-normal">
        {t("form.title")}
      </h2>

      <Input
        type="text"
        required
        maxLength={255}
        label={t("form.labels.name")}
        placeholder={t("form.placeholders.name")}
        icon={<UserIcon />}
        value={form.name}
        onChange={(event) => set("name")(event.target.value)}
      />
      <Input
        type="email"
        required
        maxLength={255}
        label={t("form.labels.email")}
        placeholder={t("form.placeholders.email")}
        icon={<MailIcon />}
        value={form.email}
        onChange={(event) => set("email")(event.target.value)}
      />
      <Input
        type="tel"
        maxLength={30}
        label={t("form.labels.phone")}
        placeholder={t("form.placeholders.phone")}
        icon={<PhoneIcon />}
        value={form.phone}
        onChange={(event) => set("phone")(event.target.value)}
      />
      <Input
        type="text"
        required
        maxLength={255}
        label={t("form.labels.subject")}
        placeholder={t("form.placeholders.subject")}
        value={form.subject}
        onChange={(event) => set("subject")(event.target.value)}
      />
      <Textarea
        required
        maxLength={5000}
        label={t("form.labels.message")}
        placeholder={t("form.placeholders.message")}
        value={form.message}
        onChange={(event) => set("message")(event.target.value)}
      />

      <Button type="submit" disabled={pending}>
        {pending ? tCommon("messages.info.loading") : t("form.actions.submit")}
      </Button>
    </form>
  );
}
