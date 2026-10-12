"use client";

import { useEffect, useState, type ReactNode } from "react";
import { useLocale, useTranslations } from "next-intl";
import { toast } from "sonner";
import { ChevronDownIcon, TelegramIcon } from "@/components/ui/icons";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Switch } from "@/components/ui/switch";
import { useAuth } from "@/context/auth-context";
import { useThemeMode } from "@/context/theme-mode-context";
import { ApiError } from "@/lib/api/client";
import {
  fetchTelegramBotInfo,
  telegramApi,
} from "@/lib/api/telegram";
import {
  fetchVapidPublicKey,
  notificationsApi,
} from "@/lib/api/notifications";
import { usePathname, useRouter } from "@/i18n/navigation";

// Mock preferences — 2FA and the email toggle await a backend endpoint,
// so they keep in-memory state only. Three rows are real: Language
// switches the active next-intl locale, Appearance switches the display
// mode (theme-mode cookie via the theme context), and Browser
// notifications registers this browser for OS-level web push.

interface SettingsSelectProps<T extends string> {
  value: T;
  onChange: (value: T) => void;
  label: string;
  options: { value: T; label: string }[];
}

function SettingsSelect<T extends string>({
  value,
  onChange,
  label,
  options,
}: SettingsSelectProps<T>) {
  return (
    <div className="relative">
      <select
        value={value}
        // Option values are the only strings the select can emit.
        onChange={(event) => onChange(event.target.value as T)}
        aria-label={label}
        // The appearance select starts from the pre-paint cookie value, which
        // the server-rendered markup cannot know — let the client value win.
        suppressHydrationWarning
        className="h-10 w-28 appearance-none rounded-default bg-muted ps-4 pe-9 text-sm font-medium text-foreground outline-none"
      >
        {options.map((option) => (
          <option key={option.value} value={option.value}>
            {option.label}
          </option>
        ))}
      </select>
      <span
        aria-hidden="true"
        className="pointer-events-none absolute end-3 top-1/2 -translate-y-1/2 text-secondary-text"
      >
        <ChevronDownIcon className="size-4" />
      </span>
    </div>
  );
}

function SettingsRow({
  title,
  description,
  children,
}: {
  title: string;
  description: ReactNode;
  children: ReactNode;
}) {
  return (
    <li className="flex flex-wrap items-center justify-between gap-4 py-6 first:pt-0">
      <div className="min-w-0">
        <p className="text-base font-bold text-foreground rtl:normal-case rtl:tracking-normal">
          {title}
        </p>
        <p className="mt-1 text-sm text-secondary-text rtl:normal-case rtl:tracking-normal">
          {description}
        </p>
      </div>
      {children}
    </li>
  );
}

type PushState = "checking" | "unsupported" | "unavailable" | "blocked" | "off" | "on" | "busy";

/** VAPID keys are base64url; the subscribe() API wants raw bytes. */
function urlBase64ToUint8Array(base64Url: string): Uint8Array<ArrayBuffer> {
  const padding = "=".repeat((4 - (base64Url.length % 4)) % 4);
  const base64 = (base64Url + padding).replace(/-/g, "+").replace(/_/g, "/");
  const raw = atob(base64);
  const buffer = new ArrayBuffer(raw.length);
  const output = new Uint8Array(buffer);
  for (let index = 0; index < raw.length; index += 1) {
    output[index] = raw.charCodeAt(index);
  }
  return output;
}

/**
 * OS-level web push for this browser: enabled means the browser holds a
 * push subscription the backend knows about, so deliveries keep coming
 * with the site closed. The service worker (public/sw.js) shows the
 * notifications and deep-links order rows.
 */
