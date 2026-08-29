import { z } from 'zod'
import {
  File,
  FileArchive,
  FileAudio,
  FileImage,
  FileSpreadsheet,
  FileText,
  FileVideo,
  type LucideIcon,
} from 'lucide-react'

// ---------------------------------------------------------------------------
// Domain types (API resources)
// ---------------------------------------------------------------------------

export type MediaFolder = {
  id: number
  name: string
  parent_id: number | null
  created_at?: string | null
  updated_at?: string | null
}

export type MediaItem = {
  id: number
  /** Physical file name on disk (generated, immutable). */
  filename: string
  /** Editable display name — stored WITHOUT extension. */
  name?: string | null
  /** File extension without the dot (e.g. 'png'). */
  extension?: string | null
  url?: string | null
  path?: string | null
  mime_type?: string | null
  size?: number | null
  folder_id?: number | null
  created_at?: string | null
  updated_at?: string | null
}

// ---------------------------------------------------------------------------
// Form schemas
// ---------------------------------------------------------------------------

/**
 * Characters that never make sense in a folder/file display name: path
 * separators and filesystem-hostile chars (`\ / : * ? " < > |`), control
 * chars. Names are also what admins type in the delete-confirmation dialog,
 * so keeping them predictable matters. Applied to folder AND media names.
 */
