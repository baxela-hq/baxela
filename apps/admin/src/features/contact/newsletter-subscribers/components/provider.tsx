import React, { useState } from 'react'
import useDialogState from '@/hooks/use-dialog-state'
import { type NewsletterSubscriber } from '../data/schema'

type NewsletterSubscribersDialogType = 'delete'

type NewsletterSubscribersContextType = {
  open: NewsletterSubscribersDialogType | null
  setOpen: (str: NewsletterSubscribersDialogType | null) => void
  currentRow: NewsletterSubscriber | null
  setCurrentRow: React.Dispatch<React.SetStateAction<NewsletterSubscriber | null>>
}

const NewsletterSubscribersContext = React.createContext<NewsletterSubscribersContextType | null>(null)

export function Provider({ children }: { children: React.ReactNode }) {
  const [open, setOpen] = useDialogState<NewsletterSubscribersDialogType>(null)
  const [currentRow, setCurrentRow] = useState<NewsletterSubscriber | null>(null)

  return (
    <NewsletterSubscribersContext value={{ open, setOpen, currentRow, setCurrentRow }}>
      {children}
    </NewsletterSubscribersContext>
  )
}

// eslint-disable-next-line react-refresh/only-export-components
export const useNewsletterSubscribers = () => {
  const newsletterSubscribersContext = React.useContext(NewsletterSubscribersContext)

  if (!newsletterSubscribersContext) {
    throw new Error('useNewsletterSubscribers has to be used within <NewsletterSubscribersContext>')
  }

  return newsletterSubscribersContext
}
