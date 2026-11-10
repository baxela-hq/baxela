import { z } from 'zod'
import type { Language } from '@/shared/types/locale.types'

export const settingTranslationSchema = z.object({
  language: z.string(),
  value: z.string(),
})
export type SettingTranslation = z.infer<typeof settingTranslationSchema>

export const settingSchema = z.object({
  group: z.string().nullable(),
  type: z.string().nullable(),
  name: z.string(),
  value: z.string().nullable(),
  is_translatable: z.boolean(),
  comment: z.string().nullable(),
  translations: z.array(
    z.object({
      id: z.number(),
      language_id: z.number(),
      value: z.string(),
    })
  ),
})
export type Setting = z.infer<typeof settingSchema>

export const translatableSettingSchema = z.object({
  value: z.string(),
  translations: z.array(settingTranslationSchema),
})
export type TranslatableSettingForm = z.infer<typeof translatableSettingSchema>

// Storefront theme registry — must stay in sync with the storefront's
// SITE_THEMES (apps/storefront/lib/theme.ts); the storefront falls back to
// its default theme for unknown values.
export const STOREFRONT_THEMES = ['default'] as const
export const DEFAULT_STOREFRONT_THEME: (typeof STOREFRONT_THEMES)[number] =
  'default'

export const formSchema = z.object({
  website_title: translatableSettingSchema,
  website_description: translatableSettingSchema,
  language_id: z.string(),
  currency_id: z.string(),
  timezone: z.string(),
  prices_include_tax: z.boolean(),
  announcement_bar_enabled: z.boolean(),
  announcement_text: translatableSettingSchema,
  storefront_theme: z.enum(STOREFRONT_THEMES),
  store_legal_name: z.string(),
  store_legal_address: z.string(),
  store_tax_id: z.string(),
  store_contact: z.string(),
})
export type SettingsForm = z.infer<typeof formSchema>

export const SettingRequestSchema = z.object({
  name: z.string(),
  value: z.string(),
  translations: z.array(settingTranslationSchema).optional(),
})
export type SettingRequest = z.infer<typeof SettingRequestSchema>

export const defaultValues: SettingsForm = {
  website_title: { value: '', translations: [] },
  website_description: { value: '', translations: [] },
  language_id: '',
  currency_id: '',
  timezone: 'UTC',
  prices_include_tax: false,
  announcement_bar_enabled: false,
  announcement_text: { value: '', translations: [] },
  storefront_theme: DEFAULT_STOREFRONT_THEME,
  store_legal_name: '',
  store_legal_address: '',
  store_tax_id: '',
  store_contact: '',
}

function buildTranslatableValues(
  languages: Language[],
  setting?: Setting
): TranslatableSettingForm {
  const existing = setting?.translations ?? []

  return {
    value: existing[0]?.value ?? '',
    translations: languages.map((language) => {
      const match = existing.find((t) => t.language_id === language.id)
      return { language: language.code, value: match?.value ?? '' }
    }),
  }
}

export function buildSettingsValues(
  languages: Language[],
  settings: Setting[]
): SettingsForm {
  const byName = new Map(settings.map((setting) => [setting.name, setting]))

  return {
    website_title: buildTranslatableValues(
      languages,
      byName.get('website_title')
    ),
    website_description: buildTranslatableValues(
      languages,
      byName.get('website_description')
    ),
    language_id: byName.get('language_id')?.value ?? '',
    currency_id: byName.get('currency_id')?.value ?? '',
    timezone: byName.get('timezone')?.value ?? 'UTC',
    prices_include_tax: byName.get('prices_include_tax')?.value === '1',
    announcement_bar_enabled:
      byName.get('announcement_bar_enabled')?.value === '1' ||
      byName.get('announcement_bar_enabled')?.value === 'true',
    announcement_text: buildTranslatableValues(
      languages,
      byName.get('announcement_text')
    ),
    storefront_theme: (STOREFRONT_THEMES as readonly string[]).includes(
      byName.get('storefront_theme')?.value ?? ''
    )
      ? (byName.get('storefront_theme')
          ?.value as SettingsForm['storefront_theme'])
      : DEFAULT_STOREFRONT_THEME,
    store_legal_name: byName.get('store_legal_name')?.value ?? '',
    store_legal_address: byName.get('store_legal_address')?.value ?? '',
    store_tax_id: byName.get('store_tax_id')?.value ?? '',
    store_contact: byName.get('store_contact')?.value ?? '',
  }
}

export function buildSettingsRequest(
  values: SettingsForm,
  languages: Language[]
): SettingRequest[] {
  const defaultCode =
    (languages.find((language) => language.is_default) ?? languages[0])?.code ??
    ''

  const resolve = (field: TranslatableSettingForm) => ({
    value:
      field.translations.find((t) => t.language === defaultCode)?.value ||
      field.value ||
      '',
    translations: field.translations.filter((t) => t.value !== ''),
  })

  return [
    { name: 'website_title', ...resolve(values.website_title) },
    { name: 'website_description', ...resolve(values.website_description) },
    { name: 'language_id', value: values.language_id },
    { name: 'currency_id', value: values.currency_id },
    { name: 'timezone', value: values.timezone || 'UTC' },
    {
      name: 'prices_include_tax',
      value: values.prices_include_tax ? '1' : '0',
    },
    {
      name: 'announcement_bar_enabled',
      value: values.announcement_bar_enabled ? '1' : '0',
    },
    { name: 'announcement_text', ...resolve(values.announcement_text) },
    { name: 'storefront_theme', value: values.storefront_theme },
    { name: 'store_legal_name', value: values.store_legal_name },
    { name: 'store_legal_address', value: values.store_legal_address },
    { name: 'store_tax_id', value: values.store_tax_id },
    { name: 'store_contact', value: values.store_contact },
  ]
}
