import { z } from 'zod'
import type { Language } from '@/shared/types/locale.types'

export const translationSchema = z.object({
  language_id: z.number(),
  language: z.string(),
  title: z.string().min(1, 'required'),
  slug: z.string().min(1, 'required'),
  description: z.string().nullable(),
});
export type TranslationForm = z.infer<typeof translationSchema>

export const categorySchema = z.object({
  id: z.number(),
  parent_id: z.number().nullable(),
  translations: z.array(translationSchema),
  position: z.string(),
  created_at: z.string(),
  updated_at: z.string(),
})
export type Category = z.infer<typeof categorySchema>

export type CategoryNode = Category & { title: string; depth: number }



export const formSchema = z.object({
  parent_id: z.number().nullable(),
  position: z.string().max(255),
  translations: z.array(translationSchema),
})
export type CategoryForm = z.infer<typeof formSchema>

export const defaultValues = {
  parent_id: null,
  position: '1',
  translations: [] as TranslationForm[],
}

export function buildDefaultValues(languages: Language[]): CategoryForm {
  return {
    parent_id: null,
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
  currentRow?: Category
): CategoryForm {
  const base = buildDefaultValues(languages)
  if (!currentRow) return base

  const translationsMap = new Map(
    currentRow.translations.map((t) => [t.language, t])
  )

  return {
    parent_id: currentRow.parent_id,
    position: currentRow.position.toString(),
    translations: base.translations.map((baseTranslation, index) => {
      const existing = translationsMap.get(baseTranslation.language)
      return existing
        ? { ...baseTranslation, ...existing }
        : { ...baseTranslation, language_id: index }
    }),
  }
}
