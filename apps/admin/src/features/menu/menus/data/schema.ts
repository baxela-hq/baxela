import { z } from 'zod'
import type { Language } from '@/shared/types/locale.types'

export const MENU_LOCATIONS = ['header', 'footer'] as const
export type MenuLocation = (typeof MENU_LOCATIONS)[number]

export const translationSchema = z.object({
  language_id: z.number(),
  language: z.string(),
  title: z.string().min(1, 'required'),
  description: z.string(),
});
export type TranslationForm = z.infer<typeof translationSchema>

export const menuSchema = z.object({
  id: z.number(),
  location: z.string(),
  is_active: z.boolean(),
  translations: z.array(translationSchema),
  created_at: z.string(),
  updated_at: z.string(),
})
export type Menu = z.infer<typeof menuSchema>

export const formSchema = z.object({
  location: z.string().min(1, 'required'),
  is_active: z.boolean(),
  translations: z.array(translationSchema),
})
export type MenuForm = z.infer<typeof formSchema>

export const defaultValues = {
  location: 'header',
  is_active: true,
  translations: [] as TranslationForm[],
}

export function buildDefaultValues(languages: Language[]): MenuForm {
  return {
    location: 'header',
    is_active: true,
    translations: languages.map((language, index) => ({
      language_id: index,
      language: language.code,
      title: '',
      description: '',
    })),
  }
}

export function buildEditValues(
  languages: Language[],
  currentRow?: Menu
): MenuForm {
  const base = buildDefaultValues(languages)
  if (!currentRow) return base

  const translationsMap = new Map(
    currentRow.translations.map((t) => [t.language, t])
  )

  return {
    location: currentRow.location,
    is_active: currentRow.is_active,
    translations: base.translations.map((baseTranslation, index) => {
      const existing = translationsMap.get(baseTranslation.language)
      return existing
        ? { ...baseTranslation, ...existing }
        : { ...baseTranslation, language_id: index }
    }),
  }
}
