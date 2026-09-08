import { z } from 'zod'
import type { Language } from '@/shared/types/locale.types'

export const MENU_LINK_TARGETS = ['_self', '_blank'] as const
export type MenuLinkTarget = (typeof MENU_LINK_TARGETS)[number]

/** Sentinel select value meaning "no parent" (root link). */
export const NO_PARENT = 'none'

export const translationSchema = z.object({
  language_id: z.number(),
  language: z.string(),
  title: z.string().min(1, 'required'),
  description: z.string(),
});
export type TranslationForm = z.infer<typeof translationSchema>

export const menuLinkSchema = z.object({
  id: z.number(),
  menu_id: z.number(),
  parent_id: z.number().nullable(),
  url: z.string(),
  target: z.string(),
  position: z.number().nullable(),
  translations: z.array(translationSchema),
  created_at: z.string(),
  updated_at: z.string(),
})
export type MenuLink = z.infer<typeof menuLinkSchema>

export const formSchema = z.object({
  parent_id: z.string(),
  url: z.string().min(1, 'required'),
  target: z.string(),
  position: z.string().max(255),
  translations: z.array(translationSchema),
})
export type MenuLinkForm = z.infer<typeof formSchema>

/** API body shape: the select sentinel is resolved to a real parent id or null. */
export type MenuLinkPayload = Omit<MenuLinkForm, 'parent_id'> & {
  parent_id: number | string | null
}

export const defaultValues = {
  parent_id: NO_PARENT,
  url: '',
  target: '_self',
  position: '1',
  translations: [] as TranslationForm[],
}

export function buildDefaultValues(languages: Language[]): MenuLinkForm {
  return {
    parent_id: NO_PARENT,
    url: '',
    target: '_self',
    position: '1',
    translations: languages.map((language, index) => ({
      language_id: index,
      language: language.code,
      title: '',
      description: '',
    })),
  }
}

export function buildEditValues(
  languages: Language[],
  currentRow?: MenuLink
): MenuLinkForm {
  const base = buildDefaultValues(languages)
  if (!currentRow) return base

  const translationsMap = new Map(
    currentRow.translations.map((t) => [t.language, t])
  )

  return {
    parent_id: currentRow.parent_id === null ? NO_PARENT : currentRow.parent_id.toString(),
    url: currentRow.url,
    target: currentRow.target,
    position: (currentRow.position ?? 1).toString(),
    translations: base.translations.map((baseTranslation, index) => {
      const existing = translationsMap.get(baseTranslation.language)
      return existing
        ? { ...baseTranslation, ...existing }
        : { ...baseTranslation, language_id: index }
    }),
  }
}

/** Convert the form's select sentinel into the API payload shape. */
export function toPayload(data: MenuLinkForm): MenuLinkPayload {
  return {
    ...data,
    parent_id: data.parent_id === NO_PARENT ? null : data.parent_id,
  }
}
