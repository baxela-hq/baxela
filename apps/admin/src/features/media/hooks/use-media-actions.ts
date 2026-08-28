import { useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import { useMediaDialogs } from '../components/provider'
import { FeatureRoutes, Locales } from '../data/routes'
import { getMediaUrl, type MediaFolder, type MediaItem } from '../data/schema'

/**
 * Copy text to the clipboard with a legacy fallback: the async Clipboard API
 * only exists in secure contexts (https / localhost), so plain-http hosts
 * (e.g. LAN IPs or local vhosts) need the execCommand path.
 *
 * execCommand('copy') copies the *current selection* and can report success
 * while copying nothing when the selection silently failed to land on the
 * helper textarea — so the selection is verified before trusting its result.
 */
async function copyTextToClipboard(text: string): Promise<boolean> {
  if (navigator.clipboard && window.isSecureContext) {
    try {
      await navigator.clipboard.writeText(text)
      return true
    } catch {
      // Permission denied or document not focused — fall through to legacy path
    }
  }

  const textarea = document.createElement('textarea')
  textarea.value = text
  textarea.readOnly = true
  // Keep it invisible and out of the layout to avoid flicker/scroll jumps
  textarea.style.position = 'fixed'
  textarea.style.top = '0'
  textarea.style.opacity = '0'
  document.body.appendChild(textarea)

  // Remember the previous selection to restore it afterwards
  const selection = document.getSelection()
  const previousRange =
    selection && selection.rangeCount > 0
      ? selection.getRangeAt(0).cloneRange()
      : null

  textarea.focus()
  textarea.select()
  textarea.setSelectionRange(0, text.length)

  let copied = false
  try {
    // Only trust execCommand when the text is actually selected
    if (selection?.toString() === text) {
      copied = document.execCommand('copy')
    }
  } finally {
    document.body.removeChild(textarea)
    if (selection && previousRange) {
      selection.removeAllRanges()
      selection.addRange(previousRange)
    }
  }
  return copied
}

/**
 * Shared UI actions (open dialogs, copy url, download) used by the
 * folder/media cards, the list view and the preview sheet.
 */
export function useMediaActions() {
  const { setOpen, setCurrentTarget } = useMediaDialogs()
  const { tMessage } = useAppTranslation(Locales.MEDIA)
  // Generic messages come from common
  const { tMessage: commonTMessage } = useAppTranslation(Locales.SHARED_COMMON)

  const openFolderEdit = (folder: MediaFolder) => {
    setCurrentTarget({ type: 'folder', folder })
    setOpen('folder-edit')
  }

  const openFolderDelete = (folder: MediaFolder) => {
    setCurrentTarget({ type: 'folder', folder })
    setOpen('delete')
  }

  const openMediaEdit = (item: MediaItem) => {
    setCurrentTarget({ type: 'media', item })
    setOpen('media-edit')
  }

  const openMediaDelete = (item: MediaItem) => {
    setCurrentTarget({ type: 'media', item })
    setOpen('delete')
  }

  const openMediaPreview = (item: MediaItem) => {
    setCurrentTarget({ type: 'media', item })
    setOpen('preview')
  }

  const copyUrl = async (item: MediaItem) => {
    const url = getMediaUrl(item)
    if (!url) {
      toast.error(tMessage('error.no_url'))
      return
    }
    const copied = await copyTextToClipboard(url)
    if (copied) {
      toast.success(tMessage('success.url_copied'))
    } else if (!window.isSecureContext) {
      // Root cause is the plain-http origin (LAN IP / vhost), not the code
      toast.error(tMessage('error.clipboard_insecure'))
    } else {
      toast.error(commonTMessage('error.general'))
    }
  }

  /**
   * Open the file in a new tab. The HTML `download` attribute is ignored for
   * cross-origin files (media lives on the backend origin), so a forced
   * download isn't reliably possible from the admin — a new tab lets the
   * browser render/save the file instead.
   */
  const download = (item: MediaItem) => {
    const url = getMediaUrl(item)
    if (!url) {
      toast.error(tMessage('error.no_url'))
      return
    }
    window.open(url, '_blank', 'noopener,noreferrer')
  }

  return {
    openFolderEdit,
    openFolderDelete,
    openMediaEdit,
    openMediaDelete,
    openMediaPreview,
    copyUrl,
    download,
  }
}

/** Invalidate every media query (folders + files of all folders). */
export function useInvalidateMedia() {
  const queryClient = useQueryClient()
  return () =>
    queryClient.invalidateQueries({ queryKey: [FeatureRoutes.CACHE_KEY] })
}
