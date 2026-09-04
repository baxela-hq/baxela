"use client";

import { useCallback, useEffect, useState, type FormEvent } from "react";
import { useTranslations } from "next-intl";
import { toast } from "sonner";
import { useAuth } from "@/context/auth-context";
import { api, ApiError } from "@/lib/api/client";
import type { ApiAddress, ApiCountry } from "@/lib/api/types";
import { Checkbox } from "@/components/ui/checkbox";
import { Input } from "@/components/ui/input";

const EMPTY_FORM = {
  full_name: "",
  phone: "",
  address_line: "",
  city: "",
  postal_code: "",
  country_code: "",
  is_default: false,
};

/**
 * The "Manage Addresses" tab: full CRUD over the address book that checkout
 * only offers inline. Same field set and form layout as checkout.
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
      city: address.city,
      postal_code: address.postal_code ?? "",
      country_code: address.country_code,
      is_default: address.is_default,
    });
    setShowForm(true);
  };

  const closeForm = () => {
    setShowForm(false);
    setEditingId(null);
    setForm({ ...EMPTY_FORM });
  };

  const onSave = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setPending(true);
    setError(null);
    try {
      // First address is forced default so checkout always has a preference.
      const isFirstAddress = editingId === null && addresses?.length === 0;
      const body = {
        ...form,
        type: "shipping",
        is_default: isFirstAddress || form.is_default,
      };
      const saved = editingId === null
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
      {addresses.length === 0 && !showForm ? (
        <div className="rounded-default border border-border-light bg-white py-16 text-center">
          <p className="text-base text-foreground rtl:normal-case rtl:tracking-normal">
            {t("texts.addresses_empty")}
          </p>
          <p className="mt-2 text-sm text-secondary-text rtl:normal-case rtl:tracking-normal">
            {t("texts.addresses_empty_hint")}
          </p>
          <button
            type="button"
            onClick={openCreateForm}
            className="mt-6 text-sm font-medium text-accent hover:underline rtl:normal-case rtl:tracking-normal"
          >
            {t("address.actions.add")}
          </button>
        </div>
      ) : null}

      {addresses.length > 0 ? (
        <ul className="space-y-3">
          {addresses.map((address) => (
            <li
              key={address.id}
              className="rounded-default border border-border-light p-4"
            >
              <div className="flex flex-wrap items-start justify-between gap-3">
                <p className="flex flex-wrap items-center gap-2">
                  <span className="text-sm font-medium text-foreground rtl:normal-case rtl:tracking-normal">
                    {address.full_name}
                  </span>
                  {address.is_default ? (
                    <span className="inline-flex items-center rounded-default bg-accent/10 px-2 py-0.5 text-xs font-medium text-accent rtl:normal-case rtl:tracking-normal">
                      {t("labels.default_badge")}
                    </span>
                  ) : null}
                </p>
                <p className="flex items-center gap-4">
                  <button
                    type="button"
                    onClick={() => openEditForm(address)}
                    className="text-sm font-medium text-accent hover:underline rtl:normal-case rtl:tracking-normal"
                  >
                    {t("address.actions.edit")}
                  </button>
                  {confirmingDeleteId === address.id ? (
                    <>
                      <button
                        type="button"
                        disabled={pending}
                        onClick={() => onDelete(address)}
                        className="text-sm font-medium text-red-600 hover:underline disabled:opacity-60 rtl:normal-case rtl:tracking-normal"
                      >
                        {t("address.actions.confirm_delete")}
                      </button>
                      <button
                        type="button"
                        onClick={() => setConfirmingDeleteId(null)}
                        className="text-sm text-secondary-text hover:underline rtl:normal-case rtl:tracking-normal"
                      >
                        {t("address.actions.keep")}
                      </button>
                    </>
                  ) : (
                    <button
                      type="button"
                      onClick={() => setConfirmingDeleteId(address.id)}
                      className="text-sm text-secondary-text underline underline-offset-2 transition-colors hover:text-red-600 rtl:normal-case rtl:tracking-normal"
                    >
                      {t("address.actions.delete")}
                    </button>
                  )}
                </p>
              </div>
              <p className="mt-2 text-sm text-secondary-text rtl:normal-case rtl:tracking-normal">
                <span className="block">
                  {address.address_line}، {address.city}،{" "}
                  {address.country_code}
                  {address.postal_code ? `، ${address.postal_code}` : ""}
                </span>
                <span className="mt-1 block">{address.phone}</span>
              </p>
            </li>
          ))}
        </ul>
      ) : null}

      {addresses.length > 0 && !showForm ? (
        <button
          type="button"
          onClick={openCreateForm}
          className="mt-4 text-sm font-medium text-accent hover:underline rtl:normal-case rtl:tracking-normal"
        >
          {t("address.actions.add")}
        </button>
      ) : null}

      {showForm ? (
        <form
          className="mt-4 grid grid-cols-1 gap-4 rounded-default border border-border-light p-4 sm:grid-cols-2"
          onSubmit={onSave}
        >
          <Input
            required
            label={t("address.labels.full_name")}
            placeholder={t("address.placeholders.full_name")}
            value={form.full_name}
            disabled={pending}
            onChange={(event) =>
              setForm((current) => ({ ...current, full_name: event.target.value }))
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
              setForm((current) => ({ ...current, phone: event.target.value }))
            }
          />
          <div className="sm:col-span-2">
            <Input
              required
              label={t("address.labels.address_line")}
              placeholder={t("address.placeholders.address_line")}
              value={form.address_line}
              disabled={pending}
              onChange={(event) =>
                setForm((current) => ({
                  ...current,
                  address_line: event.target.value,
                }))
              }
            />
          </div>
          <Input
            required
            label={t("address.labels.city")}
            placeholder={t("address.placeholders.city")}
            value={form.city}
            disabled={pending}
            onChange={(event) =>
              setForm((current) => ({ ...current, city: event.target.value }))
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
              <option value="">{tCommon("form.placeholders.select")}</option>
              {countries.map((country) => (
                <option key={country.id} value={country.code}>
                  {country.name}
                </option>
              ))}
            </select>
          </div>
          <div className="flex items-center sm:col-span-2">
            <Checkbox
              label={t("address.labels.is_default")}
              checked={form.is_default}
              onCheckedChange={(checked) =>
                setForm((current) => ({ ...current, is_default: checked }))
              }
            />
          </div>
          <div className="flex flex-wrap items-center gap-3 sm:col-span-2">
            <button
              type="submit"
              disabled={pending}
              className="inline-flex h-12 items-center justify-center rounded-default bg-primary px-6 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90 disabled:opacity-60 rtl:normal-case rtl:tracking-normal"
            >
              {pending
                ? tCommon("messages.info.loading")
                : t("address.actions.save")}
            </button>
            <button
              type="button"
              disabled={pending}
              onClick={closeForm}
              className="inline-flex h-12 items-center justify-center rounded-default border border-border bg-white px-6 text-sm font-medium text-foreground transition-colors hover:bg-muted rtl:normal-case rtl:tracking-normal"
            >
              {t("address.actions.cancel_edit")}
            </button>
          </div>
        </form>
      ) : null}
    </div>
  );
}
