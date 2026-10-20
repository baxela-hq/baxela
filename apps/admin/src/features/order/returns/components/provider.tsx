import React, { useState } from 'react'
import useDialogState from '@/hooks/use-dialog-state'
import { type OrderReturn, type ReturnStatus } from '../data/schema'

type ReturnsDialogType = 'delete' | ReturnStatus

type ReturnsContextType = {
  open: ReturnsDialogType | null
  setOpen: (str: ReturnsDialogType | null) => void
  currentRow: OrderReturn | null
  setCurrentRow: React.Dispatch<React.SetStateAction<OrderReturn | null>>
}

const ReturnsContext = React.createContext<ReturnsContextType | null>(null)

export function Provider({ children }: { children: React.ReactNode }) {
  // a status name doubles as the transition-dialog key
  const [open, setOpen] = useDialogState<ReturnsDialogType>(null)
  const [currentRow, setCurrentRow] = useState<OrderReturn | null>(null)

  return (
    <ReturnsContext value={{ open, setOpen, currentRow, setCurrentRow }}>
      {children}
    </ReturnsContext>
  )
}

// eslint-disable-next-line react-refresh/only-export-components
export const useReturns = () => {
  const zonesContext = React.useContext(ReturnsContext)

  if (!zonesContext) {
    throw new Error('useReturns has to be used within <ReturnsContext>')
  }

  return zonesContext
}
