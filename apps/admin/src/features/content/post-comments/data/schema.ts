import { z } from 'zod'
import { type Translation } from '@/shared/types/locale.types'

export const postCommentStatusSchema = z.enum([
  'pending',
  'approved',
  'rejected',
])
export type PostCommentStatus = z.infer<typeof postCommentStatusSchema>

interface PostCommentTranslation extends Translation {
  title: string | null
}

const postCommentPostSchema = z.object({
  id: z.number(),
  translations: z.array(z.custom<PostCommentTranslation>()),
})

export const postCommentSchema = z.object({
  id: z.number(),
  post_id: z.number(),
  parent_id: z.number().nullable(),
  user_id: z.number(),
  body: z.string(),
  status: postCommentStatusSchema,
  created_at: z.string(),
  updated_at: z.string(),
  user: z
    .object({
      id: z.number(),
      name: z.string().nullable(),
    })
    .nullable(),
  post: postCommentPostSchema.nullable(),
})
export type PostComment = z.infer<typeof postCommentSchema>

// admin reply: replies always target a top-level comment of a post
export const replyRequestSchema = z.object({
  post_id: z.number(),
  parent_id: z.number(),
  body: z.string().min(1, 'required'),
})
export type PostCommentReplyRequest = z.infer<typeof replyRequestSchema>

// the UI always sends the whole record on update, so every field is patched
export const postCommentFormSchema = z.object({
  post_id: z.number(),
  parent_id: z.number().nullable(),
  body: z.string().min(1, 'required'),
  status: postCommentStatusSchema,
})
export type PostCommentForm = z.infer<typeof postCommentFormSchema>

export const replyFormSchema = z.object({
  body: z.string().min(1, 'required'),
})
export type PostCommentReplyForm = z.infer<typeof replyFormSchema>

export const editFormSchema = z.object({
  body: z.string().min(1, 'required'),
  status: postCommentStatusSchema,
})
export type PostCommentEditForm = z.infer<typeof editFormSchema>
