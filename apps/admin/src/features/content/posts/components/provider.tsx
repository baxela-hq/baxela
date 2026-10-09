import React, { useState } from 'react'
import useDialogState from '@/hooks/use-dialog-state'
import { type Post } from '../data/schema'

type PostsDialogType = 'add' | 'edit' | 'delete'

type PostsContextType = {
  open: PostsDialogType | null
  setOpen: (str: PostsDialogType | null) => void
  currentRow: Post | null
  setCurrentRow: React.Dispatch<React.SetStateAction<Post | null>>
}

const PostsContext = React.createContext<PostsContextType | null>(null)

export function Provider({ children }: { children: React.ReactNode }) {
  const [open, setOpen] = useDialogState<PostsDialogType>(null)
  const [currentRow, setCurrentRow] = useState<Post | null>(null)

  return (
    <PostsContext value={{ open, setOpen, currentRow, setCurrentRow }}>
      {children}
    </PostsContext>
  )
}

// eslint-disable-next-line react-refresh/only-export-components
export const usePosts = () => {
  const postsContext = React.useContext(PostsContext)

  if (!postsContext) {
    throw new Error('usePosts has to be used within <PostsContext>')
  }

  return postsContext
}
