import React, { useState } from 'react'
import useDialogState from '@/hooks/use-dialog-state'
import { type TaxRate } from '../data/schema'

type TaxRatesDialogType = 'add' | 'edit' | 'delete'

type TaxRatesContextType = {
  open: TaxRatesDialogType | null
  setOpen: (str: TaxRatesDialogType | null) => void
  currentRow: TaxRate | null
  setCurrentRow: React.Dispatch<React.SetStateAction<TaxRate | null>>
}

const TaxRatesContext = React.createContext<TaxRatesContextType | null>(null)

export function Provider({ children }: { children: React.ReactNode }) {
  const [open, setOpen] = useDialogState<TaxRatesDialogType>(null)
  const [currentRow, setCurrentRow] = useState<TaxRate | null>(null)

  return (
    <TaxRatesContext value={{ open, setOpen, currentRow, setCurrentRow }}>
      {children}
    </TaxRatesContext>
  )
}

// eslint-disable-next-line react-refresh/only-export-components
export const useTaxRates = () => {
  const zonesContext = React.useContext(TaxRatesContext)

  if (!zonesContext) {
    throw new Error('useTaxRates has to be used within <TaxRatesContext>')
  }

  return zonesContext
}
