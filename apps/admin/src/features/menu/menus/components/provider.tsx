import React, { useState } from 'react'
import useDialogState from '@/hooks/use-dialog-state'
import { type Menu } from '../data/schema'

type MenusDialogType = 'add' | 'edit' | 'delete' | 'sort'

type MenusContextType = {
  open: MenusDialogType | null
  setOpen: (str: MenusDialogType | null) => void
  currentRow: Menu | null
  setCurrentRow: React.Dispatch<React.SetStateAction<Menu | null>>
}

const MenusContext = React.createContext<MenusContextType | null>(null)

export function Provider({ children }: { children: React.ReactNode }) {
  const [open, setOpen] = useDialogState<MenusDialogType>(null)
  const [currentRow, setCurrentRow] = useState<Menu | null>(null)

  return (
    <MenusContext value={{ open, setOpen, currentRow, setCurrentRow }}>
      {children}
    </MenusContext>
  )
}

// eslint-disable-next-line react-refresh/only-export-components
export const useMenus = () => {
  const menusContext = React.useContext(MenusContext)

  if (!menusContext) {
    throw new Error('useMenus has to be used within <MenusContext>')
  }

  return menusContext
}
