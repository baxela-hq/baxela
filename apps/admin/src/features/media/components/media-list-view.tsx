import { Fragment } from 'react'
import { Ellipsis, Eye, Link2, Download, Pencil, Trash2 } from 'lucide-react'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import { Checkbox } from '@/components/ui/checkbox'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { Locales } from '../data/routes'
import {
  formatBytes,
  formatDate,
  getDisplayName,
  type MediaItem,
} from '../data/schema'
import { useMediaActions } from '../hooks/use-media-actions'
import { MediaThumb } from './media-card'

type MediaListViewProps = {
  items: MediaItem[]
  mode: 'manage' | 'picker'
  selectedIds?: Set<number>
  onSelect?: (item: MediaItem) => void
}

export function MediaListView({
  items,
  mode,
  selectedIds,
  onSelect,
}: MediaListViewProps) {
  const { tLabel, tAction: mediaTAction } = useAppTranslation(Locales.MEDIA)
  // Generic actions (download/delete) come from common; row-selection aria
  // labels come from the shared data-table namespace
  const { tAction } = useAppTranslation(Locales.SHARED_COMMON)
  const { t: tDataTable } = useAppTranslation(Locales.SHARED_DATA_TABLE)
  const {
    openMediaPreview,
    openMediaEdit,
    openMediaDelete,
    copyUrl,
    download,
  } = useMediaActions()

  return (
    <div className='rounded-lg border'>
      <Table>
        <TableHeader>
          <TableRow>
            {mode === 'picker' && <TableHead className='w-10' />}
            <TableHead>{tLabel('name')}</TableHead>
            <TableHead className='hidden md:table-cell'>
              {tLabel('type')}
            </TableHead>
            <TableHead className='hidden sm:table-cell'>
              {tLabel('size')}
            </TableHead>
            <TableHead className='hidden lg:table-cell'>
              {tLabel('created_at')}
            </TableHead>
            <TableHead className='w-12' />
          </TableRow>
        </TableHeader>
        <TableBody>
          {items.map((item) => {
            const selected = selectedIds?.has(item.id) ?? false

            return (
              <TableRow
                key={item.id}
                className={selected ? 'bg-accent/50' : undefined}
                onClick={() => mode === 'picker' && onSelect?.(item)}
              >
                {mode === 'picker' && (
                  <TableCell className='pe-0'>
                    <Checkbox
                      checked={selected}
                      onCheckedChange={() => onSelect?.(item)}
                      aria-label={tDataTable('columns.aria-select-row')}
                      onClick={(e) => e.stopPropagation()}
                    />
                  </TableCell>
                )}
                <TableCell className='max-w-0'>
                  <button
                    type='button'
                    onClick={() =>
                      mode === 'picker'
                        ? onSelect?.(item)
                        : openMediaPreview(item)
                    }
                    className='flex min-w-0 items-center gap-2 text-start'
                  >
                    <span className='size-9 shrink-0 overflow-hidden rounded-md bg-muted'>
                      <MediaThumb item={item} />
                    </span>
                    <span
                      className='w-full truncate font-medium'
                      title={getDisplayName(item)}
                    >
                      {getDisplayName(item)}
                    </span>
                  </button>
                </TableCell>
                <TableCell className='hidden text-muted-foreground md:table-cell'>
                  {item.mime_type ?? '—'}
                </TableCell>
                <TableCell className='hidden text-muted-foreground sm:table-cell'>
                  {formatBytes(item.size)}
                </TableCell>
                <TableCell className='hidden text-muted-foreground lg:table-cell'>
                  {formatDate(item.created_at)}
                </TableCell>
                <TableCell>
                  <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                      <button
                        type='button'
                        className='flex size-8 items-center justify-center rounded-md hover:bg-accent'
                        onClick={(e) => e.stopPropagation()}
                      >
                        <Ellipsis className='size-4' />
                        <span className='sr-only'>
                          {mediaTAction('open_menu')}
                        </span>
                      </button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent
                      align='end'
                      onClick={(e) => e.stopPropagation()}
                    >
                      <DropdownMenuItem onClick={() => openMediaPreview(item)}>
                        <Eye /> {mediaTAction('preview')}
                      </DropdownMenuItem>
                      <DropdownMenuItem onClick={() => copyUrl(item)}>
                        <Link2 /> {mediaTAction('copy_url')}
                      </DropdownMenuItem>
                      <DropdownMenuItem onClick={() => download(item)}>
                        <Download /> {tAction('download')}
                      </DropdownMenuItem>
                      {mode === 'manage' && (
                        <Fragment>
                          <DropdownMenuSeparator />
                          <DropdownMenuItem onClick={() => openMediaEdit(item)}>
                            <Pencil /> {mediaTAction('rename_move')}
                          </DropdownMenuItem>
                          <DropdownMenuItem
                            variant='destructive'
                            onClick={() => openMediaDelete(item)}
                          >
                            <Trash2 /> {tAction('delete')}
                          </DropdownMenuItem>
                        </Fragment>
                      )}
                    </DropdownMenuContent>
                  </DropdownMenu>
                </TableCell>
              </TableRow>
            )
          })}
        </TableBody>
      </Table>
    </div>
  )
}
