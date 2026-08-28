import React, { useState } from 'react'
import useDialogState from '@/hooks/use-dialog-state'
import { type AttributeValue } from '../data/schema'

type AttributeValuesDialogType = 'add' | 'edit' | 'delete'

type AttributeValuesContextType = {
  open: AttributeValuesDialogType | null
  setOpen: (str: AttributeValuesDialogType | null) => void
  currentRow: AttributeValue | null
  setCurrentRow: React.Dispatch<React.SetStateAction<AttributeValue | null>>
}

const AttributeValuesContext = React.createContext<AttributeValuesContextType | null>(null)

export function Provider({ children }: { children: React.ReactNode }) {
  const [open, setOpen] = useDialogState<AttributeValuesDialogType>(null)
  const [currentRow, setCurrentRow] = useState<AttributeValue | null>(null)

  return (
    <AttributeValuesContext value={{ open, setOpen, currentRow, setCurrentRow }}>
      {children}
    </AttributeValuesContext>
  )
}

// eslint-disable-next-line react-refresh/only-export-components
export const useAttributeValues = () => {
  const attributeValuesContext = React.useContext(AttributeValuesContext)

  if (!attributeValuesContext) {
    throw new Error('useAttributeValues has to be used within <AttributeValuesContext>')
  }

  return attributeValuesContext
}
