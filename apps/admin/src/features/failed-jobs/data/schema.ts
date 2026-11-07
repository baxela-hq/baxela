import { z } from 'zod'

/**
 * List rows carry the server-side exception excerpt; the show endpoint adds
 * the full exception and the redacted payload (serialized command replaced,
 * secret-ish keys masked server-side — gap-B3 D2/D3).
 */
export const failedJobSchema = z.object({
  uuid: z.string(),
  job: z.string().nullable(),
  connection: z.string(),
  queue: z.string(),
  failed_at: z.string().nullable(),
  exception: z.string(),
  payload: z.unknown().optional(),
})

export type FailedJob = z.infer<typeof failedJobSchema>
