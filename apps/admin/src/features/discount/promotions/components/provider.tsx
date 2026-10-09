import React, { useState } from 'react'
import useDialogState from '@/hooks/use-dialog-state'
import { type Promotion } from '../data/schema'

type PromotionsDialogType = 'delete'

type PromotionsContextType = {
  open: PromotionsDialogType | null
  setOpen: (str: PromotionsDialogType | null) => void
  currentRow: Promotion | null
  setCurrentRow: React.Dispatch<React.SetStateAction<Promotion | null>>
}

const PromotionsContext = React.createContext<PromotionsContextType | null>(null)

export function Provider({ children }: { children: React.ReactNode }) {
  const [open, setOpen] = useDialogState<PromotionsDialogType>(null)
  const [currentRow, setCurrentRow] = useState<Promotion | null>(null)

  return (
    <PromotionsContext value={{ open, setOpen, currentRow, setCurrentRow }}>
      {children}
    </PromotionsContext>
  )
}

// eslint-disable-next-line react-refresh/only-export-components
export const usePromotions = () => {
  const promotionsContext = React.useContext(PromotionsContext)

  if (!promotionsContext) {
    throw new Error('usePromotions has to be used within <PromotionsContext>')
  }

  return promotionsContext
}
