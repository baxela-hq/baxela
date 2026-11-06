import { createFileRoute } from '@tanstack/react-router'
import { TransferData } from '@/features/transfer/data'

export const Route = createFileRoute('/_authenticated/transfer/')({
  component: TransferData,
})