// eslint-disable-next-line no-control-regex -- the range intentionally matches control characters to reject them
const INVALID_NAME_CHARS = /[\\/:*?"<>|\u0000-\u001f]/

function nameSchema(invalidNameMessage: string) {
  return z
    .string()
    .trim()
    .min(1)
    .max(100)
    .refine(
      (value) =>
        !INVALID_NAME_CHARS.test(value) && value !== '.' && value !== '..',
      { message: invalidNameMessage }
    )
}

/** Schema factories take the translated message (shared common namespace). */
export function buildFolderFormSchema(invalidNameMessage: string) {
  return z.object({
    name: nameSchema(invalidNameMessage),
    parent_id: z.number().nullable(),
  })
}

export type FolderForm = { name: string; parent_id: number | null }

export function buildMediaFormSchema(invalidNameMessage: string) {
  return z.object({
    name: nameSchema(invalidNameMessage),
    folder_id: z.number().nullable(),
  })
}

export type MediaForm = { name: string; folder_id: number | null }

export function buildFolderDefaultValues(
  parentId: number | null = null
): FolderForm {
  return { name: '', parent_id: parentId }
}

export function buildFolderEditValues(folder: MediaFolder): FolderForm {
  return { name: folder.name, parent_id: folder.parent_id ?? null }
}

export function buildMediaEditValues(item: MediaItem): MediaForm {
  return { name: getEditableName(item), folder_id: item.folder_id ?? null }
}

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/** Resolve the displayable URL of a media item (url preferred, path fallback). */
export function getMediaUrl(item: MediaItem): string | null {
  return item.url ?? item.path ?? null
}

/** Suffix like ".png" built from the item's extension ('' when none). */
function getExtSuffix(item: MediaItem): string {
  const ext = item.extension?.trim()
  return ext ? `.${ext}` : ''
}

/**
 * User-facing name: the editable `name` (stored WITHOUT extension) plus the
 * extension, falling back to the physical filename.
 * Tolerates legacy rows whose `name` still contains an extension.
 */
export function getDisplayName(item: MediaItem): string {
  const base = item.name?.trim()
  if (!base) return item.filename
  const suffix = getExtSuffix(item)
  // Legacy rows may already end with an extension-like suffix — don't double it
  if (!suffix || /\.[a-z0-9]{1,10}$/i.test(base)) return base
  return `${base}${suffix}`
}

/**
 * The bare, editable part of the display name (no extension) — prefill for
 * the rename dialog. Strips the extension from legacy `name` values and from
 * the physical filename when `name` is missing.
 */
export function getEditableName(item: MediaItem): string {
  const base = (item.name?.trim() || item.filename).replace(
    /\.[a-z0-9]{1,10}$/i,
    ''
  )
  return base
}

// Extension checks run against the physical filename — it always carries the
// real extension, while a renamed display name might not.
export function isImage(item: MediaItem): boolean {
  if (item.mime_type) return item.mime_type.startsWith('image/')
  return /\.(png|jpe?g|gif|webp|svg|avif|bmp|ico)$/i.test(item.filename)
}

export function isVideo(item: MediaItem): boolean {
  if (item.mime_type) return item.mime_type.startsWith('video/')
  return /\.(mp4|webm|mov|avi|mkv)$/i.test(item.filename)
}

export function getFileIcon(item: MediaItem): LucideIcon {
  const mime = item.mime_type ?? ''
  if (mime.startsWith('image/') || isImage(item)) return FileImage
  if (mime.startsWith('video/') || isVideo(item)) return FileVideo
  if (mime.startsWith('audio/')) return FileAudio
  if (
    mime.startsWith('text/') ||
    /\.(txt|md|csv|json|xml|ya?ml)$/i.test(item.filename)
  )
    return FileText
  if (/\.(xlsx?|docx?|pdf)$/i.test(item.filename)) {
    if (/\.(xlsx?|csv)$/i.test(item.filename)) return FileSpreadsheet
    return FileText
  }
  if (/\.(zip|rar|7z|tar|gz)$/i.test(item.filename)) return FileArchive
  return File
}

export function formatBytes(bytes?: number | null): string {
  if (bytes === null || bytes === undefined || Number.isNaN(bytes)) return '—'
  if (bytes === 0) return '0 B'
  const units = ['B', 'KB', 'MB', 'GB', 'TB']
  const i = Math.min(
    Math.floor(Math.log(bytes) / Math.log(1024)),
    units.length - 1
  )
  return `${parseFloat((bytes / Math.pow(1024, i)).toFixed(i === 0 ? 0 : 1))} ${units[i]}`
}

export function formatDate(value?: string | null): string {
  if (!value) return '—'
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return '—'
  return date.toLocaleDateString(undefined, {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  })
}

/**
 * Build the breadcrumb chain (root → current folder) for the given folder id.
 * Returns an empty array for the root folder.
 */
export function buildFolderPath(
  folders: MediaFolder[],
  folderId: number | null
): MediaFolder[] {
  if (folderId === null) return []

  const byId = new Map(folders.map((folder) => [folder.id, folder]))
  const chain: MediaFolder[] = []

  let current = byId.get(folderId)
  // Guard against circular parent references
  const visited = new Set<number>()
  while (current && !visited.has(current.id)) {
    visited.add(current.id)
    chain.unshift(current)
    current =
      current.parent_id !== null ? byId.get(current.parent_id) : undefined
  }

  return chain
}

/**
 * Diff payload for a media update: only changed fields are sent. The
 * backend validates the `name` extension on every save, so re-sending an
 * unchanged exotic-extension name (legacy data) would 422 even when the
 * user only moved the file. `name` is stored without the extension —
 * `extSuffix` is stripped if the user typed the full filename anyway
 * (e.g. pasted "photo.png"). Returns {} when nothing changed.
 */
export function buildMediaUpdatePayload(
  data: MediaForm,
  original: MediaForm,
  extSuffix: string
): Partial<MediaForm> {
  const payload: Partial<MediaForm> = {}
  if (data.name !== original.name) {
    let nextName = data.name.trim()
    if (
      extSuffix &&
      nextName.toLowerCase().endsWith(extSuffix.toLowerCase())
    ) {
      nextName = nextName.slice(0, -extSuffix.length).trim()
    }
    if (nextName) payload.name = nextName
  }
  if (data.folder_id !== original.folder_id) payload.folder_id = data.folder_id
  return payload
}
