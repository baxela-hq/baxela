import React, { useState } from 'react'
import useDialogState from '@/hooks/use-dialog-state'
import { type Ticket } from '../data/schema'

type TicketsDialogType = 'delete'

type TicketsContextType = {
  open: TicketsDialogType | null
  setOpen: (str: TicketsDialogType | null) => void
  currentRow: Ticket | null
  setCurrentRow: React.Dispatch<React.SetStateAction<Ticket | null>>
}

const TicketsContext = React.createContext<TicketsContextType | null>(null)

export function Provider({ children }: { children: React.ReactNode }) {
  const [open, setOpen] = useDialogState<TicketsDialogType>(null)
  const [currentRow, setCurrentRow] = useState<Ticket | null>(null)

  return (
    <TicketsContext value={{ open, setOpen, currentRow, setCurrentRow }}>
      {children}
    </TicketsContext>
  )
}

// eslint-disable-next-line react-refresh/only-export-components
export const useTickets = () => {
  const ticketsContext = React.useContext(TicketsContext)

  if (!ticketsContext) {
    throw new Error('useTickets has to be used within <TicketsContext>')
  }

  return ticketsContext
}
