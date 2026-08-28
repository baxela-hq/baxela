import { z } from 'zod'
import type { Language } from '@/shared/types/locale.types'

export const translationSchema = z.object({
  language_id: z.number(),
  language: z.string(),
  title: z.string().min(1, 'required'),
  slug: z.string().min(1, 'required'),
});
export type TranslationForm = z.infer<typeof translationSchema>

export const optionValueSchema = z.object({
  id: z.number(),
  option_id: z.number(),
  translations: z.array(translationSchema),
  position: z.string(),
  created_at: z.string(),
  updated_at: z.string(),
})
export type OptionValue = z.infer<typeof optionValueSchema>



export const formSchema = z.object({
  position: z.string().max(255),
  translations: z.array(translationSchema),
})
export type OptionValueForm = z.infer<typeof formSchema>

export const defaultValues = {
  position: '1',
  translations: [] as TranslationForm[],
}

export function buildDefaultValues(languages: Language[]): OptionValueForm {
  return {
    position: '1',
    translations: languages.map((language, index) => ({
      language_id: index,
      language: language.code,
      title: '',
      slug: '',
      description: '',
    })),
  }
}

export function buildEditValues(
  languages: Language[],
  currentRow?: OptionValue
): OptionValueForm {
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
