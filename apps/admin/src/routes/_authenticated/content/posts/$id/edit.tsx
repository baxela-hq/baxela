import { createFileRoute } from '@tanstack/react-router'
import { PostForm } from '@/features/content/posts/form'


export const Route = createFileRoute('/_authenticated/content/posts/$id/edit')({
  component: PostForm,
})
