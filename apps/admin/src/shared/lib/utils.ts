import { toast } from 'sonner'
import { type ApiError } from './api-error.ts'

export function parseAndToastError(error: ApiError) {
  if (Object.keys( error.errors).length > 0) {
    Object.entries(error.errors).forEach(([_field, messages]) => {
      ;(messages as string[]).forEach((message: string) => {
        toast.error(message)
      })
    })
  }
}

export function ucfirst(word: string) {
  if (!word) return word
  return word[0].toUpperCase() + word.substr(1).toLowerCase()
}
