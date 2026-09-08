import React, { useState } from 'react'
import useDialogState from '@/hooks/use-dialog-state'
import { type MenuLink } from '../data/schema'

type MenuLinksDialogType = 'add' | 'edit' | 'delete' | 'sort'

type MenuLinksContextType = {
  open: MenuLinksDialogType | null
  setOpen: (str: MenuLinksDialogType | null) => void
  currentRow: MenuLink | null
  setCurrentRow: React.Dispatch<React.SetStateAction<MenuLink | null>>
}

const MenuLinksContext = React.createContext<MenuLinksContextType | null>(null)

export function Provider({ children }: { children: React.ReactNode }) {
  const [open, setOpen] = useDialogState<MenuLinksDialogType>(null)
  const [currentRow, setCurrentRow] = useState<MenuLink | null>(null)

  return (
    <MenuLinksContext value={{ open, setOpen, currentRow, setCurrentRow }}>
      {children}
    </MenuLinksContext>
  )
}

// eslint-disable-next-line react-refresh/only-export-components
export const useMenuLinks = () => {
  const menuLinksContext = React.useContext(MenuLinksContext)

  if (!menuLinksContext) {
    throw new Error('useMenuLinks has to be used within <MenuLinksContext>')
  }

  return menuLinksContext
}
