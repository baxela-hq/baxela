import { useMemo } from 'react'
import { useQuery } from '@tanstack/react-query'
import { buildHierarchy } from '@/shared/lib/tree'
import type { AllResponse } from '@/shared/types/common.types'
import { fetchFolders, fetchMedia } from '../api/media.api'
import { FeatureRoutes } from '../data/routes'
import {
  buildFolderPath,
  type MediaFolder,
  type MediaItem,
} from '../data/schema'

/** All folders (fetched once, filtered client-side for navigation). */
export function useFolders() {
  return useQuery<AllResponse<MediaFolder>>({
    queryKey: [FeatureRoutes.CACHE_KEY, 'folders'],
    queryFn: () => fetchFolders(),
  })
}

/** Media files of a folder (server-filtered via filter[folder_id]). */
export function useMedia(folderId: number | null) {
  return useQuery<AllResponse<MediaItem>>({
    queryKey: [FeatureRoutes.CACHE_KEY, 'files', folderId ?? 'null'],
    queryFn: () => fetchMedia(folderId),
  })
}

/** Direct sub-folders of a folder. */
export function useSubfolders(folderId: number | null) {
  const { data, isLoading } = useFolders()
  const subfolders = useMemo(() => {
    const folders = data?.data ?? []
    return folders.filter((folder) =>
      folderId === null
        ? folder.parent_id === null
        : folder.parent_id === folderId
    )
  }, [data, folderId])
  return { subfolders, isLoading }
}

/** Breadcrumb chain (root → current) for a folder. */
export function useFolderPath(folderId: number | null): MediaFolder[] {
  const { data } = useFolders()
  return useMemo(
    () => buildFolderPath(data?.data ?? [], folderId),
    [data, folderId]
  )
}

/**
 * Flattened folder tree (parents first, depth for indentation) for Select
 * inputs. When `excludeId` is given, that folder and its descendants are
 * excluded (prevents making a folder its own parent).
 */
export function useFolderTree(
  excludeId?: number | null
): (MediaFolder & { depth: number })[] {
  const { data } = useFolders()
  return useMemo(() => {
    const tree = buildHierarchy<MediaFolder>(
      data?.data ?? [],
      (folder) => folder.name
    )

    if (excludeId === null || excludeId === undefined) return tree

    const filtered: (MediaFolder & { depth: number })[] = []
    let excluding = false
    let excludeDepth = 0
    for (const item of tree) {
      if (item.id === excludeId) {
        excluding = true
        excludeDepth = item.depth
        continue
      }
      if (excluding) {
        if (item.depth > excludeDepth) continue
        excluding = false
      }
      filtered.push(item)
    }
    return filtered
  }, [data, excludeId])
}
