import { z } from 'zod'
import type { Language } from '@/shared/types/locale.types'

export const translationSchema = z.object({
  language_id: z.number(),
  language: z.string(),
  title: z.string().min(1, 'required'),
})
export type TranslationForm = z.infer<typeof translationSchema>

export const attributeGroupSchema = z.object({
  id: z.number(),
  position: z.string(),
  translations: z.array(translationSchema),
  attributes_count: z.number().nullish(),
  created_at: z.string(),
  updated_at: z.string(),
})
export type AttributeGroup = z.infer<typeof attributeGroupSchema>

export const formSchema = z.object({
  position: z.string().max(255),
  translations: z.array(translationSchema),
})
export type AttributeGroupForm = z.infer<typeof formSchema>

export function buildDefaultValues(languages: Language[]): AttributeGroupForm {
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
  currentRow?: AttributeGroup
): AttributeGroupForm {
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
