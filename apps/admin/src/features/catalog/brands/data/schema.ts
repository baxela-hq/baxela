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

export const brandSchema = z.object({
  id: z.number(),
  translations: z.array(translationSchema),
  position: z.string(),
  image_media_id: z.number().nullable(),
  image_url: z.string().nullable(),
  created_at: z.string(),
  updated_at: z.string(),
})
export type Brand = z.infer<typeof brandSchema>

export const formSchema = z.object({
  position: z.string().max(255),
  image_media_id: z.number().nullable(),
  image_url: z.string().nullable(),
  translations: z.array(translationSchema),
})
export type BrandForm = z.infer<typeof formSchema>

export const defaultValues = {
  position: '1',
  image_media_id: null,
  image_url: null,
  translations: [] as TranslationForm[],
}

export function buildDefaultValues(languages: Language[]): BrandForm {
  return {
    position: '1',
    image_media_id: null,
    image_url: null,
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
  currentRow?: Brand
): BrandForm {
  const base = buildDefaultValues(languages)
  if (!currentRow) return base

  const translationsMap = new Map(
    currentRow.translations.map((t) => [t.language, t])
  )

  return {
    position: currentRow.position.toString(),
    image_media_id: currentRow.image_media_id,
    image_url: currentRow.image_url,
    translations: base.translations.map((baseTranslation, index) => {
      const existing = translationsMap.get(baseTranslation.language)
      return existing
        ? { ...baseTranslation, ...existing }
        : { ...baseTranslation, language_id: index }
    }),
  }
}
