import { createFileRoute } from '@tanstack/react-router'
import { PostCategories } from '@/features/content/post-categories'


export const Route = createFileRoute('/_authenticated/content/post-categories/')({
  component: PostCategories,
})
