"use client";

import { useCallback, useEffect, useMemo, useState, type FormEvent } from "react";
import { useFormatter, useTranslations } from "next-intl";
import { useAuth } from "@/context/auth-context";
import { api, ApiError } from "@/lib/api/client";
import type {
  ApiAddress,
  ApiCartItem,
  ApiCountry,
  ApiProfile,
  ApiShippingMethod,
} from "@/lib/api/types";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Link, useRouter } from "@/i18n/navigation";

const EMPTY_ADDRESS_FORM = {
  full_name: "",
  phone: "",
  address_line: "",
  city: "",
  postal_code: "",
  country_code: "",
};

/**
 * Full checkout: pick or create a shipping address (shipping quotes are
 * country-driven), choose a method, then place the order (idempotent) and
 * start a manual payment — the only implemented backend driver.
 */
export default function CheckoutPage() {
  const t = useTranslations("checkout.checkout");
  const tCommon = useTranslations("shared.common");
  const tLayout = useTranslations("shared.layout");
  const format = useFormatter();
  const { status, token } = useAuth();
  const router = useRouter();

  const [cartItems, setCartItems] = useState<ApiCartItem[]>([]);
  const [addresses, setAddresses] = useState<ApiAddress[]>([]);
  const [countries, setCountries] = useState<ApiCountry[]>([]);
  const [addressId, setAddressId] = useState<number | null>(null);
  const [showAddressForm, setShowAddressForm] = useState(false);
  const [addressForm, setAddressForm] = useState({ ...EMPTY_ADDRESS_FORM });
  const [methods, setMethods] = useState<ApiShippingMethod[]>([]);
  const [methodId, setMethodId] = useState<number | null>(null);
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [placedOrder, setPlacedOrder] = useState<number | null>(null);

  useEffect(() => {
    if (status === "unauthenticated") {
      router.replace("/login?next=/checkout");
    }
  }, [status, router]);

  useEffect(() => {
    if (status !== "authenticated" || !token) return;
    void (async () => {
      try {
        const [items, savedAddresses, countryList, profile] = await Promise.all([
          api.get<ApiCartItem[]>("/cart/user/cart-items", { token }),
          api.get<ApiAddress[]>("/user/user/addresses", { token }),
          api.get<ApiCountry[]>("/core/public/countries"),
          // A missing profile row must not fail checkout; it only pre-fills
          // the address form's name.
          api.get<ApiProfile>("/user/user/profile", { token }).catch(() => null),
        ]);
        setCartItems(items);
        setAddresses(savedAddresses);
        setCountries(countryList);
        if (profile?.full_name) {
          const name = profile.full_name;
          setAddressForm((form) => ({ ...form, full_name: name }));
        }
        const preferred =
          savedAddresses.find((address) => address.is_default) ??
          savedAddresses[0];
        if (preferred) {
          setAddressId(preferred.id);
        } else {
          setShowAddressForm(true);
        }
      } catch (cause) {
        setError(
          cause instanceof ApiError
            ? cause.message
            : tCommon("messages.error.general"),
        );
      }
    })();
  }, [status, token, tCommon]);

  const loadMethods = useCallback(
    async (chosenAddressId: number) => {
      try {
        const quotes = await api.get<ApiShippingMethod[]>(
          `/shipping/user/methods?address_id=${chosenAddressId}`,
          { token },
        );
        setMethods(quotes);
        setMethodId(quotes.length > 0 ? quotes[0].id : null);
      } catch (cause) {
        setError(
          cause instanceof ApiError
            ? cause.message
            : tCommon("messages.error.general"),
        );
      }
    },
    [token, tCommon],
  );

  useEffect(() => {
    if (addressId !== null) {
      // See cart page: fetch-on-change effect, setState only in the async
      // continuation.
      // eslint-disable-next-line react-hooks/set-state-in-effect
      void loadMethods(addressId);
    }
  }, [addressId, loadMethods]);

  const onCreateAddress = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setPending(true);
    setError(null);
    try {
      const created = await api.post<ApiAddress>(
        "/user/user/addresses",
        { ...addressForm, type: "shipping", is_default: addresses.length === 0 },
        { token },
      );
      setAddresses((previous) => [...previous, created]);
      setAddressId(created.id);
      setShowAddressForm(false);
      setAddressForm({ ...EMPTY_ADDRESS_FORM });
    } catch (cause) {
      if (cause instanceof ApiError && cause.errors) {
        const first = Object.values(cause.errors).flat()[0];
        setError(first ?? cause.message);
      } else {
        setError(
          cause instanceof ApiError
            ? cause.message
            : tCommon("messages.error.general"),
        );
      }
    } finally {
      setPending(false);
    }
  };

  const selectedAddress = useMemo(
    () => addresses.find((address) => address.id === addressId) ?? null,
    [addresses, addressId],
  );
  const selectedMethod = useMemo(
    () => methods.find((method) => method.id === methodId) ?? null,
    [methods, methodId],
  );

  const subtotal = cartItems.reduce(
    (sum, item) => sum + Number(item.price_snapshot) * item.quantity,
    0,
  );
  const shippingCost = selectedMethod?.price ?? 0;
  const total = subtotal + shippingCost;

  const usd = { style: "currency", currency: "USD" } as const;

  const onPlaceOrder = async () => {
    if (addressId === null || methodId === null) return;
    setPending(true);
    setError(null);
    try {
      // Idempotency guards against double-submits creating two orders
      const idempotencyKey = crypto.randomUUID();
      const { order_id: orderId } = await api.post<{ order_id: number }>(
        "/cart/user/checkout",
        { address_id: addressId, shipping_method_id: methodId },
        { token, headers: { "X-Idempotency-Key": idempotencyKey } },
      );
      await api.post(
        "/payment/user/process",
        { order_id: String(orderId), method: "manual" },
        { token },
      );
      setPlacedOrder(orderId);
    } catch (cause) {
      setError(
        cause instanceof ApiError
          ? cause.message
          : tCommon("messages.error.general"),
      );
    } finally {
      setPending(false);
    }
  };

  if (status !== "authenticated" || !token) {
    return (
      <section className="mx-auto max-w-7xl px-6 py-24 text-center">
        <p className="text-base text-secondary-text rtl:normal-case rtl:tracking-normal">
          {tCommon("messages.info.loading")}
        </p>
      </section>
    );
  }

  if (placedOrder !== null) {
    return (
      <section className="mx-auto max-w-2xl px-6 py-24 text-center">
        <h1 className="text-3xl font-bold text-foreground rtl:normal-case rtl:tracking-normal">
          {t("success.texts.title")}
        </h1>
        <p className="mt-4 text-sm text-secondary-text rtl:normal-case rtl:tracking-normal">
          {t("success.texts.order_number", {
            id: placedOrder.toLocaleString(),
          })}
        </p>
        <p className="mt-2 text-sm text-secondary-text rtl:normal-case rtl:tracking-normal">
          {t("success.texts.description")}
        </p>
        <Link
          href="/products"
          className="mt-10 inline-flex h-12 items-center justify-center rounded-default bg-primary px-8 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90 rtl:normal-case rtl:tracking-normal"
        >
          {t("success.actions.continue_shopping")}
        </Link>
      </section>
    );
  }

  return (
    <>
      <nav
        aria-label={tLayout("breadcrumb.labels.navigation")}
        className="border-b border-border-light bg-muted"
      >
        <ol className="mx-auto flex max-w-7xl items-center gap-2 px-6 py-4 text-sm text-secondary-text">
          <li>
            <Link
              href="/"
              className="transition-colors hover:text-accent rtl:normal-case rtl:tracking-normal"
            >
              {tLayout("breadcrumb.links.home")}
            </Link>
          </li>
          <li aria-hidden="true">/</li>
          <li className="font-medium text-foreground rtl:normal-case rtl:tracking-normal">
            {t("texts.title")}
          </li>
        </ol>
      </nav>

      <section className="mx-auto max-w-7xl px-6 py-12">
        <h1 className="text-3xl font-bold text-foreground rtl:normal-case rtl:tracking-normal">
          {t("texts.title")}
        </h1>

        {cartItems.length === 0 ? (
          <div className="mt-8 text-center">
            <p className="text-base text-foreground rtl:normal-case rtl:tracking-normal">
              {t("texts.empty_cart")}
            </p>
            <Link
              href="/products"
              className="mt-6 inline-flex h-12 items-center justify-center rounded-default bg-primary px-8 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90 rtl:normal-case rtl:tracking-normal"
            >
              {t("success.actions.continue_shopping")}
            </Link>
          </div>
        ) : (
          <div className="mt-8 grid grid-cols-1 gap-12 lg:grid-cols-[1fr_420px]">
            <div className="space-y-10">
              {/* Address */}
              <div>
                <h2 className="text-lg font-semibold text-foreground rtl:normal-case rtl:tracking-normal">
                  {t("address.texts.title")}
                </h2>

                {addresses.length > 0 ? (
                  <div className="mt-4 space-y-3">
                    {addresses.map((address) => (
                      <label
                        key={address.id}
                        className="flex cursor-pointer items-start gap-3 rounded-default border border-border px-4 py-4 transition-colors hover:bg-muted"
                      >
                        <input
                          type="radio"
                          name="address"
                          checked={addressId === address.id}
                          onChange={() => setAddressId(address.id)}
                          className="mt-1 size-4 accent-accent"
                        />
                        <span className="text-sm text-foreground rtl:normal-case rtl:tracking-normal">
                          <span className="block font-medium">
                            {address.full_name}
                          </span>
                          <span className="mt-1 block text-secondary-text">
                            {address.address_line}، {address.city}،{" "}
                            {address.country_code}
                            {address.postal_code
                              ? `، ${address.postal_code}`
                              : ""}
                          </span>
                          <span className="mt-1 block text-secondary-text">
                            {address.phone}
                          </span>
                        </span>
                      </label>
                    ))}
                  </div>
                ) : null}

                {addresses.length > 0 ? (
                  <button
                    type="button"
                    onClick={() => setShowAddressForm((value) => !value)}
                    className="mt-3 text-sm font-medium text-accent hover:underline rtl:normal-case rtl:tracking-normal"
                  >
                    {t("address.actions.new")}
                  </button>
                ) : null}

                {showAddressForm ? (
                  <form
                    className="mt-4 grid grid-cols-1 gap-4 rounded-default border border-border-light p-4 sm:grid-cols-2"
                    onSubmit={onCreateAddress}
                  >
                    <Input
                      required
                      label={t("address.labels.full_name")}
                      placeholder={t("address.placeholders.full_name")}
                      value={addressForm.full_name}
                      onChange={(event) =>
                        setAddressForm((form) => ({
                          ...form,
                          full_name: event.target.value,
                        }))
                      }
                    />
                    <Input
                      required
                      type="tel"
                      label={t("address.labels.phone")}
                      placeholder={t("address.placeholders.phone")}
                      value={addressForm.phone}
                      onChange={(event) =>
                        setAddressForm((form) => ({
                          ...form,
                          phone: event.target.value,
                        }))
                      }
                    />
                    <div className="sm:col-span-2">
                      <Input
                        required
                        label={t("address.labels.address_line")}
                        placeholder={t("address.placeholders.address_line")}
                        value={addressForm.address_line}
                        onChange={(event) =>
                          setAddressForm((form) => ({
                            ...form,
                            address_line: event.target.value,
                          }))
                        }
                      />
                    </div>
                    <Input
                      required
                      label={t("address.labels.city")}
                      placeholder={t("address.placeholders.city")}
                      value={addressForm.city}
                      onChange={(event) =>
                        setAddressForm((form) => ({
                          ...form,
                          city: event.target.value,
                        }))
                      }
                    />
                    <Input
                      label={t("address.labels.postal_code")}
                      placeholder={t("address.placeholders.postal_code")}
                      value={addressForm.postal_code}
                      onChange={(event) =>
                        setAddressForm((form) => ({
                          ...form,
                          postal_code: event.target.value,
                        }))
                      }
                    />
                    <div>
                      <label
                        htmlFor="country"
                        className="mb-2 block text-sm text-secondary-text rtl:normal-case rtl:tracking-normal"
                      >
                        {t("address.labels.country")}
                      </label>
                      <select
                        id="country"
                        required
                        value={addressForm.country_code}
                        onChange={(event) =>
                          setAddressForm((form) => ({
                            ...form,
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
                    <div className="flex items-end">
                      <Button type="submit" variant="outline" fullWidth={false}>
                        {t("address.actions.save")}
                      </Button>
                    </div>
                  </form>
                ) : null}
              </div>

              {/* Shipping method */}
              <div>
                <h2 className="text-lg font-semibold text-foreground rtl:normal-case rtl:tracking-normal">
                  {t("shipping_method.texts.title")}
                </h2>
                {selectedAddress === null ? (
                  <p className="mt-4 text-sm text-secondary-text rtl:normal-case rtl:tracking-normal">
                    {t("shipping_method.texts.select_address_first")}
                  </p>
                ) : methods.length === 0 ? (
                  <p className="mt-4 text-sm text-secondary-text rtl:normal-case rtl:tracking-normal">
                    {t("shipping_method.texts.none_available")}
                  </p>
                ) : (
                  <div className="mt-4 space-y-3">
                    {methods.map((method) => (
                      <label
                        key={method.id}
                        className="flex cursor-pointer items-center justify-between gap-4 rounded-default border border-border px-4 py-4 transition-colors hover:bg-muted"
                      >
                        <span className="flex items-center gap-3">
                          <input
                            type="radio"
                            name="shipping"
                            checked={methodId === method.id}
                            onChange={() => setMethodId(method.id)}
                            className="size-4 accent-accent"
                          />
                          <span className="text-sm font-medium text-foreground rtl:normal-case rtl:tracking-normal">
                            {method.name}
                          </span>
                        </span>
                        <span className="text-sm font-medium text-foreground">
                          {format.number(method.price, usd)}
                        </span>
                      </label>
                    ))}
                  </div>
                )}
              </div>

              {/* Payment */}
              <div>
                <h2 className="text-lg font-semibold text-foreground rtl:normal-case rtl:tracking-normal">
                  {t("payment.texts.title")}
                </h2>
                <p className="mt-4 rounded-default border border-border bg-muted px-4 py-4 text-sm text-secondary-text rtl:normal-case rtl:tracking-normal">
                  {t("payment.texts.method_manual")}
                </p>
              </div>
            </div>

            {/* Summary */}
            <aside
              className="h-fit rounded-default border border-border-light p-6 lg:sticky lg:top-28"
              aria-label={t("summary.labels.aside")}
            >
              <h2 className="text-lg font-semibold text-foreground rtl:normal-case rtl:tracking-normal">
                {t("summary.labels.title")}
              </h2>
              <ul className="mt-6 space-y-4">
                {cartItems.map((item) => (
                  <li key={item.id} className="flex items-center gap-4">
                    <span
                      className="size-16 shrink-0 rounded-default border border-border bg-muted"
                      aria-hidden="true"
                    />
                    <span className="min-w-0 flex-1">
                      <span className="block truncate text-sm font-medium text-foreground rtl:normal-case rtl:tracking-normal">
                        {item.product_name_snapshot}
                      </span>
                      {item.variant_label ? (
                        <span className="block text-sm text-secondary-text rtl:normal-case rtl:tracking-normal">
                          {item.variant_label}
                        </span>
                      ) : null}
                      <span className="block text-sm text-secondary-text rtl:normal-case rtl:tracking-normal">
                        {t("summary.texts.qty", { count: item.quantity })}
                      </span>
                    </span>
                    <span className="text-sm font-medium text-foreground">
                      {format.number(
                        Number(item.price_snapshot) * item.quantity,
                        usd,
                      )}
                    </span>
                  </li>
                ))}
              </ul>

              <div className="mt-6 space-y-3 border-t border-border-light pt-6 text-sm">
                <p className="flex items-center justify-between text-secondary-text rtl:normal-case rtl:tracking-normal">
                  <span>{t("summary.labels.subtotal")}</span>
                  <span className="font-medium text-foreground">
                    {format.number(subtotal, usd)}
                  </span>
                </p>
                <p className="flex items-center justify-between text-secondary-text rtl:normal-case rtl:tracking-normal">
                  <span>{t("summary.labels.shipping")}</span>
                  <span className="font-medium text-foreground">
                    {selectedMethod
                      ? format.number(shippingCost, usd)
                      : "—"}
                  </span>
                </p>
                <p className="flex items-center justify-between border-t border-border-light pt-3 text-base font-semibold text-foreground rtl:normal-case rtl:tracking-normal">
                  <span>{t("summary.labels.total")}</span>
                  <span>{selectedMethod ? format.number(total, usd) : "—"}</span>
                </p>
              </div>

              {error ? (
                <p
                  role="alert"
                  className="mt-4 text-sm text-red-600 rtl:normal-case rtl:tracking-normal"
                >
                  {error}
                </p>
              ) : null}

              <Button
                variant="accent"
                fullWidth
                className="mt-6"
                disabled={pending || addressId === null || methodId === null}
                onClick={onPlaceOrder}
              >
                {pending
                  ? tCommon("messages.info.loading")
                  : t("actions.place_order")}
              </Button>
            </aside>
          </div>
        )}
      </section>
    </>
  );
}
