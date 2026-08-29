import { useState } from 'react'
import { useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import { ApiError } from '@/shared/lib/api-error'
import { parseAndToastError } from '@/shared/lib/utils'
import { uploadMedia } from '../api/media.api'
import { FeatureRoutes, Locales } from '../data/routes'

/**
 * Sequential multi-file upload into a folder: per-file error reporting
 * (413 gets a specific message), a summary toast on partial success and
 * cache invalidation afterwards. `isUploading` gates the UI while the
 * batch runs.
 */
export function useMediaUpload(folderId: number | null) {
  const queryClient = useQueryClient()
  const { tMessage: mediaTMessage } = useAppTranslation(Locales.MEDIA)
  // Generic messages (upload success/failure) come from common
  const { tMessage, tAction } = useAppTranslation(Locales.SHARED_COMMON)
  const [isUploading, setIsUploading] = useState(false)

  const handleFiles = async (fileList: FileList | File[] | null) => {
    const selected = Array.from(fileList ?? [])
    if (selected.length === 0) return

    setIsUploading(true)
    let succeeded = 0
    let failed = 0
    for (const file of selected) {
      try {
        await uploadMedia(file, folderId)
        succeeded += 1
      } catch (err) {
        failed += 1
        // nginx/php reject oversized bodies with 413 before Laravel can
        // answer with JSON — give a specific message instead of "Unknown"
        if (err instanceof ApiError && err.status === 413) {
          toast.error(
            mediaTMessage('error.file_too_large', { name: file.name })
          )
        } else if (err instanceof ApiError) {
          parseAndToastError(err)
        }
      }
    }
    if (succeeded > 0) {
      toast.success(tMessage('success.upload_general'))
      await queryClient.invalidateQueries({
        queryKey: [FeatureRoutes.CACHE_KEY],
      })
    }
    if (failed > 0) {
      toast.error(tMessage('error.default', { action: tAction('upload') }))
    }
    setIsUploading(false)
  }

  return { handleFiles, isUploading }
}
