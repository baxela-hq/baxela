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

export const postSeoTranslationSchema = z.object({
  language_id: z.number(),
  language: z.string(),
  meta_title: z.string().max(255).nullable(),
  meta_description: z.string().max(255).nullable(),
  open_graph_title: z.string().max(255).nullable(),
  open_graph_description: z.string().max(255).nullable(),
});
export type PostSeoTranslation = z.infer<typeof postSeoTranslationSchema>

// categories nested on a post: translations are not loaded on this relation
const postCategoryRefSchema = z.object({
  id: z.number(),
  parent_id: z.number().nullable(),
  position: z.number().nullable(),
  created_at: z.string(),
  updated_at: z.string(),
})

// related products nested on a post: only ids come back (titles resolve
// through the picker's own product queries)
const postProductRefSchema = z.object({
  id: z.number(),
  title: z.string().nullable().optional(),
  image_url: z.string().nullable().optional(),
})

export const postSchema = z.object({
  id: z.number(),
  translations: z.array(translationSchema),
  status: z.string(),
  published_at: z.string().nullable(),
  categories: z.array(postCategoryRefSchema).optional(),
  products: z.array(postProductRefSchema).optional(),
  seo: z.array(postSeoTranslationSchema).optional(),
  created_at: z.string(),
  updated_at: z.string(),
})

export type Post = z.infer<typeof postSchema>

export const formSchema = z.object({
  status: _postStatusSchema,
  // Date in the form, ISO string over the wire (see PostPayload)
  published_at: z.date().nullable(),
  categories: z.array(z.number()),
  products: z.array(z.number()),
  seo: z.array(postSeoTranslationSchema),
  translations: z.array(translationSchema),
})
export type PostForm = z.infer<typeof formSchema>

export type PostPayload = Omit<PostForm, 'published_at'> & {
  published_at: string | null
}

export function buildDefaultValues(languages: Language[]): PostForm {
  return {
    status: '',
    published_at: null,
    categories: [],
    products: [],
    seo: languages.map((language, index) => ({
      language_id: index,
      language: language.code,
      meta_title: null,
      meta_description: null,
      open_graph_title: null,
      open_graph_description: null,
    })),
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

  const seoMap = new Map(
    (currentRow.seo ?? []).map((item) => {
      return [item.language, item];
    })
  )

  return {
    status: currentRow.status,
    published_at: currentRow.published_at ? new Date(currentRow.published_at) : null,
    categories: currentRow.categories?.map((category) => category.id) ?? [],
    products: currentRow.products?.map((product) => product.id) ?? [],
    seo: base.seo.map((baseSeo, index) => {
      const existing = seoMap.get(baseSeo.language)
      return existing
        ? { ...baseSeo, ...existing }
        : { ...baseSeo, language_id: index }
    }),
    translations: base.translations.map((baseTranslation, index) => {
      const existing = translationsMap.get(baseTranslation.language)
      return existing
        ? { ...baseTranslation, ...existing }
        : { ...baseTranslation, language_id: index }
    }),
  }
}
