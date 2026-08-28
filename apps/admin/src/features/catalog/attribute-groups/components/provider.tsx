import React, { useState } from 'react'
import useDialogState from '@/hooks/use-dialog-state'
import { type AttributeGroup } from '../data/schema'

type AttributeGroupsDialogType = 'add' | 'edit' | 'delete'

type AttributeGroupsContextType = {
  open: AttributeGroupsDialogType | null
  setOpen: (str: AttributeGroupsDialogType | null) => void
  currentRow: AttributeGroup | null
  setCurrentRow: React.Dispatch<React.SetStateAction<AttributeGroup | null>>
}

const AttributeGroupsContext = React.createContext<AttributeGroupsContextType | null>(null)

export function Provider({ children }: { children: React.ReactNode }) {
  const [open, setOpen] = useDialogState<AttributeGroupsDialogType>(null)
  const [currentRow, setCurrentRow] = useState<AttributeGroup | null>(null)

  return (
    <AttributeGroupsContext value={{ open, setOpen, currentRow, setCurrentRow }}>
      {children}
    </AttributeGroupsContext>
  )
}

// eslint-disable-next-line react-refresh/only-export-components
export const useAttributeGroups = () => {
  const attributeGroupsContext = React.useContext(AttributeGroupsContext)

  if (!attributeGroupsContext) {
    throw new Error('useAttributeGroups has to be used within <AttributeGroupsContext>')
  }

  return attributeGroupsContext
}
