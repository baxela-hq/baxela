import { Fragment, useMemo, useRef, useState } from 'react'
import { useQueryClient } from '@tanstack/react-query'
import { ApiError } from '@/shared/lib/api-error'
import { parseAndToastError } from '@/shared/lib/utils'
import {
  FolderPlus,
  LayoutGrid,
  List,
  Loader2,
  Search,
  SearchX,
  Upload,
  UploadCloud,
} from 'lucide-react'
import { toast } from 'sonner'
import { cn } from '@/lib/utils'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import {
  Breadcrumb,
  BreadcrumbItem,
  BreadcrumbLink,
  BreadcrumbList,
  BreadcrumbPage,
  BreadcrumbSeparator,
} from '@/components/ui/breadcrumb'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Skeleton } from '@/components/ui/skeleton'
import { uploadMedia } from '../api/media.api'
import { FeatureRoutes, Locales } from '../data/routes'
import {
  getDisplayName,
  type MediaFolder,
  type MediaItem,
} from '../data/schema'
import {
  useFolderPath,
  useMedia,
  useSubfolders,
} from '../hooks/use-media-library'
import { FolderCard } from './folder-card'
import { MediaCard } from './media-card'
import { MediaListView } from './media-list-view'
import { useMediaDialogs } from './provider'

type MediaBrowserMode = 'manage' | 'picker'

type MediaBrowserProps = {
  /** Currently browsed folder (`null` = root). */
  folderId: number | null
  onFolderChange: (folderId: number | null) => void
  /** 'picker' enables selection and hides edit/delete actions. */
  mode?: MediaBrowserMode
  accept?: string
  selectedIds?: Set<number>
  onSelect?: (item: MediaItem) => void
}

