import { z } from 'zod'
import { attributeGroupSchema } from '../../attribute-groups/data/schema'
import { attributeSchema } from '../../attributes/data/schema'
import { attributeValueSchema } from '../../attribute-values/data/schema'

export const attributeTemplateSchema = z.object({
  id: z.number(),
  title: z.string(),
  description: z.string().nullable(),
  is_active: z.boolean(),
  position: z.string(),
  // Only loaded on show/create/update responses — the list returns `groups_count` instead.
  groups: z.array(attributeGroupSchema).nullish(),
  groups_count: z.number().nullish(),
  created_at: z.string(),
  updated_at: z.string(),
})
export type AttributeTemplate = z.infer<typeof attributeTemplateSchema>

// The show/create/update responses deep-load groups.attributes.values, each with
// translations — typing only, the API layer never parses at runtime.
const _attributeTemplateDetailSchema = attributeTemplateSchema.extend({
  groups: z
    .array(
      attributeGroupSchema.extend({
        attributes: z
          .array(attributeSchema.extend({ values: z.array(attributeValueSchema).nullish() }))
          .nullish(),
      })
    )
    .nullish(),
})
export type AttributeTemplateDetail = z.infer<typeof _attributeTemplateDetailSchema>

export const formSchema = z.object({
  title: z.string().min(1, 'required').max(255),
  description: z.string().nullable(),
  is_active: z.boolean(),
  position: z.string().max(255),
  group_ids: z.array(z.number()),
})
export type AttributeTemplateForm = z.infer<typeof formSchema>

export const defaultValues: AttributeTemplateForm = {
  title: '',
  description: null,
  is_active: true,
  position: '1',
  group_ids: [],
}

export function buildEditValues(currentRow?: AttributeTemplate): AttributeTemplateForm {
  if (!currentRow) return { ...defaultValues }

  return {
    title: currentRow.title,
    description: currentRow.description,
    is_active: currentRow.is_active,
    position: currentRow.position.toString(),
    group_ids: (currentRow.groups ?? []).map((group) => group.id),
  }
}
