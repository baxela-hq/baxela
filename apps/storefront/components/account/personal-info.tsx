"use client";

import { useState, type FormEvent } from "react";
import { useTranslations } from "next-intl";
import { toast } from "sonner";
import { useAuth } from "@/context/auth-context";
import { api, ApiError } from "@/lib/api/client";
import type { ApiGender, ApiProfile } from "@/lib/api/types";
import { PencilIcon } from "@/components/ui/icons";
import { Input } from "@/components/ui/input";

export const EMPTY_PROFILE: ApiProfile = {
  full_name: null,
  display_name: null,
  bio: null,
  avatar: null,
  gender: null,
  date_of_birth: null,
};

const GENDERS: ApiGender[] = ["male", "female", "other"];

interface PersonalInfoProps {
  profile: ApiProfile;
  onSaved: (profile: ApiProfile) => void;
}

/**
 * The "Personal Information" tab, matching the user profile fields:
 * full_name (required) plus optional display name, bio, gender, date of
 * birth and avatar. The avatar is a URL column without an upload endpoint,
 * so it renders when set and falls back to initials. Email has no change
 * endpoint and is shown read-only from the session.
 */
export function PersonalInfo({ profile, onSaved }: PersonalInfoProps) {
  const t = useTranslations("account.account");
  const tCommon = useTranslations("shared.common");
  const { user, token } = useAuth();

  const [editing, setEditing] = useState(false);
  const [pending, setPending] = useState(false);
  const [form, setForm] = useState({
    full_name: profile.full_name ?? "",
    display_name: profile.display_name ?? "",
    bio: profile.bio ?? "",
    gender: profile.gender ?? ("" as ApiGender | ""),
    date_of_birth: profile.date_of_birth ?? "",
  });

  const fallbackName = user?.email.split("@")[0] ?? "";
  const displayName = form.full_name.trim() || fallbackName;
  const initials = displayName
    .split(/[\s._-]+/)
    .map((part) => part[0])
    .join("")
    .slice(0, 2)
    .toUpperCase();

  const resetForm = () => {
    setForm({
      full_name: profile.full_name ?? "",
      display_name: profile.display_name ?? "",
      bio: profile.bio ?? "",
      gender: profile.gender ?? ("" as ApiGender | ""),
      date_of_birth: profile.date_of_birth ?? "",
    });
  };

  const onSubmit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setPending(true);
    try {
      const updated = await api.patch<ApiProfile>(
        "/user/user/profile",
        {
          full_name: form.full_name.trim(),
          display_name: form.display_name.trim() || null,
          bio: form.bio.trim() || null,
          gender: form.gender || null,
          date_of_birth: form.date_of_birth || null,
        },
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

  return (
    <div className="rounded-default border border-border-light bg-white p-6 sm:p-8">
      <div className="flex flex-wrap items-center justify-between gap-4">
        {profile.avatar ? (
          // The avatar column holds a plain URL; next/image would need the
          // host whitelisted, so render it directly.
          // eslint-disable-next-line @next/next/no-img-element
          <img
            src={profile.avatar}
            alt={displayName}
            className="size-20 rounded-full object-cover"
          />
        ) : (
          <span
            aria-hidden="true"
            className="grid size-20 place-items-center rounded-full bg-muted text-lg font-semibold text-foreground"
          >
            {initials}
          </span>
        )}
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
          required
          label={t("profile.labels.full_name")}
          placeholder={t("profile.labels.full_name")}
          value={form.full_name}
          disabled={!editing || pending}
          onChange={(event) =>
            setForm((current) => ({
              ...current,
              full_name: event.target.value,
            }))
          }
        />
        <Input
          label={t("profile.labels.display_name")}
          placeholder={t("profile.labels.display_name")}
          value={form.display_name}
          disabled={!editing || pending}
          onChange={(event) =>
            setForm((current) => ({
              ...current,
              display_name: event.target.value,
            }))
          }
        />
        <div>
          <label
            htmlFor="account-gender"
            className="mb-2 block text-sm text-secondary-text rtl:normal-case rtl:tracking-normal"
          >
            {t("profile.labels.gender")}
          </label>
          <select
            id="account-gender"
            value={form.gender}
            disabled={!editing || pending}
            onChange={(event) =>
              setForm((current) => ({
                ...current,
                gender: event.target.value as ApiGender | "",
              }))
            }
            className="h-14 w-full rounded-default border border-border bg-white px-4 text-base text-foreground focus:border-primary focus:outline-none disabled:opacity-60"
          >
            <option value="">{tCommon("form.placeholders.select")}</option>
            {GENDERS.map((gender) => (
              <option key={gender} value={gender}>
                {t(`profile.gender_options.${gender}`)}
              </option>
            ))}
          </select>
        </div>
        <Input
          type="date"
          max={new Date().toISOString().slice(0, 10)}
          label={t("profile.labels.date_of_birth")}
          value={form.date_of_birth}
          disabled={!editing || pending}
          onChange={(event) =>
            setForm((current) => ({
              ...current,
              date_of_birth: event.target.value,
            }))
          }
        />
        <div className="sm:col-span-2">
          <label
            htmlFor="account-bio"
            className="mb-2 block text-sm text-secondary-text rtl:normal-case rtl:tracking-normal"
          >
            {t("profile.labels.bio")}
          </label>
          <textarea
            id="account-bio"
            rows={4}
            value={form.bio}
            placeholder={t("profile.placeholders.bio")}
            disabled={!editing || pending}
            onChange={(event) =>
              setForm((current) => ({ ...current, bio: event.target.value }))
            }
            className="w-full rounded-default border border-border bg-white px-4 py-3 text-base text-foreground outline-none transition-colors placeholder:text-secondary-text focus:border-primary disabled:opacity-60"
          />
        </div>
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
                resetForm();
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
