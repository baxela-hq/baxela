import React, { useState } from 'react'
import useDialogState from '@/hooks/use-dialog-state'
import { type Attribute } from '../data/schema'

type AttributesDialogType = 'add' | 'edit' | 'delete'

type AttributesContextType = {
  open: AttributesDialogType | null
  setOpen: (str: AttributesDialogType | null) => void
  currentRow: Attribute | null
  setCurrentRow: React.Dispatch<React.SetStateAction<Attribute | null>>
}

const AttributesContext = React.createContext<AttributesContextType | null>(null)

export function Provider({ children }: { children: React.ReactNode }) {
  const [open, setOpen] = useDialogState<AttributesDialogType>(null)
  const [currentRow, setCurrentRow] = useState<Attribute | null>(null)

  return (
    <AttributesContext value={{ open, setOpen, currentRow, setCurrentRow }}>
      {children}
    </AttributesContext>
  )
}

// eslint-disable-next-line react-refresh/only-export-components
export const useAttributes = () => {
  const attributesContext = React.useContext(AttributesContext)

  if (!attributesContext) {
    throw new Error('useAttributes has to be used within <AttributesContext>')
  }

  return attributesContext
}
