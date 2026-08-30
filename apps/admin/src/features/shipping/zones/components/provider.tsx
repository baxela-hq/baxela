import React, { useState } from 'react'
import useDialogState from '@/hooks/use-dialog-state'
import { type Zone } from '../data/schema'

type ZonesDialogType = 'add' | 'edit' | 'delete'

type ZonesContextType = {
  open: ZonesDialogType | null
  setOpen: (str: ZonesDialogType | null) => void
  currentRow: Zone | null
  setCurrentRow: React.Dispatch<React.SetStateAction<Zone | null>>
}

const ZonesContext = React.createContext<ZonesContextType | null>(null)

export function Provider({ children }: { children: React.ReactNode }) {
  const [open, setOpen] = useDialogState<ZonesDialogType>(null)
  const [currentRow, setCurrentRow] = useState<Zone | null>(null)

  return (
    <ZonesContext value={{ open, setOpen, currentRow, setCurrentRow }}>
      {children}
    </ZonesContext>
  )
}

// eslint-disable-next-line react-refresh/only-export-components
export const useZones = () => {
  const zonesContext = React.useContext(ZonesContext)

  if (!zonesContext) {
    throw new Error('useZones has to be used within <ZonesContext>')
  }

  return zonesContext
}