function BrowserNotificationsRow() {
  const t = useTranslations("account.account");
  const locale = useLocale();
  const { token } = useAuth();

  const [state, setState] = useState<PushState>("checking");

  useEffect(() => {
    if (!token) return;
    let active = true;
    void (async () => {
      const supported =
        typeof window !== "undefined" &&
        "serviceWorker" in navigator &&
        "PushManager" in window &&
        typeof Notification !== "undefined";
      if (!supported) {
        if (active) setState("unsupported");
        return;
      }
      try {
        const { public_key: publicKey } = await fetchVapidPublicKey();
        if (!publicKey) {
          if (active) setState("unavailable");
          return;
        }
        if (Notification.permission === "denied") {
          if (active) setState("blocked");
          return;
        }
        const registration = await navigator.serviceWorker.ready;
        const subscription = await registration.pushManager.getSubscription();
        if (!subscription) {
          if (active) setState("off");
          return;
        }
        const subscriptions = await notificationsApi(token).pushSubscriptions();
        if (!active) return;
        const endpoints = new Set(subscriptions.map((row) => row.endpoint));
        setState(endpoints.has(subscription.endpoint) ? "on" : "off");
      } catch {
        if (active) setState("off");
      }
    })();
    return () => {
      active = false;
    };
  }, [token]);

  const toggle = async (checked: boolean) => {
    if (!token) return;
    setState("busy");
    try {
      if (checked) {
        const { public_key: publicKey } = await fetchVapidPublicKey();
        if (!publicKey) {
          setState("unavailable");
          return;
        }
        const permission = await Notification.requestPermission();
        if (permission !== "granted") {
          setState("blocked");
          return;
        }
        const registration = await navigator.serviceWorker.register("/sw.js");
        await navigator.serviceWorker.ready;
        const subscription =
          (await registration.pushManager.getSubscription()) ??
          (await registration.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: urlBase64ToUint8Array(publicKey),
          }));
        const json = subscription.toJSON();
        await notificationsApi(token).savePushSubscription({
          endpoint: subscription.endpoint,
          keys: {
            p256dh: json.keys?.p256dh ?? null,
            auth: json.keys?.auth ?? null,
          },
          user_agent: navigator.userAgent,
          locale,
        });
        setState("on");
      } else {
        const registration = await navigator.serviceWorker.ready;
        const subscription = await registration.pushManager.getSubscription();
        if (subscription) {
          await notificationsApi(token).deletePushSubscription(
            subscription.endpoint,
          );
          await subscription.unsubscribe();
        }
        setState("off");
      }
    } catch {
      setState("off");
    }
  };

  const hint =
    state === "blocked"
      ? t("settings.browser_notifications.hints.blocked")
      : state === "unsupported"
        ? t("settings.browser_notifications.hints.unsupported")
        : state === "unavailable"
          ? t("settings.browser_notifications.hints.unavailable")
          : null;

  return (
    <SettingsRow
      title={t("settings.browser_notifications.title")}
      description={
        hint ?? t("settings.browser_notifications.description")
      }
    >
      <Switch
        checked={state === "on"}
        disabled={state === "checking" || state === "busy" || state === "unsupported" || state === "unavailable" || state === "blocked"}
        onCheckedChange={(checked) => void toggle(checked)}
        label={t("settings.browser_notifications.title")}
      />
    </SettingsRow>
  );
}

/**
 * Telegram delivery for this account: enabled means a chat is linked
 * server-side. Turning it on needs the linking code the bot replies
 * with on /start, so the switch expands an inline code form instead of
 * toggling directly; turning it off simply unlinks.
 */
