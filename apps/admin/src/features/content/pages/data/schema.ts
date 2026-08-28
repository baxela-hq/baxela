import { z } from 'zod'
import type { Language } from '@/shared/types/locale.types'

export const statuses = [
  'draft',
  'published'
];

const _pageStatusSchema = z.enum(statuses);

export type PageStatus = z.infer<typeof _pageStatusSchema>

export const translationSchema = z.object({
  language_id: z.number(),
  language: z.string(),
  title: z.string().min(1, 'required'),
  slug: z.string().min(1, 'required'),
  description: z.string().nullable(),
  content: z.string().nullable(),
});
export type TranslationForm = z.infer<typeof translationSchema>

export const pageSchema = z.object({
  id: z.number(),
  translations: z.array(translationSchema),
  status: z.string(),
  created_at: z.string(),
  updated_at: z.string(),
})

export type Page = z.infer<typeof pageSchema>

export const formSchema = z.object({
  status: _pageStatusSchema,
  translations: z.array(translationSchema),
})
export type PageForm = z.infer<typeof formSchema>

export function buildDefaultValues(languages: Language[]): PageForm {
  return {
    status: '',
    translations: languages.map((language, index) => ({
      language_id: index,
      language: language.code,
      title: '',
      slug: '',
      description: '',
      content: '',
    })),
  }
}

export function buildEditValues(
  languages: Language[],
  currentRow?: Page
): PageForm {
  const base = buildDefaultValues(languages)
  if (!currentRow) return base

  const translationsMap = new Map(
    currentRow.translations.map((t) => {
      return [t.language, t];
    })
  )

  return {
    status: currentRow.status,
    translations: base.translations.map((baseTranslation, index) => {
      const existing = translationsMap.get(baseTranslation.language)
      return existing
        ? { ...baseTranslation, ...existing }
        : { ...baseTranslation, language_id: index }
    }),
  }
}
