import React, { useState } from 'react'
import useDialogState from '@/hooks/use-dialog-state'
import { type Method } from '../data/schema'

type MethodsDialogType = 'add' | 'edit' | 'delete'

type MethodsContextType = {
  open: MethodsDialogType | null
  setOpen: (str: MethodsDialogType | null) => void
  currentRow: Method | null
  setCurrentRow: React.Dispatch<React.SetStateAction<Method | null>>
}

const MethodsContext = React.createContext<MethodsContextType | null>(null)

export function Provider({ children }: { children: React.ReactNode }) {
  const [open, setOpen] = useDialogState<MethodsDialogType>(null)
  const [currentRow, setCurrentRow] = useState<Method | null>(null)

  return (
    <MethodsContext value={{ open, setOpen, currentRow, setCurrentRow }}>
      {children}
    </MethodsContext>
  )
}

// eslint-disable-next-line react-refresh/only-export-components
export const useMethods = () => {
  const methodsContext = React.useContext(MethodsContext)

  if (!methodsContext) {
    throw new Error('useMethods has to be used within <MethodsContext>')
  }

  return methodsContext
}
