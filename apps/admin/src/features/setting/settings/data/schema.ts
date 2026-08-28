import { z } from 'zod'

export const settingSchema = z.object({
  name: z.string(),
  value: z.string(),
  group: z.string(),
  type: z.string(),
  comment: z.string().nullable(),
})

export type Setting = z.infer<typeof settingSchema>

export const formSchema = z.object({
  website_title: z.string().min(2).max(255),
  website_keywords: z.string().min(2).max(255),
  website_description: z.string().min(5),
})
export type SettingForm = z.infer<typeof formSchema>


export const SettingRequestSchema = z.object({
  name: z.string(),
  value: z.string(),
})
export type SettingRequest = z.infer<typeof SettingRequestSchema>

export const defaultValues: SettingForm = {
  website_title: "",
  website_keywords: "",
  website_description: "",
}





