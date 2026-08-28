import React, { useState } from 'react'
import useDialogState from '@/hooks/use-dialog-state'
import { type AttributeTemplate } from '../data/schema'

type AttributeTemplatesDialogType = 'add' | 'edit' | 'delete'

type AttributeTemplatesContextType = {
  open: AttributeTemplatesDialogType | null
  setOpen: (str: AttributeTemplatesDialogType | null) => void
  currentRow: AttributeTemplate | null
  setCurrentRow: React.Dispatch<React.SetStateAction<AttributeTemplate | null>>
}

const AttributeTemplatesContext = React.createContext<AttributeTemplatesContextType | null>(null)

export function Provider({ children }: { children: React.ReactNode }) {
  const [open, setOpen] = useDialogState<AttributeTemplatesDialogType>(null)
  const [currentRow, setCurrentRow] = useState<AttributeTemplate | null>(null)

  return (
    <AttributeTemplatesContext value={{ open, setOpen, currentRow, setCurrentRow }}>
      {children}
    </AttributeTemplatesContext>
  )
}

// eslint-disable-next-line react-refresh/only-export-components
export const useAttributeTemplates = () => {
  const attributeTemplatesContext = React.useContext(AttributeTemplatesContext)

  if (!attributeTemplatesContext) {
    throw new Error('useAttributeTemplates has to be used within <AttributeTemplatesContext>')
  }

  return attributeTemplatesContext
}
