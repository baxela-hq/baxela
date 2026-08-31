import { z } from 'zod'
import { type Translation } from '@/shared/types/locale.types'

export const productCommentStatusSchema = z.enum([
  'pending',
  'approved',
  'rejected',
])
export type ProductCommentStatus = z.infer<typeof productCommentStatusSchema>

interface ProductCommentTranslation extends Translation {
  title: string | null
}

const productCommentProductSchema = z.object({
  id: z.number(),
  translations: z.array(z.custom<ProductCommentTranslation>()),
})

export const productCommentSchema = z.object({
  id: z.number(),
  product_id: z.number(),
  parent_id: z.number().nullable(),
  user_id: z.number(),
  body: z.string(),
  status: productCommentStatusSchema,
  created_at: z.string(),
  updated_at: z.string(),
  user: z
    .object({
      id: z.number(),
      name: z.string().nullable(),
    })
    .nullable(),
  product: productCommentProductSchema.nullable(),
})
export type ProductComment = z.infer<typeof productCommentSchema>

// admin reply: replies always target a top-level comment of a product
export const replyRequestSchema = z.object({
  product_id: z.number(),
  parent_id: z.number(),
  body: z.string().min(1, 'required'),
})
export type ProductCommentReplyRequest = z.infer<typeof replyRequestSchema>

// the UI always sends the whole record on update, so every field is patched
export const productCommentFormSchema = z.object({
  product_id: z.number(),
  parent_id: z.number().nullable(),
  body: z.string().min(1, 'required'),
  status: productCommentStatusSchema,
})
export type ProductCommentForm = z.infer<typeof productCommentFormSchema>

export const replyFormSchema = z.object({
  body: z.string().min(1, 'required'),
})
export type ProductCommentReplyForm = z.infer<typeof replyFormSchema>

export const editFormSchema = z.object({
  body: z.string().min(1, 'required'),
  status: productCommentStatusSchema,
})
export type ProductCommentEditForm = z.infer<typeof editFormSchema>
