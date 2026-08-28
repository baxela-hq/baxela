import React, { useState } from 'react'
import useDialogState from '@/hooks/use-dialog-state'
import { type OptionValue } from '../data/schema'

type OptionValuesDialogType = 'add' | 'edit' | 'delete'

type OptionValuesContextType = {
  open: OptionValuesDialogType | null
  setOpen: (str: OptionValuesDialogType | null) => void
  currentRow: OptionValue | null
  setCurrentRow: React.Dispatch<React.SetStateAction<OptionValue | null>>
}

const OptionValuesContext = React.createContext<OptionValuesContextType | null>(null)

export function Provider({ children }: { children: React.ReactNode }) {
  const [open, setOpen] = useDialogState<OptionValuesDialogType>(null)
  const [currentRow, setCurrentRow] = useState<OptionValue | null>(null)

  return (
    <OptionValuesContext value={{ open, setOpen, currentRow, setCurrentRow }}>
      {children}
    </OptionValuesContext>
  )
}

// eslint-disable-next-line react-refresh/only-export-components
export const useOptionValues = () => {
  const optionValuesContext = React.useContext(OptionValuesContext)

  if (!optionValuesContext) {
    throw new Error('useOptionValues has to be used within <OptionValuesContext>')
  }

  return optionValuesContext
}
