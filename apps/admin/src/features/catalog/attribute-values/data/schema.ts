import { z } from 'zod'
import type { Language } from '@/shared/types/locale.types'

export const translationSchema = z.object({
  language_id: z.number(),
  language: z.string(),
  title: z.string().min(1, 'required'),
})
export type TranslationForm = z.infer<typeof translationSchema>

// The API returns `attribute_id` as a string on create but as an int on list — cosmetic backend quirk.
export const attributeValueSchema = z.object({
  id: z.number(),
  attribute_id: z.union([z.number(), z.string()]),
  translations: z.array(translationSchema),
  position: z.string(),
  created_at: z.string(),
  updated_at: z.string(),
})
export type AttributeValue = z.infer<typeof attributeValueSchema>

export const formSchema = z.object({
  position: z.string().max(255),
  translations: z.array(translationSchema),
})
export type AttributeValueForm = z.infer<typeof formSchema>

export function buildDefaultValues(languages: Language[]): AttributeValueForm {
  return {
    position: '1',
    translations: languages.map((language, index) => ({
      language_id: index,
      language: language.code,
      title: '',
    })),
  }
}

export function buildEditValues(
  languages: Language[],
  currentRow?: AttributeValue
): AttributeValueForm {
  const base = buildDefaultValues(languages)
  if (!currentRow) return base

  const translationsMap = new Map(
    currentRow.translations.map((t) => [t.language, t])
  )

  return {
    position: currentRow.position.toString(),
    translations: base.translations.map((baseTranslation, index) => {
      const existing = translationsMap.get(baseTranslation.language)
      return existing
        ? { ...baseTranslation, ...existing }
        : { ...baseTranslation, language_id: index }
    }),
  }
}
