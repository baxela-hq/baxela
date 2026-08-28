import { z } from 'zod'

export const userSchema = z.object({
  id: z.number(),
  email: z.string(),
  email_verified_at: z.string(),
  comment: z.string(),
  is_active: z.boolean(),
  is_admin: z.boolean(),
  created_at: z.string(),
  updated_at: z.string(),
})

export type User = z.infer<typeof userSchema>

export const formSchema = z.object({
  email: z.email().nonempty(),
  password: z.string().min(8).max(40).optional(),
  is_active: z.boolean(),
  is_admin: z.boolean(),
  comment: z.string().optional(),
  mode: z.string(),
}).refine(data => {
  // When creating, password is required
  if (data.mode === 'create') {
    return data.password !== undefined && data.password.length > 0;
  }
  // When updating, password is optional
  return true;
})
export type UserForm = z.infer<typeof formSchema>

export const defaultValues = {
  email: '',
  password: '',
  is_active: false,
  is_admin: false,
  comment: '',
  mode: 'create',
}


