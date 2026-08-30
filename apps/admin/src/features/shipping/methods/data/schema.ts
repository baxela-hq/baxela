import { z } from 'zod'
import type { Language } from '@/shared/types/locale.types'

export const translationSchema = z.object({
  language_id: z.number(),
  language: z.string(),
  name: z.string().min(1, 'required'),
  description: z.string().nullable(),
})
export type MethodTranslationForm = z.infer<typeof translationSchema>

export const methodSchema = z.object({
  id: z.number(),
  code: z.string(),
  is_active: z.boolean(),
  position: z.string(),
  translations: z.array(translationSchema),
  created_at: z.string(),
  updated_at: z.string(),
})
export type Method = z.infer<typeof methodSchema>

export const formSchema = z.object({
  code: z.string().min(1, 'required').max(255),
  is_active: z.boolean(),
  position: z.string().max(255),
  translations: z.array(translationSchema),
})
export type MethodForm = z.infer<typeof formSchema>

export function buildDefaultValues(languages: Language[]): MethodForm {
  return {
    code: '',
    is_active: true,
    position: '1',
    translations: languages.map((language, index) => ({
      language_id: index,
      language: language.code,
      name: '',
      description: '',
    })),
  }
}

export function buildEditValues(
  languages: Language[],
  currentRow?: Method
): MethodForm {
  const base = buildDefaultValues(languages)
  if (!currentRow) return base

  const translationsMap = new Map(
    currentRow.translations.map((t) => [t.language, t])
  )

  return {
    code: currentRow.code,
    is_active: currentRow.is_active,
    position: currentRow.position.toString(),
    translations: base.translations.map((baseTranslation, index) => {
      const existing = translationsMap.get(baseTranslation.language)
      return existing
        ? { ...baseTranslation, ...existing }
        : { ...baseTranslation, language_id: index }
    }),
  }
}
