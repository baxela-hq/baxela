import { createFileRoute } from '@tanstack/react-router'
import { Settings } from '@/features/setting/settings/index.tsx'


export const Route = createFileRoute('/_authenticated/setting/settings/')({
  component: Settings,
})