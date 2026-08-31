import { DotsHorizontalIcon } from '@radix-ui/react-icons'
import { type Row } from '@tanstack/react-table'
import {
  Ban,
  Check,
  MessageSquareReply,
  Trash2,
  UserPen,
} from 'lucide-react'
import { Button } from '@/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuShortcut,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import { Locales } from '../data/routes'
import { type ProductComment } from '../data/schema'
import { useUpdateProductCommentStatus } from '../hooks/use-product-comment-mutations'
import { useProductComments } from './provider'

type DataTableRowActionsProps = {
  row: Row<ProductComment>
}

export function DataTableRowActions({ row }: DataTableRowActionsProps) {
  const { setOpen, setCurrentRow } = useProductComments()
  const { tAction } = useAppTranslation(Locales.SHARED_COMMON)
  const { tLabel } = useAppTranslation(Locales.PRODUCT_COMMENT)
  const updateStatus = useUpdateProductCommentStatus()

  // only top-level comments accept replies (one level deep)
  const canReply = row.original.parent_id === null

  return (
    <>
      <DropdownMenu modal={false}>
        <DropdownMenuTrigger asChild>
          <Button
            variant='ghost'
            className='flex h-8 w-8 p-0 data-[state=open]:bg-muted'
          >
            <DotsHorizontalIcon className='h-4 w-4' />
            <span className='sr-only'>Open menu</span>
          </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align='end' className='w-[160px]'>
          <DropdownMenuItem
            onClick={() => {
              setCurrentRow(row.original)
              setOpen('edit')
            }}
          >
            {tAction('edit')}
            <DropdownMenuShortcut>
              <UserPen size={16} />
            </DropdownMenuShortcut>
          </DropdownMenuItem>
          {canReply && (
            <DropdownMenuItem
              onClick={() => {
                setCurrentRow(row.original)
                setOpen('add')
              }}
            >
              {tLabel('reply')}
              <DropdownMenuShortcut>
                <MessageSquareReply size={16} />
              </DropdownMenuShortcut>
            </DropdownMenuItem>
          )}
          {row.original.status !== 'approved' && (
            <DropdownMenuItem
              onClick={() =>
                updateStatus.mutate({ comment: row.original, status: 'approved' })
              }
            >
              {tLabel('approve')}
              <DropdownMenuShortcut>
                <Check size={16} />
              </DropdownMenuShortcut>
            </DropdownMenuItem>
          )}
          {row.original.status !== 'rejected' && (
            <DropdownMenuItem
              onClick={() =>
                updateStatus.mutate({ comment: row.original, status: 'rejected' })
              }
            >
              {tLabel('reject')}
              <DropdownMenuShortcut>
                <Ban size={16} />
              </DropdownMenuShortcut>
            </DropdownMenuItem>
          )}
          <DropdownMenuSeparator />
          <DropdownMenuItem
            onClick={() => {
              setCurrentRow(row.original)
              setOpen('delete')
            }}
            className='text-red-500!'
          >
            {tAction('delete')}
            <DropdownMenuShortcut>
              <Trash2 size={16} />
            </DropdownMenuShortcut>
          </DropdownMenuItem>
        </DropdownMenuContent>
      </DropdownMenu>
    </>
  )
}
