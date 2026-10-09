import React, { useState } from 'react'
import useDialogState from '@/hooks/use-dialog-state'
import { type PostCategory } from '../data/schema'

type PostCategoriesDialogType = 'add' | 'edit' | 'delete'

type PostCategoriesContextType = {
  open: PostCategoriesDialogType | null
  setOpen: (str: PostCategoriesDialogType | null) => void
  currentRow: PostCategory | null
  setCurrentRow: React.Dispatch<React.SetStateAction<PostCategory | null>>
}

const PostCategoriesContext = React.createContext<PostCategoriesContextType | null>(null)

export function Provider({ children }: { children: React.ReactNode }) {
  const [open, setOpen] = useDialogState<PostCategoriesDialogType>(null)
  const [currentRow, setCurrentRow] = useState<PostCategory | null>(null)

  return (
    <PostCategoriesContext value={{ open, setOpen, currentRow, setCurrentRow }}>
      {children}
    </PostCategoriesContext>
  )
}

// eslint-disable-next-line react-refresh/only-export-components
export const usePostCategories = () => {
  const postCategoriesContext = React.useContext(PostCategoriesContext)

  if (!postCategoriesContext) {
    throw new Error('usePostCategories has to be used within <PostCategoriesContext>')
  }

  return postCategoriesContext
}
