import React, { useState } from 'react'
import useDialogState from '@/hooks/use-dialog-state'
import { type MediaFolder, type MediaItem } from '../data/schema'

type MediaDialogType =
  | 'folder-add'
  | 'folder-edit'
  | 'media-edit'
  | 'delete'
  | 'preview'

/**
 * The subject a dialog currently acts on. Folders and media are separate
 * entities in the backend, so the target is discriminated by `type`.
 */
type MediaDialogTarget =
  | { type: 'folder'; folder: MediaFolder }
  | { type: 'media'; item: MediaItem }
  | null

type MediaContextType = {
  open: MediaDialogType | null
  setOpen: (str: MediaDialogType | null) => void
  currentTarget: MediaDialogTarget
  setCurrentTarget: React.Dispatch<React.SetStateAction<MediaDialogTarget>>
}

const MediaContext = React.createContext<MediaContextType | null>(null)

export function Provider({ children }: { children: React.ReactNode }) {
  const [open, setOpen] = useDialogState<MediaDialogType>(null)
  const [currentTarget, setCurrentTarget] = useState<MediaDialogTarget>(null)

  return (
    <MediaContext value={{ open, setOpen, currentTarget, setCurrentTarget }}>
      {children}
    </MediaContext>
  )
}

// eslint-disable-next-line react-refresh/only-export-components
export const useMediaDialogs = () => {
  const mediaContext = React.useContext(MediaContext)

  if (!mediaContext) {
    throw new Error('useMediaDialogs has to be used within <MediaContext>')
  }

  return mediaContext
}
