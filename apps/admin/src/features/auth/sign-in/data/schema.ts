import { z } from 'zod'

export type SignInForm = z.infer<ReturnType<typeof buildSignInFormSchema>>

/**
 * Schema factory takes the translated validation messages (the sign-in
 * namespace) — same pattern as the media module's schema factories.
 */
export function buildSignInFormSchema(messages: {
  emailRequired: string
  passwordRequired: string
  passwordMinLength: string
}) {
  return z.object({
    email: z.email({
      error: (iss) => (iss.input === '' ? messages.emailRequired : undefined),
    }),
    password: z
      .string()
      .min(1, messages.passwordRequired)
      .min(7, messages.passwordMinLength),
  })
}
