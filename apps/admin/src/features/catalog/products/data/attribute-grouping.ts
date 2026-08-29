import { pickTranslation } from '@/shared/lib/locale'
import { type AttributeGroup } from '@/features/catalog/attribute-groups/data/schema'
import { type Attribute } from '@/features/catalog/attributes/data/schema'
import { type AttributeValue } from '@/features/catalog/attribute-values/data/schema'
import { type ProductAttributeValueEntry } from './schema'

export function attributeTitle(attribute: Attribute) {
  return pickTranslation(attribute.translations)?.title ?? attribute.code
}

export function attributeValueTitle(value: AttributeValue) {
  return pickTranslation(value.translations)?.title ?? `#${value.id}`
}

export function attributeGroupTitle(group: AttributeGroup) {
  return pickTranslation(group.translations)?.title ?? `#${group.id}`
}

export function byPosition(a?: Attribute, b?: Attribute) {
  if (!a || !b) return 0
  return Number(a.position) - Number(b.position) || a.id - b.id
}

export function buildAttributeMap(attributes: Attribute[]) {
  const map = new Map<number, Attribute>()
  attributes.forEach((attribute) => {
    if (!map.has(attribute.id)) map.set(attribute.id, attribute)
  })
  return map
}

/** Groups sorted by position — the order groups render in everywhere. */
export function orderGroupsByPosition(groups: AttributeGroup[]) {
  return groups
    .slice()
    .sort(
      (a, b) => Number(a.position) - Number(b.position) || a.id - b.id
    )
}

export type PickerGroups = {
  known: Map<number, Attribute[]>
  ungrouped: Attribute[]
}

/**
 * Attributes available in the picker (not yet selected), grouped by their
 * attribute group and ordered by position.
 */
export function buildPickerGroups(
  attributes: Attribute[],
  groupById: Map<number, AttributeGroup>,
  selectedIds: Set<number>
): PickerGroups {
  const known = new Map<number, Attribute[]>()
  const ungrouped: Attribute[] = []
  attributes.forEach((attribute) => {
    if (selectedIds.has(attribute.id)) return
    if (groupById.has(attribute.group_id)) {
      const list = known.get(attribute.group_id) ?? []
      list.push(attribute)
      known.set(attribute.group_id, list)
    } else {
      ungrouped.push(attribute)
    }
  })
  known.forEach((list) => list.sort(byPosition))
  ungrouped.sort(byPosition)
  return { known, ungrouped }
}

export type DisplaySection = {
  key: string
  title: string
  rows: { index: number; attribute?: Attribute }[]
}

/**
 * Selected rows laid out as sections: one per attribute group (ordered,
 * rows sorted by position) plus a trailing ungrouped section.
 */
export function buildDisplaySections(
  fields: { attribute_id: number }[],
  attributeById: Map<number, Attribute>,
  groupById: Map<number, AttributeGroup>,
  orderedGroups: AttributeGroup[],
  ungroupedTitle: string
): DisplaySection[] {
  const rowsByGroup = new Map<number, { index: number; attribute?: Attribute }[]>()
  const ungrouped: { index: number; attribute?: Attribute }[] = []
  fields.forEach((field, index) => {
    const attribute = attributeById.get(field.attribute_id)
    const row = { index, attribute }
    if (attribute && groupById.has(attribute.group_id)) {
      const list = rowsByGroup.get(attribute.group_id) ?? []
      list.push(row)
      rowsByGroup.set(attribute.group_id, list)
    } else {
      ungrouped.push(row)
    }
  })

  const sections = orderedGroups
    .map((group) => ({
      key: `group-${group.id}`,
      title: attributeGroupTitle(group),
      rows: (rowsByGroup.get(group.id) ?? [])
        .slice()
        .sort((a, b) => byPosition(a.attribute, b.attribute)),
    }))
    .filter((section) => section.rows.length > 0)

  if (ungrouped.length > 0) {
    sections.push({ key: 'ungrouped', title: ungroupedTitle, rows: ungrouped })
  }
  return sections
}

export function makeEmptyEntry(attribute: Attribute): ProductAttributeValueEntry {
  return {
    attribute_id: attribute.id,
    data_type: attribute.data_type,
    value_ids: [],
    text_value: null,
    number_value: null,
    // A boolean switch is always meaningful (off = false), so it starts set.
    boolean_value: attribute.data_type === 'boolean' ? false : null,
  }
}

/** An entry the user has not given a meaningful value yet (per data_type). */
export function isEntryUnset(entry: ProductAttributeValueEntry) {
  if (entry.data_type === 'select' || entry.data_type === 'multiselect') {
    return entry.value_ids.length === 0
  }
  if (entry.data_type === 'text') return !entry.text_value?.trim()
  if (entry.data_type === 'number') return !entry.number_value?.trim()
  return false
}

/** Flatten a template's groups → attributes (used when loading a template). */
export function flattenTemplateAttributes(template: {
  groups?: { attributes?: unknown[] | null }[] | null
}): Attribute[] {
  const templateAttributes: Attribute[] = []
  ;(template.groups ?? []).forEach((group) => {
    ;((group.attributes ?? []) as Attribute[]).forEach((attribute) =>
      templateAttributes.push(attribute)
    )
  })
  return templateAttributes
}

/** Merge template-discovered attributes into the extras list (no duplicates). */
export function mergeExtraAttributes(
  prev: Attribute[],
  templateAttributes: Attribute[]
): Attribute[] {
  const known = new Set(prev.map((attribute) => attribute.id))
  return [
    ...prev,
    ...templateAttributes.filter((attribute) => !known.has(attribute.id)),
  ]
}
