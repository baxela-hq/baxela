import React, { useState } from 'react'
import useDialogState from '@/hooks/use-dialog-state'
import { type Option } from '../data/schema'

type OptionsDialogType = 'add' | 'edit' | 'delete'

type OptionsContextType = {
  open: OptionsDialogType | null
  setOpen: (str: OptionsDialogType | null) => void
  currentRow: Option | null
  setCurrentRow: React.Dispatch<React.SetStateAction<Option | null>>
}

const OptionsContext = React.createContext<OptionsContextType | null>(null)

export function Provider({ children }: { children: React.ReactNode }) {
  const [open, setOpen] = useDialogState<OptionsDialogType>(null)
  const [currentRow, setCurrentRow] = useState<Option | null>(null)

  return (
    <OptionsContext value={{ open, setOpen, currentRow, setCurrentRow }}>
      {children}
    </OptionsContext>
  )
}

// eslint-disable-next-line react-refresh/only-export-components
export const useOptions = () => {
  const optionsContext = React.useContext(OptionsContext)

  if (!optionsContext) {
    throw new Error('useOptions has to be used within <OptionsContext>')
  }

  return optionsContext
}
