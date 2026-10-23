import React, { useState } from 'react'
import useDialogState from '@/hooks/use-dialog-state'
import { type CurrencyRow } from '../data/schema'

type CurrenciesDialogType = 'edit'

type CurrenciesContextType = {
  open: CurrenciesDialogType | null
  setOpen: (str: CurrenciesDialogType | null) => void
  currentRow: CurrencyRow | null
  setCurrentRow: React.Dispatch<React.SetStateAction<CurrencyRow | null>>
}

const CurrenciesContext = React.createContext<CurrenciesContextType | null>(
  null
)

export function Provider({ children }: { children: React.ReactNode }) {
  const [open, setOpen] = useDialogState<CurrenciesDialogType>(null)
  const [currentRow, setCurrentRow] = useState<CurrencyRow | null>(null)

  return (
    <CurrenciesContext value={{ open, setOpen, currentRow, setCurrentRow }}>
      {children}
    </CurrenciesContext>
  )
}

// eslint-disable-next-line react-refresh/only-export-components
export const useCurrenciesDialog = () => {
  const currenciesContext = React.useContext(CurrenciesContext)

  if (!currenciesContext) {
    throw new Error(
      'useCurrenciesDialog has to be used within <CurrenciesContext>'
    )
  }

  return currenciesContext
}
