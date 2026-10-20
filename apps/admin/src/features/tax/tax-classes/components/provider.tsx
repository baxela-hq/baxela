import React, { useState } from 'react'
import useDialogState from '@/hooks/use-dialog-state'
import { type TaxClass } from '../data/schema'

type TaxClassesDialogType = 'add' | 'edit' | 'delete'

type TaxClassesContextType = {
  open: TaxClassesDialogType | null
  setOpen: (str: TaxClassesDialogType | null) => void
  currentRow: TaxClass | null
  setCurrentRow: React.Dispatch<React.SetStateAction<TaxClass | null>>
}

const TaxClassesContext = React.createContext<TaxClassesContextType | null>(null)

export function Provider({ children }: { children: React.ReactNode }) {
  const [open, setOpen] = useDialogState<TaxClassesDialogType>(null)
  const [currentRow, setCurrentRow] = useState<TaxClass | null>(null)

  return (
    <TaxClassesContext value={{ open, setOpen, currentRow, setCurrentRow }}>
      {children}
    </TaxClassesContext>
  )
}

// eslint-disable-next-line react-refresh/only-export-components
export const useTaxClasses = () => {
  const zonesContext = React.useContext(TaxClassesContext)

  if (!zonesContext) {
    throw new Error('useTaxClasses has to be used within <TaxClassesContext>')
  }

  return zonesContext
}
