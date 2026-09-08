import { createFileRoute } from '@tanstack/react-router'
import { Menus } from '@/features/menu/menus'


export const Route = createFileRoute('/_authenticated/menu/menus/')({
  component: Menus,
})
