import { createFileRoute } from '@tanstack/react-router'
import { SettingsStore } from '@/features/setting/settings'

export const Route = createFileRoute('/_authenticated/settings/store')({
  component: SettingsStore,
})
