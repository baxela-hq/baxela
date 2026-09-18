import React, { useState } from 'react'
import useDialogState from '@/hooks/use-dialog-state'
import { type Payment } from '../data/schema'

type PaymentsDialogType = 'confirm' | 'fail'

type PaymentsContextType = {
  open: PaymentsDialogType | null
  setOpen: (str: PaymentsDialogType | null) => void
  currentRow: Payment | null
  setCurrentRow: React.Dispatch<React.SetStateAction<Payment | null>>
}

const PaymentsContext = React.createContext<PaymentsContextType | null>(null)

export function Provider({ children }: { children: React.ReactNode }) {
  const [open, setOpen] = useDialogState<PaymentsDialogType>(null)
  const [currentRow, setCurrentRow] = useState<Payment | null>(null)

  return (
    <PaymentsContext value={{ open, setOpen, currentRow, setCurrentRow }}>
      {children}
    </PaymentsContext>
  )
}

// eslint-disable-next-line react-refresh/only-export-components
export const usePayments = () => {
  const paymentsContext = React.useContext(PaymentsContext)

  if (!paymentsContext) {
    throw new Error('usePayments has to be used within <PaymentsContext>')
  }

  return paymentsContext
}