function TelegramNotificationsRow() {
  const t = useTranslations("account.account");
  const { token } = useAuth();

  type TelegramState =
    | "checking"
    | "unavailable"
    | "off"
    | "linking"
    | "busy"
    | "on";

  const [state, setState] = useState<TelegramState>("checking");
  const [botUrl, setBotUrl] = useState<string | null>(null);
  const [code, setCode] = useState("");
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (!token) return;
    let active = true;
    void (async () => {
      try {
        const bot = await fetchTelegramBotInfo();
        if (!active) return;
        setBotUrl(bot.bot_url);
        if (!bot.bot_url) {
          setState("unavailable");
          return;
        }
        const subscription = await telegramApi(token).subscription();
        if (active) setState(subscription ? "on" : "off");
      } catch {
        if (active) setState("off");
      }
    })();
    return () => {
      active = false;
    };
  }, [token]);

  const submit = async (event: React.FormEvent) => {
    event.preventDefault();
    if (!token) return;

    const trimmed = code.trim();
    if (!/^\d{6}$/.test(trimmed)) {
      setError(t("settings.telegram_notifications.link.invalid"));
      return;
    }

    setError(null);
    setState("busy");
    try {
      await telegramApi(token).link({ code: trimmed, chat_id: null });
      setState("on");
      setCode("");
      toast.success(t("settings.telegram_notifications.link.success"));
    } catch (cause) {
      setState("linking");
      toast.error(
        cause instanceof ApiError
          ? cause.message
          : t("settings.telegram_notifications.link.failed"),
      );
    }
  };

  const unlink = async () => {
    if (!token) return;
    setState("busy");
    try {
      await telegramApi(token).unlink();
      setState("off");
    } catch {
      setState("on");
    }
  };

  return (
    <li className="py-6 first:pt-0">
      <div className="flex flex-wrap items-center justify-between gap-4">
        <div className="min-w-0">
          <p className="flex items-center gap-2 text-base font-bold text-foreground rtl:normal-case rtl:tracking-normal">
            <TelegramIcon className="size-4" />
            {t("settings.telegram_notifications.title")}
          </p>
          <p className="mt-1 text-sm text-secondary-text rtl:normal-case rtl:tracking-normal">
            {state === "unavailable"
              ? t("settings.telegram_notifications.hints.unavailable")
              : t("settings.telegram_notifications.description")}
          </p>
        </div>
        <Switch
          checked={state === "on"}
          disabled={
            state === "checking" ||
            state === "busy" ||
            state === "unavailable"
          }
          onCheckedChange={(checked) => {
            if (checked) setState("linking");
            else void unlink();
          }}
          label={t("settings.telegram_notifications.title")}
        />
      </div>

      {state === "linking" && (
        <form className="mt-4 max-w-sm space-y-2" onSubmit={submit}>
          <p className="text-sm text-secondary-text">
            {t("settings.telegram_notifications.link.instructions")}{" "}
            {botUrl && (
              <a
                href={botUrl}
                target="_blank"
                rel="noreferrer"
                className="font-medium text-primary underline underline-offset-4"
              >
                {t("settings.telegram_notifications.link.open_bot")}
              </a>
            )}
          </p>
          <Input
            value={code}
            onChange={(event) => setCode(event.target.value)}
            placeholder={t("settings.telegram_notifications.link.placeholder")}
            inputMode="numeric"
            aria-label={t("settings.telegram_notifications.link.placeholder")}
          />
          {error && <p className="text-sm text-red-500">{error}</p>}
          <div className="flex items-center gap-2">
            <Button type="submit" size="sm" className="w-auto px-4" disabled={code.trim() === ""}>
              {t("settings.telegram_notifications.link.submit")}
            </Button>
            <Button
              type="button"
              variant="outline"
              size="sm"
              className="w-auto px-4"
              onClick={() => {
                setState("off");
                setError(null);
              }}
            >
              {t("settings.telegram_notifications.link.cancel")}
            </Button>
          </div>
        </form>
      )}
    </li>
  );
}

export function Settings() {
  const t = useTranslations("account.account");
  const locale = useLocale();
  const pathname = usePathname();
  const router = useRouter();
  const { preference: appearance, setPreference: setAppearance } =
    useThemeMode();

  const [twoFactor, setTwoFactor] = useState(true);
  const [emailNotifications, setEmailNotifications] = useState(true);

  return (
    <ul className="divide-y divide-border-light">
      <SettingsRow
        title={t("settings.appearance.title")}
        description={t("settings.appearance.description")}
      >
        <SettingsSelect
          value={appearance}
          onChange={setAppearance}
          label={t("settings.appearance.title")}
          options={[
            { value: "light", label: t("settings.appearance.theme_light") },
            { value: "dark", label: t("settings.appearance.theme_dark") },
            { value: "system", label: t("settings.appearance.theme_system") },
          ]}
        />
      </SettingsRow>

      <SettingsRow
        title={t("settings.language.title")}
        description={t("settings.language.description")}
      >
        <SettingsSelect
          value={locale}
          onChange={(next) => router.replace(pathname, { locale: next })}
          label={t("settings.language.title")}
          options={[
            { value: "en", label: "English" },
            { value: "fa", label: "فارسی" },
          ]}
        />
      </SettingsRow>

      <SettingsRow
        title={t("settings.two_factor.title")}
        description={t("settings.two_factor.description")}
      >
        <Switch
          checked={twoFactor}
          onCheckedChange={setTwoFactor}
          label={t("settings.two_factor.title")}
        />
      </SettingsRow>

      <BrowserNotificationsRow />

      <TelegramNotificationsRow />

      <SettingsRow
        title={t("settings.email_notifications.title")}
        description={t("settings.email_notifications.description")}
      >
        <Switch
          checked={emailNotifications}
          onCheckedChange={setEmailNotifications}
          label={t("settings.email_notifications.title")}
        />
      </SettingsRow>
    </ul>
  );
}