export function MediaBrowser({
  folderId,
  onFolderChange,
  mode = 'manage',
  accept,
  selectedIds,
  onSelect,
}: MediaBrowserProps) {
  const {
    tLabel,
    tPlaceHolder,
    tAction,
    tMessage: mediaTMessage,
  } = useAppTranslation(Locales.MEDIA)
  // Generic messages (upload success/failure) come from common
  const { tMessage } = useAppTranslation(Locales.SHARED_COMMON)
  const { setOpen } = useMediaDialogs()
  const queryClient = useQueryClient()

  const [search, setSearch] = useState('')
  const [view, setView] = useState<'grid' | 'list'>('grid')
  const [isDragging, setIsDragging] = useState(false)
  const [isUploading, setIsUploading] = useState(false)
  const inputRef = useRef<HTMLInputElement>(null)

  const { subfolders, isLoading: foldersLoading } = useSubfolders(folderId)
  const folderPath = useFolderPath(folderId)
  const { data: mediaData, isLoading: mediaLoading } = useMedia(folderId)

  const files = useMemo(() => mediaData?.data ?? [], [mediaData])

  const { filteredFolders, filteredFiles } = useMemo(() => {
    const query = search.trim().toLowerCase()
    if (!query) return { filteredFolders: subfolders, filteredFiles: files }
    return {
      filteredFolders: subfolders.filter((folder) =>
        folder.name.toLowerCase().includes(query)
      ),
      // Match the display name or the physical filename (e.g. searching the hash)
      filteredFiles: files.filter(
        (file) =>
          getDisplayName(file).toLowerCase().includes(query) ||
          file.filename.toLowerCase().includes(query)
      ),
    }
  }, [subfolders, files, search])

  const isLoading = foldersLoading || mediaLoading
  const isEmpty = filteredFolders.length === 0 && filteredFiles.length === 0

  const openFolder = (folder: MediaFolder) => {
    setSearch('')
    onFolderChange(folder.id)
  }

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

  const openNewFolderDialog = () => {
    setOpen('folder-add')
  }

  return (
    <div className='flex flex-col gap-4'>
      {/* Toolbar: breadcrumb + search + view + actions */}
      <div className='flex flex-wrap items-center justify-between gap-2'>
        <Breadcrumb>
          <BreadcrumbList>
            <BreadcrumbItem>
              <BreadcrumbLink asChild>
                <button type='button' onClick={() => onFolderChange(null)}>
                  {tLabel('root')}
                </button>
              </BreadcrumbLink>
            </BreadcrumbItem>
            {folderPath.map((folder, index) => (
              <Fragment key={folder.id}>
                <BreadcrumbSeparator className='rtl:-scale-x-100' />
                <BreadcrumbItem>
                  {index === folderPath.length - 1 ? (
                    <BreadcrumbPage>{folder.name}</BreadcrumbPage>
                  ) : (
                    <BreadcrumbLink asChild>
                      <button
                        type='button'
                        onClick={() => onFolderChange(folder.id)}
                      >
                        {folder.name}
                      </button>
                    </BreadcrumbLink>
                  )}
                </BreadcrumbItem>
              </Fragment>
            ))}
          </BreadcrumbList>
        </Breadcrumb>

        <div className='flex flex-wrap items-center gap-2'>
          <div className='relative'>
            <Search className='absolute start-2.5 top-2.5 size-4 text-muted-foreground' />
            <Input
              value={search}
              onChange={(event) => setSearch(event.target.value)}
              placeholder={tPlaceHolder('search')}
              className='h-9 w-48 ps-8'
            />
          </div>

          <div className='flex items-center rounded-md border'>
            <button
              type='button'
              aria-label={tAction('grid_view')}
              onClick={() => setView('grid')}
              className={cn(
                'flex size-9 items-center justify-center rounded-s-md transition-colors hover:bg-accent',
                view === 'grid' ? 'bg-accent' : 'text-muted-foreground'
              )}
            >
              <LayoutGrid className='size-4' />
            </button>
            <button
              type='button'
              aria-label={tAction('list_view')}
              onClick={() => setView('list')}
              className={cn(
                'flex size-9 items-center justify-center rounded-e-md transition-colors hover:bg-accent',
                view === 'list' ? 'bg-accent' : 'text-muted-foreground'
              )}
            >
              <List className='size-4' />
            </button>
          </div>

          <Button variant='outline' size='sm' onClick={openNewFolderDialog}>
            <FolderPlus /> {tAction('new_folder')}
          </Button>
          <Button
            size='sm'
            onClick={() => inputRef.current?.click()}
            disabled={isUploading}
          >
            {isUploading ? <Loader2 className='animate-spin' /> : <Upload />}{' '}
            {tAction('upload')}
          </Button>
        </div>
      </div>

      {/* Drop zone */}
      <div
        className={cn(
          'relative flex-1 rounded-xl border border-dashed p-4 transition-colors',
          isDragging && 'border-primary bg-primary/5'
        )}
        onDragOver={(event) => {
          event.preventDefault()
          setIsDragging(true)
        }}
        onDragLeave={(event) => {
          if (event.currentTarget.contains(event.relatedTarget as Node)) return
          setIsDragging(false)
        }}
        onDrop={(event) => {
          event.preventDefault()
          setIsDragging(false)
          handleFiles(event.dataTransfer.files)
        }}
      >
        {isDragging && (
          <div className='pointer-events-none absolute inset-0 z-10 flex flex-col items-center justify-center gap-2 rounded-xl bg-background/80 backdrop-blur-sm'>
            <UploadCloud className='size-10 text-primary' />
            <p className='text-sm font-medium'>
              {mediaTMessage('info.drop_files')}
            </p>
          </div>
        )}

        {isLoading ? (
          <div className='grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 xl:grid-cols-8'>
            {Array.from({ length: 12 }).map((_, index) => (
              <div key={index} className='space-y-2'>
                <Skeleton className='aspect-square w-full rounded-lg' />
                <Skeleton className='h-3.5 w-3/4' />
              </div>
            ))}
          </div>
        ) : isEmpty ? (
          <div className='flex flex-col items-center justify-center gap-2 py-16 text-center'>
            {search ? (
              <>
                <SearchX
                  className='size-10 text-muted-foreground'
                  strokeWidth={1.5}
                />
                <p className='text-sm text-muted-foreground'>
                  {mediaTMessage('info.no_results')}
                </p>
              </>
            ) : (
              <>
                <UploadCloud
                  className='size-10 text-muted-foreground'
                  strokeWidth={1.5}
                />
                <p className='text-sm text-muted-foreground'>
                  {mediaTMessage('info.empty_folder')}
                </p>
                <Button
                  variant='outline'
                  size='sm'
                  onClick={() => inputRef.current?.click()}
                  disabled={isUploading}
                >
                  <Upload /> {tAction('upload')}
                </Button>
              </>
            )}
          </div>
        ) : (
          <div className='space-y-5'>
            {filteredFolders.length > 0 && (
              <section className='space-y-2'>
                <h3 className='text-xs font-semibold tracking-wide text-muted-foreground uppercase'>
                  {tLabel('folders')} ({filteredFolders.length})
                </h3>
                {view === 'grid' ? (
                  <div className='grid grid-cols-2 gap-3 sm:grid-cols-4 md:grid-cols-5 lg:grid-cols-7 xl:grid-cols-8'>
                    {filteredFolders.map((folder) => (
                      <FolderCard
                        key={folder.id}
                        folder={folder}
                        mode={mode}
                        onOpen={openFolder}
                      />
                    ))}
                  </div>
                ) : (
                  <div className='grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-4'>
                    {filteredFolders.map((folder) => (
                      <FolderCard
                        key={folder.id}
                        folder={folder}
                        mode={mode}
                        onOpen={openFolder}
                      />
                    ))}
                  </div>
                )}
              </section>
            )}

            {filteredFiles.length > 0 && (
              <section className='space-y-2'>
                <h3 className='text-xs font-semibold tracking-wide text-muted-foreground uppercase'>
                  {tLabel('files')} ({filteredFiles.length})
                </h3>
                {view === 'grid' ? (
                  <div className='grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 xl:grid-cols-8'>
                    {filteredFiles.map((item) => (
                      <MediaCard
                        key={item.id}
                        item={item}
                        mode={mode}
                        selected={selectedIds?.has(item.id)}
                        onSelect={onSelect}
                      />
                    ))}
                  </div>
                ) : (
                  <MediaListView
                    items={filteredFiles}
                    mode={mode}
                    selectedIds={selectedIds}
                    onSelect={onSelect}
                  />
                )}
              </section>
            )}
          </div>
        )}

        <input
          ref={inputRef}
          type='file'
          className='hidden'
          multiple
          accept={accept}
          onChange={(event) => {
            handleFiles(event.target.files)
            event.target.value = ''
          }}
        />
      </div>
    </div>
  )
}
