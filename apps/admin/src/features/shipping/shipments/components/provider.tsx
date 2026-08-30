import React, { useState } from 'react'
import useDialogState from '@/hooks/use-dialog-state'
import { type Shipment } from '../data/schema'

type ShipmentsDialogType = 'add' | 'edit'

type ShipmentsContextType = {
  open: ShipmentsDialogType | null
  setOpen: (str: ShipmentsDialogType | null) => void
  currentRow: Shipment | null
  setCurrentRow: React.Dispatch<React.SetStateAction<Shipment | null>>
}

const ShipmentsContext = React.createContext<ShipmentsContextType | null>(null)

export function Provider({ children }: { children: React.ReactNode }) {
  const [open, setOpen] = useDialogState<ShipmentsDialogType>(null)
  const [currentRow, setCurrentRow] = useState<Shipment | null>(null)

  return (
    <ShipmentsContext value={{ open, setOpen, currentRow, setCurrentRow }}>
      {children}
    </ShipmentsContext>
  )
}

// eslint-disable-next-line react-refresh/only-export-components
export const useShipments = () => {
  const shipmentsContext = React.useContext(ShipmentsContext)

  if (!shipmentsContext) {
    throw new Error('useShipments has to be used within <ShipmentsContext>')
  }

  return shipmentsContext
}
