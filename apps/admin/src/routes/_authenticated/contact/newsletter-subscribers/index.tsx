import { createFileRoute } from '@tanstack/react-router'
import { NewsletterSubscribers } from '@/features/contact/newsletter-subscribers'


export const Route = createFileRoute('/_authenticated/contact/newsletter-subscribers/')({
  component: NewsletterSubscribers,
})
