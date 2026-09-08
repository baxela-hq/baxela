import React, { useState } from 'react'
import useDialogState from '@/hooks/use-dialog-state'
import { type ContactMessage } from '../data/schema'

type ContactMessagesDialogType = 'view' | 'delete'

type ContactMessagesContextType = {
  open: ContactMessagesDialogType | null
  setOpen: (str: ContactMessagesDialogType | null) => void
  currentRow: ContactMessage | null
  setCurrentRow: React.Dispatch<React.SetStateAction<ContactMessage | null>>
}

const ContactMessagesContext =
  React.createContext<ContactMessagesContextType | null>(null)

export function Provider({ children }: { children: React.ReactNode }) {
  const [open, setOpen] = useDialogState<ContactMessagesDialogType>(null)
  const [currentRow, setCurrentRow] = useState<ContactMessage | null>(null)

  return (
    <ContactMessagesContext
      value={{ open, setOpen, currentRow, setCurrentRow }}
    >
      {children}
    </ContactMessagesContext>
  )
}

// eslint-disable-next-line react-refresh/only-export-components
export const useContactMessages = () => {
  const contactMessagesContext = React.useContext(ContactMessagesContext)

  if (!contactMessagesContext) {
    throw new Error(
      'useContactMessages has to be used within <ContactMessagesContext>'
    )
  }

  return contactMessagesContext
}
