"use client";

import { useState, type ReactNode } from "react";
import { useLocale, useTranslations } from "next-intl";
import { ChevronDownIcon } from "@/components/ui/icons";
import { Switch } from "@/components/ui/switch";
import { usePathname, useRouter } from "@/i18n/navigation";

// Mock preferences — 2FA and the notification toggles await a backend
// endpoint, so they keep in-memory state only. Two rows are real: Language
// switches the active next-intl locale, and Appearance lists Light, the
// only theme the storefront ships.

interface SettingsSelectProps {
  value: string;
  onChange: (value: string) => void;
  label: string;
  options: { value: string; label: string }[];
}

function SettingsSelect({
  value,
  onChange,
  label,
  options,
}: SettingsSelectProps) {
  return (
    <div className="relative">
      <select
        value={value}
        onChange={(event) => onChange(event.target.value)}
        aria-label={label}
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
  description: string;
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

export function Settings() {
  const t = useTranslations("account.account");
  const locale = useLocale();
  const pathname = usePathname();
  const router = useRouter();

  const [appearance, setAppearance] = useState("light");
  const [twoFactor, setTwoFactor] = useState(true);
  const [pushNotifications, setPushNotifications] = useState(true);
  const [desktopNotifications, setDesktopNotifications] = useState(true);
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
          options={[{ value: "light", label: t("settings.appearance.theme_light") }]}
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

      <SettingsRow
        title={t("settings.push_notifications.title")}
        description={t("settings.push_notifications.description")}
      >
        <Switch
          checked={pushNotifications}
          onCheckedChange={setPushNotifications}
          label={t("settings.push_notifications.title")}
        />
      </SettingsRow>

      <SettingsRow
        title={t("settings.desktop_notifications.title")}
        description={t("settings.desktop_notifications.description")}
      >
        <Switch
          checked={desktopNotifications}
          onCheckedChange={setDesktopNotifications}
          label={t("settings.desktop_notifications.title")}
        />
      </SettingsRow>

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
