"use client";

import {
  useCallback,
  useEffect,
  useState,
  type FormEvent,
} from "react";
import { useTranslations } from "next-intl";
import { toast } from "sonner";
import { useAuth } from "@/context/auth-context";
import { api, ApiError } from "@/lib/api/client";
import type { ApiAddress, ApiCountry } from "@/lib/api/types";
import { Checkbox } from "@/components/ui/checkbox";
import {
  PencilIcon,
  PhoneIcon,
  PlusIcon,
  TrashIcon,
} from "@/components/ui/icons";
import { Input } from "@/components/ui/input";

const EMPTY_FORM = {
  full_name: "",
  phone: "",
  address_line: "",
  address_line_2: "",
  city: "",
  postal_code: "",
  country_code: "",
  is_default: false,
};

/**
 * The "Manage Addresses" tab: full CRUD over the address book that checkout
 * only offers inline. The list follows the profile screen — dark add button,
 * flat rows with edit/delete chips on the side — and add/edit opens the
 * modal from the design. The design's two address lines are combined into
 * the single address_line column, and its State select is the backend's
 * required country select.
 */
export function AddressManager() {
  const t = useTranslations("account.account");
  const tCommon = useTranslations("shared.common");
  const { token } = useAuth();

  const [addresses, setAddresses] = useState<ApiAddress[] | null>(null);
  const [countries, setCountries] = useState<ApiCountry[]>([]);
  const [form, setForm] = useState({ ...EMPTY_FORM });
  const [editingId, setEditingId] = useState<number | null>(null);
  const [showForm, setShowForm] = useState(false);
  const [confirmingDeleteId, setConfirmingDeleteId] = useState<number | null>(
    null,
  );
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    if (!token) return;
    try {
      const [savedAddresses, countryList] = await Promise.all([
        api.get<ApiAddress[]>("/user/user/addresses", { token }),
        api.get<ApiCountry[]>("/core/public/countries"),
      ]);
      setAddresses(savedAddresses);
      setCountries(countryList);
      setError(null);
    } catch (cause) {
      setError(
        cause instanceof ApiError
          ? cause.message
          : tCommon("messages.error.general"),
      );
    }
  }, [token, tCommon]);

  useEffect(() => {
    if (token) {
      // See cart page: fetch-on-auth is a legitimate external-system sync;
      // the rule flags setState statically even though it only runs in the
      // async continuation after the fetch resolves.
      // eslint-disable-next-line react-hooks/set-state-in-effect
      void load();
    }
  }, [token, load]);

  const openCreateForm = () => {
    setEditingId(null);
    setForm({ ...EMPTY_FORM });
    setShowForm(true);
  };

  const openEditForm = (address: ApiAddress) => {
    setEditingId(address.id);
    setForm({
      full_name: address.full_name,
      phone: address.phone,
      address_line: address.address_line,
      address_line_2: "",
      city: address.city,
      postal_code: address.postal_code ?? "",
      country_code: address.country_code,
      is_default: address.is_default,
    });
    setShowForm(true);
  };

  const closeForm = useCallback(() => {
    setShowForm(false);
    setEditingId(null);
    setForm({ ...EMPTY_FORM });
  }, []);

  // Same overlay behaviour as the mobile menu drawer: lock the page behind
  // the dialog and let Escape dismiss it.
  useEffect(() => {
    if (!showForm) return;

    const onKeyDown = (event: KeyboardEvent) => {
      if (event.key === "Escape") closeForm();
    };
    document.addEventListener("keydown", onKeyDown);
    document.body.style.overflow = "hidden";

    return () => {
      document.removeEventListener("keydown", onKeyDown);
      document.body.style.overflow = "";
    };
  }, [showForm, closeForm]);

  const onSave = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setPending(true);
    setError(null);
    try {
      // The design splits the street address over two inputs; the backend
      // stores one address_line column, so the lines are joined.
      const addressLine = [form.address_line, form.address_line_2]
        .map((line) => line.trim())
        .filter((line) => line !== "")
        .join(", ");
      // First address is forced default so checkout always has a preference.
      const isFirstAddress = editingId === null && addresses?.length === 0;
      const body = {
        full_name: form.full_name,
        phone: form.phone,
        address_line: addressLine,
        city: form.city,
        postal_code: form.postal_code,
        country_code: form.country_code,
        type: "shipping",
        is_default: isFirstAddress || form.is_default,
      };
      const saved =
        editingId === null
          ? await api.post<ApiAddress>("/user/user/addresses", body, { token })
          : await api.patch<ApiAddress>(
              `/user/user/addresses/${editingId}`,
              body,
              { token },
            );
      setAddresses((previous) =>
        editingId === null
          ? [...(previous ?? []), saved]
          : (previous ?? []).map((entry) =>
              entry.id === saved.id ? saved : entry,
            ),
      );
      toast.success(t("address.messages.success.saved"));
      closeForm();
    } catch (cause) {
      if (cause instanceof ApiError && cause.errors) {
        const first = Object.values(cause.errors).flat()[0];
        toast.error(first ?? cause.message);
      } else {
        toast.error(
          cause instanceof ApiError
            ? cause.message
            : t("address.messages.error.save_failed"),
        );
      }
    } finally {
      setPending(false);
    }
  };

  const onDelete = async (address: ApiAddress) => {
    setPending(true);
    try {
      await api.delete(`/user/user/addresses/${address.id}`, { token });
      setAddresses(
        (previous) =>
          previous?.filter((entry) => entry.id !== address.id) ?? previous,
      );
      toast.success(t("address.messages.success.deleted"));
    } catch (cause) {
      toast.error(
        cause instanceof ApiError
          ? cause.message
          : t("address.messages.error.delete_failed"),
      );
    } finally {
      setPending(false);
      setConfirmingDeleteId(null);
    }
  };

  if (addresses === null) {
    return (
      <p className="py-16 text-center text-base text-secondary-text rtl:normal-case rtl:tracking-normal">
        {error ?? tCommon("messages.info.loading")}
      </p>
    );
  }

  return (
    <div>
      {!showForm ? (
        <button
          type="button"
          onClick={openCreateForm}
          className="inline-flex h-12 items-center justify-center gap-2 rounded-default bg-primary px-6 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90 rtl:normal-case rtl:tracking-normal"
        >
          <PlusIcon className="size-4" />
          {t("address.actions.add")}
        </button>
      ) : null}

      {addresses.length === 0 && !showForm ? (
        <div className="py-16 text-center">
          <p className="text-base text-foreground rtl:normal-case rtl:tracking-normal">
            {t("texts.addresses_empty")}
          </p>
          <p className="mt-2 text-sm text-secondary-text rtl:normal-case rtl:tracking-normal">
            {t("texts.addresses_empty_hint")}
          </p>
        </div>
      ) : null}

      {addresses.length > 0 ? (
        <ul className="mt-2 divide-y divide-border-light">
          {addresses.map((address) => (
            <li key={address.id} className="flex flex-wrap gap-4 py-6 first:pt-4">
              <div className="min-w-0 flex-1">
                <p className="flex flex-wrap items-center gap-2">
                  <span className="text-lg font-bold text-foreground rtl:normal-case rtl:tracking-normal">
                    {address.full_name}
                  </span>
                  {address.is_default ? (
                    <span className="inline-flex items-center rounded-default bg-accent/10 px-2 py-0.5 text-xs font-medium text-accent rtl:normal-case rtl:tracking-normal">
                      {t("labels.default_badge")}
                    </span>
                  ) : null}
                </p>
                <p className="mt-2 text-sm text-secondary-text rtl:normal-case rtl:tracking-normal">
                  {address.address_line}، {address.city}،{" "}
                  {address.country_code}
                  {address.postal_code ? `، ${address.postal_code}` : ""}
                </p>
                <p className="mt-2 flex items-center gap-2 text-sm text-secondary-text rtl:normal-case rtl:tracking-normal">
                  <PhoneIcon className="size-4" />
                  {address.phone}
                </p>
              </div>

              <div className="flex flex-col items-stretch gap-2">
                {confirmingDeleteId === address.id ? (
                  <>
                    <button
                      type="button"
                      disabled={pending}
                      onClick={() => onDelete(address)}
                      className="inline-flex h-9 items-center justify-center gap-2 rounded-default bg-red-500 px-4 text-sm font-medium text-white transition-colors hover:bg-red-600 disabled:opacity-60 rtl:normal-case rtl:tracking-normal"
                    >
                      <TrashIcon className="size-4" />
                      {t("address.actions.confirm_delete")}
                    </button>
                    <button
                      type="button"
                      onClick={() => setConfirmingDeleteId(null)}
                      className="inline-flex h-9 items-center justify-center rounded-default border border-border bg-white px-4 text-sm font-medium text-foreground transition-colors hover:bg-muted rtl:normal-case rtl:tracking-normal"
                    >
                      {t("address.actions.keep")}
                    </button>
                  </>
                ) : (
                  <>
                    <button
                      type="button"
                      onClick={() => openEditForm(address)}
                      className="inline-flex h-9 items-center justify-center gap-2 rounded-default bg-muted px-4 text-sm font-medium text-foreground transition-colors hover:bg-border rtl:normal-case rtl:tracking-normal"
                    >
                      <PencilIcon className="size-4" />
                      {t("address.actions.edit")}
                    </button>
                    <button
                      type="button"
                      onClick={() => setConfirmingDeleteId(address.id)}
                      className="inline-flex h-9 items-center justify-center gap-2 rounded-default bg-red-50 px-4 text-sm font-medium text-red-600 transition-colors hover:bg-red-100 rtl:normal-case rtl:tracking-normal"
                    >
                      <TrashIcon className="size-4" />
                      {t("address.actions.delete")}
                    </button>
                  </>
                )}
              </div>
            </li>
          ))}
        </ul>
      ) : null}

      {showForm ? (
        <div
          role="dialog"
          aria-modal="true"
          aria-label={
            editingId === null
              ? t("address.texts.add_title")
              : t("address.texts.edit_title")
          }
          className="fixed inset-0 z-50 overflow-y-auto"
        >
          <div
            aria-hidden="true"
            onClick={closeForm}
            className="fixed inset-0 bg-foreground/40"
          />
          <div className="flex min-h-full items-center justify-center p-4">
            <div className="relative w-full max-w-md rounded-default bg-white p-6 sm:p-8">
              <h2 className="text-xl font-bold text-foreground rtl:normal-case rtl:tracking-normal">
                {editingId === null
                  ? t("address.texts.add_title")
                  : t("address.texts.edit_title")}
              </h2>

              <form
                className="mt-6 flex flex-col gap-5"
                onSubmit={onSave}
              >
                <Input
                  required
                  label={t("address.labels.full_name")}
                  placeholder={t("address.placeholders.full_name")}
                  value={form.full_name}
                  disabled={pending}
                  onChange={(event) =>
                    setForm((current) => ({
                      ...current,
                      full_name: event.target.value,
                    }))
                  }
                />
                <Input
                  required
                  type="tel"
                  label={t("address.labels.phone")}
                  placeholder={t("address.placeholders.phone")}
                  value={form.phone}
                  disabled={pending}
                  onChange={(event) =>
                    setForm((current) => ({
                      ...current,
                      phone: event.target.value,
                    }))
                  }
                />
                <Input
                  required
                  label={t("address.labels.address_line")}
                  value={form.address_line}
                  disabled={pending}
                  onChange={(event) =>
                    setForm((current) => ({
                      ...current,
                      address_line: event.target.value,
                    }))
                  }
                />
                <Input
                  label={t("address.labels.address_line_2")}
                  value={form.address_line_2}
                  disabled={pending}
                  onChange={(event) =>
                    setForm((current) => ({
                      ...current,
                      address_line_2: event.target.value,
                    }))
                  }
                />
                <Input
                  required
                  label={t("address.labels.city")}
                  placeholder={t("address.placeholders.city")}
                  value={form.city}
                  disabled={pending}
                  onChange={(event) =>
                    setForm((current) => ({
                      ...current,
                      city: event.target.value,
                    }))
                  }
                />
                <Input
                  label={t("address.labels.postal_code")}
                  placeholder={t("address.placeholders.postal_code")}
                  value={form.postal_code}
                  disabled={pending}
                  onChange={(event) =>
                    setForm((current) => ({
                      ...current,
                      postal_code: event.target.value,
                    }))
                  }
                />
                <div>
                  <label
                    htmlFor="account-address-country"
                    className="mb-2 block text-sm text-secondary-text rtl:normal-case rtl:tracking-normal"
                  >
                    {t("address.labels.country")}
                  </label>
                  <select
                    id="account-address-country"
                    required
                    value={form.country_code}
                    disabled={pending}
                    onChange={(event) =>
                      setForm((current) => ({
                        ...current,
                        country_code: event.target.value,
                      }))
                    }
                    className="h-14 w-full rounded-default border border-border bg-white px-4 text-base text-foreground focus:border-primary focus:outline-none"
                  >
                    <option value="">
                      {tCommon("form.placeholders.select")}
                    </option>
                    {countries.map((country) => (
                      <option key={country.id} value={country.code}>
                        {country.name}
                      </option>
                    ))}
                  </select>
                </div>
                <Checkbox
                  label={t("address.labels.is_default")}
                  checked={form.is_default}
                  onCheckedChange={(checked) =>
                    setForm((current) => ({
                      ...current,
                      is_default: checked,
                    }))
                  }
                />
                <div className="mt-2 flex items-center gap-3">
                  <button
                    type="button"
                    disabled={pending}
                    onClick={closeForm}
                    className="h-12 flex-1 rounded-default bg-muted text-sm font-medium text-foreground transition-colors hover:bg-border disabled:opacity-60 rtl:normal-case rtl:tracking-normal"
                  >
                    {t("address.actions.cancel_edit")}
                  </button>
                  <button
                    type="submit"
                    disabled={pending}
                    className="h-12 flex-1 rounded-default bg-primary text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90 disabled:opacity-60 rtl:normal-case rtl:tracking-normal"
                  >
                    {pending
                      ? tCommon("messages.info.loading")
                      : editingId === null
                        ? t("address.actions.add")
                        : t("address.actions.save")}
                  </button>
                </div>
              </form>
            </div>
          </div>
        </div>
      ) : null}
    </div>
  );
}
