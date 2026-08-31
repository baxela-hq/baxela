import React, { useState } from 'react'
import useDialogState from '@/hooks/use-dialog-state'
import { type ProductComment } from '../data/schema'

type ProductCommentsDialogType = 'add' | 'edit' | 'delete'

type ProductCommentsContextType = {
  open: ProductCommentsDialogType | null
  setOpen: (str: ProductCommentsDialogType | null) => void
  currentRow: ProductComment | null
  setCurrentRow: React.Dispatch<React.SetStateAction<ProductComment | null>>
}

const ProductCommentsContext =
  React.createContext<ProductCommentsContextType | null>(null)

export function Provider({ children }: { children: React.ReactNode }) {
  const [open, setOpen] = useDialogState<ProductCommentsDialogType>(null)
  const [currentRow, setCurrentRow] = useState<ProductComment | null>(null)

  return (
    <ProductCommentsContext value={{ open, setOpen, currentRow, setCurrentRow }}>
      {children}
    </ProductCommentsContext>
  )
}

// eslint-disable-next-line react-refresh/only-export-components
export const useProductComments = () => {
  const productCommentsContext = React.useContext(ProductCommentsContext)

  if (!productCommentsContext) {
    throw new Error(
      'useProductComments has to be used within <ProductCommentsContext>'
    )
  }

  return productCommentsContext
}
