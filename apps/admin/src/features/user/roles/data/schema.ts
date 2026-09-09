import { z } from 'zod'

export const permissionSchema = z.object({
  id: z.number(),
  name: z.string(),
})

export type Permission = z.infer<typeof permissionSchema>

export const roleSchema = z.object({
  id: z.number(),
  name: z.string(),
  permissions: z.array(permissionSchema),
})

export type Role = z.infer<typeof roleSchema>

export const formSchema = z.object({
  name: z
    .string()
    .min(2)
    .max(64)
    .regex(/^[a-z0-9]+(?:-[a-z0-9]+)*$/),
  permission_ids: z.array(z.number()),
})

export type RoleForm = z.infer<typeof formSchema>

export const defaultValues = {
  name: '',
  permission_ids: [] as number[],
}
