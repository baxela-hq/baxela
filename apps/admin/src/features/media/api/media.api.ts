import {
  deleteRequest,
  getRequest,
  patchRequest,
  postRequest,
} from '@/shared/lib/api-client'
import type { AllResponse, SingleResponse } from '@/shared/types/common.types'
import type {
  FolderForm,
  MediaFolder,
  MediaForm,
  MediaItem,
} from '../data/schema'

const FOLDERS_URL = 'media/admin/folders'
const MEDIA_URL = 'media/admin/media'

// ---------------------------------------------------------------------------
// Folders
// ---------------------------------------------------------------------------

export function fetchFolders(
  queryParams: Record<string, unknown> = { per_page: 1000 }
) {
  return getRequest<AllResponse<MediaFolder>>(
    FOLDERS_URL,
    queryParams as Record<string, never>
  )
}

export async function createFolder(request: FolderForm): Promise<MediaFolder> {
  const { data } = await postRequest<SingleResponse<MediaFolder>, FolderForm>(
    FOLDERS_URL,
    request
  )
  return data as MediaFolder
}

export function updateFolder(
  id: number,
  request: FolderForm
): Promise<MediaFolder> {
  return patchRequest<SingleResponse<MediaFolder>, FolderForm>(
    `${FOLDERS_URL}/${id}`,
    request
  ).then((res) => res.data as MediaFolder)
}

export function deleteFolder(id: number) {
  return deleteRequest(`${FOLDERS_URL}/${id}`)
}

// ---------------------------------------------------------------------------
// Media
// ---------------------------------------------------------------------------

/**
 * Fetch media of a folder. `folderId === null` lists root-level files
 * (`filter[folder_id]=null`).
 */
export function fetchMedia(
  folderId: number | null,
  queryParams: Record<string, string> = {}
) {
  return getRequest<AllResponse<MediaItem>>(MEDIA_URL, {
    'filter[folder_id]': folderId === null ? 'null' : String(folderId),
    ...queryParams,
  } as unknown as Record<string, never>)
}

export async function uploadMedia(
  file: File,
  folderId: number | null
): Promise<MediaItem> {
  const formData = new FormData()
  formData.append('file', file)
  if (folderId !== null) {
    formData.append('folder_id', String(folderId))
  }
  const { data } = await postRequest<SingleResponse<MediaItem>, FormData>(
    MEDIA_URL,
    formData,
    {
      headers: { 'Content-Type': 'multipart/form-data' },
    }
  )
  return data as MediaItem
}

/**
 * Partial update: only `name` (rename) and/or `folder_id` (move) —
 * the backend accepts PATCH payloads with just the changed fields.
 */
export function updateMedia(
  id: number,
  request: Partial<MediaForm>
): Promise<MediaItem> {
  return patchRequest<SingleResponse<MediaItem>, Partial<MediaForm>>(
    `${MEDIA_URL}/${id}`,
    request
  ).then((res) => res.data as MediaItem)
}

export function deleteMedia(id: number) {
  return deleteRequest(`${MEDIA_URL}/${id}`)
}
