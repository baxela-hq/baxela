import z from 'zod'
import { createFileRoute } from '@tanstack/react-router'
import { Media } from '@/features/media'

const mediaSearchSchema = z.object({
  // Currently browsed folder id (absent = root)
  folder: z.number().optional(),
})

export const Route = createFileRoute('/_authenticated/media/')({
  validateSearch: mediaSearchSchema,
  component: Media,
})
