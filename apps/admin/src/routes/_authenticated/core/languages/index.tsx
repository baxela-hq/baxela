import { createFileRoute } from '@tanstack/react-router'
import { Languages } from '@/features/core/languages'

export const Route = createFileRoute('/_authenticated/core/languages/')({
  component: Languages,
})
