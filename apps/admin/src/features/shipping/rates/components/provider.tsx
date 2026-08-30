import React, { useState } from 'react'
import useDialogState from '@/hooks/use-dialog-state'
import { type Rate } from '../data/schema'

type RatesDialogType = 'add' | 'edit' | 'delete'

type RatesContextType = {
  open: RatesDialogType | null
  setOpen: (str: RatesDialogType | null) => void
  currentRow: Rate | null
  setCurrentRow: React.Dispatch<React.SetStateAction<Rate | null>>
}

const RatesContext = React.createContext<RatesContextType | null>(null)

export function Provider({ children }: { children: React.ReactNode }) {
  const [open, setOpen] = useDialogState<RatesDialogType>(null)
  const [currentRow, setCurrentRow] = useState<Rate | null>(null)

  return (
    <RatesContext value={{ open, setOpen, currentRow, setCurrentRow }}>
      {children}
    </RatesContext>
  )
}

// eslint-disable-next-line react-refresh/only-export-components
export const useRates = () => {
  const ratesContext = React.useContext(RatesContext)

  if (!ratesContext) {
    throw new Error('useRates has to be used within <RatesContext>')
  }

  return ratesContext
}
