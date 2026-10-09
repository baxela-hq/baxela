import React, { useState } from 'react'
import useDialogState from '@/hooks/use-dialog-state'
import { type PostComment } from '../data/schema'

type PostCommentsDialogType = 'add' | 'edit' | 'delete'

type PostCommentsContextType = {
  open: PostCommentsDialogType | null
  setOpen: (str: PostCommentsDialogType | null) => void
  currentRow: PostComment | null
  setCurrentRow: React.Dispatch<React.SetStateAction<PostComment | null>>
}

const PostCommentsContext =
  React.createContext<PostCommentsContextType | null>(null)

export function Provider({ children }: { children: React.ReactNode }) {
  const [open, setOpen] = useDialogState<PostCommentsDialogType>(null)
  const [currentRow, setCurrentRow] = useState<PostComment | null>(null)

  return (
    <PostCommentsContext value={{ open, setOpen, currentRow, setCurrentRow }}>
      {children}
    </PostCommentsContext>
  )
}

// eslint-disable-next-line react-refresh/only-export-components
export const usePostComments = () => {
  const postCommentsContext = React.useContext(PostCommentsContext)

  if (!postCommentsContext) {
    throw new Error(
      'usePostComments has to be used within <PostCommentsContext>'
    )
  }

  return postCommentsContext
}
