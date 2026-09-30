import React, { useState } from 'react'
import useDialogState from '@/hooks/use-dialog-state'
import { type InventoryStock } from '../data/schema'

type InventoryStocksDialogType = 'add' | 'edit' | 'delete'

type InventoryStocksContextType = {
  open: InventoryStocksDialogType | null
  setOpen: (str: InventoryStocksDialogType | null) => void
  currentRow: InventoryStock | null
  setCurrentRow: React.Dispatch<React.SetStateAction<InventoryStock | null>>
}

const InventoryStocksContext = React.createContext<InventoryStocksContextType | null>(null)

export function Provider({ children }: { children: React.ReactNode }) {
  const [open, setOpen] = useDialogState<InventoryStocksDialogType>(null)
  const [currentRow, setCurrentRow] = useState<InventoryStock | null>(null)

  return (
    <InventoryStocksContext value={{ open, setOpen, currentRow, setCurrentRow }}>
      {children}
    </InventoryStocksContext>
  )
}

// eslint-disable-next-line react-refresh/only-export-components
export const useInventoryStocks = () => {
  const inventoryStocksContext = React.useContext(InventoryStocksContext)

  if (!inventoryStocksContext) {
    throw new Error('useInventoryStocks has to be used within <InventoryStocksContext>')
  }

  return inventoryStocksContext
}
