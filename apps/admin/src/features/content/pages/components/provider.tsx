import React, { useState } from 'react'
import useDialogState from '@/hooks/use-dialog-state'
import { type Page } from '../data/schema'

type PagesDialogType = 'add' | 'edit' | 'delete'

type PagesContextType = {
  open: PagesDialogType | null
  setOpen: (str: PagesDialogType | null) => void
  currentRow: Page | null
  setCurrentRow: React.Dispatch<React.SetStateAction<Page | null>>
}

const PagesContext = React.createContext<PagesContextType | null>(null)

export function Provider({ children }: { children: React.ReactNode }) {
  const [open, setOpen] = useDialogState<PagesDialogType>(null)
  const [currentRow, setCurrentRow] = useState<Page | null>(null)

  return (
    <PagesContext value={{ open, setOpen, currentRow, setCurrentRow }}>
      {children}
    </PagesContext>
  )
}

// eslint-disable-next-line react-refresh/only-export-components
export const usePages = () => {
  const pagesContext = React.useContext(PagesContext)

  if (!pagesContext) {
    throw new Error('usePages has to be used within <PagesContext>')
  }

  return pagesContext
}
