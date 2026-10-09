import { z } from 'zod'
import type { Language } from '@/shared/types/locale.types'

export const statuses = [
  'draft',
  'published'
];

const _postStatusSchema = z.enum(statuses);

export type PostStatus = z.infer<typeof _postStatusSchema>

export const translationSchema = z.object({
  language_id: z.number(),
  language: z.string(),
  title: z.string().min(1, 'required'),
  slug: z.string().min(1, 'required'),
  description: z.string().nullable(),
  content: z.string().nullable(),
});
export type TranslationForm = z.infer<typeof translationSchema>

// categories nested on a post: translations are not loaded on this relation
const postCategoryRefSchema = z.object({
  id: z.number(),
  parent_id: z.number().nullable(),
  position: z.number().nullable(),
  created_at: z.string(),
  updated_at: z.string(),
})

export const postSchema = z.object({
  id: z.number(),
  translations: z.array(translationSchema),
  status: z.string(),
  is_featured: z.boolean(),
  categories: z.array(postCategoryRefSchema).optional(),
  created_at: z.string(),
  updated_at: z.string(),
})

export type Post = z.infer<typeof postSchema>

export const formSchema = z.object({
  status: _postStatusSchema,
  is_featured: z.boolean(),
  categories: z.array(z.number()),
  translations: z.array(translationSchema),
})
export type PostForm = z.infer<typeof formSchema>

export function buildDefaultValues(languages: Language[]): PostForm {
  return {
    status: '',
    is_featured: false,
    categories: [],
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
  currentRow?: Post
): PostForm {
  const base = buildDefaultValues(languages)
  if (!currentRow) return base

  const translationsMap = new Map(
    currentRow.translations.map((t) => {
      return [t.language, t];
    })
  )

  return {
    status: currentRow.status,
    is_featured: currentRow.is_featured,
    categories: currentRow.categories?.map((category) => category.id) ?? [],
    translations: base.translations.map((baseTranslation, index) => {
      const existing = translationsMap.get(baseTranslation.language)
      return existing
        ? { ...baseTranslation, ...existing }
        : { ...baseTranslation, language_id: index }
    }),
  }
}
