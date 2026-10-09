import { createFileRoute } from '@tanstack/react-router'
import { Posts } from '@/features/content/posts'


export const Route = createFileRoute('/_authenticated/content/posts/')({
  component: Posts,
})
