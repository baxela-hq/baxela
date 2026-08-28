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

export const formSchema = z.object({
  website_title: translatableSettingSchema,
  website_description: translatableSettingSchema,
  language_id: z.string(),
  currency_id: z.string(),
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
    website_title: buildTranslatableValues(languages, byName.get('website_title')),
    website_description: buildTranslatableValues(
      languages,
      byName.get('website_description')
    ),
    language_id: byName.get('language_id')?.value ?? '',
    currency_id: byName.get('currency_id')?.value ?? '',
  }
}

export function buildSettingsRequest(
  values: SettingsForm,
  languages: Language[]
): SettingRequest[] {
  const defaultCode =
    (languages.find((language) => language.is_default) ?? languages[0])?.code ?? ''

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
  ]
}