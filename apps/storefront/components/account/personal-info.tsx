"use client";

import { useState, type FormEvent } from "react";
import { useTranslations } from "next-intl";
import { toast } from "sonner";
import { useAuth } from "@/context/auth-context";
import { api, ApiError } from "@/lib/api/client";
import type { ApiProfile } from "@/lib/api/types";
import { PencilIcon } from "@/components/ui/icons";
import { Input } from "@/components/ui/input";

interface PersonalInfoProps {
  profile: ApiProfile;
  onSaved: (profile: ApiProfile) => void;
}

/**
 * The "Personal Information" tab. Only first/last name are editable — the
 * user_users table has no phone/address columns (addresses live in the
 * address book), and there is no email-change endpoint, so the email is
 * shown read-only from the session.
 */
export function PersonalInfo({ profile, onSaved }: PersonalInfoProps) {
  const t = useTranslations("account.account");
  const tCommon = useTranslations("shared.common");
  const { user, token } = useAuth();

  const [editing, setEditing] = useState(false);
  const [pending, setPending] = useState(false);
  const [form, setForm] = useState({
    first_name: profile.first_name ?? "",
    last_name: profile.last_name ?? "",
  });

  const onSubmit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setPending(true);
    try {
      const updated = await api.patch<ApiProfile>(
        "/user/user/profile",
        { first_name: form.first_name, last_name: form.last_name },
        { token },
      );
      onSaved(updated);
      setEditing(false);
      toast.success(t("profile.messages.success.saved"));
    } catch (cause) {
      toast.error(
        cause instanceof ApiError
          ? cause.message
          : t("profile.messages.error.save_failed"),
      );
    } finally {
      setPending(false);
    }
  };

  const initials = `${form.first_name}${form.last_name}`
    .trim()
    .split(/\s+/)
    .map((part) => part[0])
    .join("")
    .slice(0, 2)
    .toUpperCase();

  return (
    <div className="rounded-default border border-border-light bg-white p-6 sm:p-8">
      <div className="flex flex-wrap items-center justify-between gap-4">
        <span
          aria-hidden="true"
          className="grid size-20 place-items-center rounded-full bg-muted text-lg font-semibold text-foreground"
        >
          {initials}
        </span>
        {editing ? null : (
          <button
            type="button"
            onClick={() => setEditing(true)}
            className="inline-flex h-12 items-center justify-center gap-2 rounded-default bg-primary px-6 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90 rtl:normal-case rtl:tracking-normal"
          >
            <PencilIcon className="size-4" />
            {t("profile.actions.edit")}
          </button>
        )}
      </div>

      <form
        className="mt-10 grid grid-cols-1 gap-6 sm:grid-cols-2"
        onSubmit={onSubmit}
      >
        <Input
          label={t("profile.labels.first_name")}
          placeholder={t("profile.labels.first_name")}
          value={form.first_name}
          disabled={!editing || pending}
          onChange={(event) =>
            setForm((current) => ({
              ...current,
              first_name: event.target.value,
            }))
          }
        />
        <Input
          label={t("profile.labels.last_name")}
          placeholder={t("profile.labels.last_name")}
          value={form.last_name}
          disabled={!editing || pending}
          onChange={(event) =>
            setForm((current) => ({ ...current, last_name: event.target.value }))
          }
        />
        <div className="sm:col-span-2">
          <Input
            type="email"
            label={t("profile.labels.email_address")}
            placeholder={t("profile.labels.email_address")}
            value={user?.email ?? ""}
            disabled
          />
        </div>

        {editing ? (
          <div className="flex flex-wrap items-center gap-3 sm:col-span-2">
            <button
              type="submit"
              disabled={pending}
              className="inline-flex h-12 items-center justify-center rounded-default bg-primary px-6 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90 disabled:opacity-60 rtl:normal-case rtl:tracking-normal"
            >
              {pending
                ? tCommon("messages.info.loading")
                : t("profile.actions.save")}
            </button>
            <button
              type="button"
              disabled={pending}
              onClick={() => {
                setEditing(false);
                setForm({
                  first_name: profile.first_name ?? "",
                  last_name: profile.last_name ?? "",
                });
              }}
              className="inline-flex h-12 items-center justify-center rounded-default border border-border bg-white px-6 text-sm font-medium text-foreground transition-colors hover:bg-muted rtl:normal-case rtl:tracking-normal"
            >
              {t("profile.actions.cancel_edit")}
            </button>
          </div>
        ) : null}
      </form>
    </div>
  );
}
