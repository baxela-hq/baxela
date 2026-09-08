import { createFileRoute } from '@tanstack/react-router'
import { MenuLinks } from '@/features/menu/menu-links'


export const Route = createFileRoute('/_authenticated/menu/menu-links/$id/')({
  component: MenuLinks,
})
