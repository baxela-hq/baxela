import { z } from 'zod'
import type { Language } from '@/shared/types/locale.types'
import { attributeGroupSchema } from '../../attribute-groups/data/schema'

export const DATA_TYPES = ['text', 'number', 'boolean', 'select', 'multiselect'] as const
export type DataType = (typeof DATA_TYPES)[number]

export const translationSchema = z.object({
  language_id: z.number(),
  language: z.string(),
  title: z.string().min(1, 'required'),
})
export type TranslationForm = z.infer<typeof translationSchema>

export const attributeSchema = z.object({
  id: z.number(),
  group_id: z.number(),
  code: z.string(),
  data_type: z.enum(DATA_TYPES),
  is_filterable: z.boolean(),
  position: z.string(),
  translations: z.array(translationSchema),
  values_count: z.number().nullish(),
  group: attributeGroupSchema.nullish(),
  created_at: z.string(),
  updated_at: z.string(),
})
export type Attribute = z.infer<typeof attributeSchema>

export const formSchema = z.object({
  group_id: z.number().min(1, 'required'),
  code: z.string().min(1, 'required').max(255),
  data_type: z.enum(DATA_TYPES),
  is_filterable: z.boolean(),
  position: z.string().max(255),
  translations: z.array(translationSchema),
})
export type AttributeForm = z.infer<typeof formSchema>

export function buildDefaultValues(languages: Language[]): AttributeForm {
  return {
    group_id: 0,
    code: '',
    data_type: 'text',
    is_filterable: false,
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
  currentRow?: Attribute
): AttributeForm {
  const base = buildDefaultValues(languages)
  if (!currentRow) return base

  const translationsMap = new Map(
    currentRow.translations.map((t) => [t.language, t])
  )

  return {
    group_id: currentRow.group_id,
    code: currentRow.code,
    data_type: currentRow.data_type,
    is_filterable: currentRow.is_filterable,
    position: currentRow.position.toString(),
    translations: base.translations.map((baseTranslation, index) => {
      const existing = translationsMap.get(baseTranslation.language)
      return existing
        ? { ...baseTranslation, ...existing }
        : { ...baseTranslation, language_id: index }
    }),
  }
}
