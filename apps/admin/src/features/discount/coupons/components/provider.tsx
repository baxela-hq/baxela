import React, { useState } from 'react'
import useDialogState from '@/hooks/use-dialog-state'
import { type Coupon } from '../data/schema'

type CouponsDialogType = 'add' | 'edit' | 'delete'

type CouponsContextType = {
  open: CouponsDialogType | null
  setOpen: (str: CouponsDialogType | null) => void
  currentRow: Coupon | null
  setCurrentRow: React.Dispatch<React.SetStateAction<Coupon | null>>
}

const CouponsContext = React.createContext<CouponsContextType | null>(null)

export function Provider({ children }: { children: React.ReactNode }) {
  const [open, setOpen] = useDialogState<CouponsDialogType>(null)
  const [currentRow, setCurrentRow] = useState<Coupon | null>(null)

  return (
    <CouponsContext value={{ open, setOpen, currentRow, setCurrentRow }}>
      {children}
    </CouponsContext>
  )
}

// eslint-disable-next-line react-refresh/only-export-components
export const useCoupons = () => {
  const couponsContext = React.useContext(CouponsContext)

  if (!couponsContext) {
    throw new Error('useCoupons has to be used within <CouponsContext>')
  }

  return couponsContext
}
