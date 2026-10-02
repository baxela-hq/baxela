import { ChevronDown, ChevronUp, Plus, X } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'

export type SectionItem = {
  id: number
}

type SectionCardProps<T extends SectionItem> = {
  title: string
  description: string
  addLabel: string
  emptyLabel: string
  items: T[]
  isLoading?: boolean
  /** Display fields for one row; imageUrl may be null (text fallback shows). */
  labelOf: (item: T) => {
    title: string
    imageUrl?: string | null
    meta?: string
  }
  onAdd: () => void
  onRemove: (id: number) => void
  /** Move an item one slot up (-1) or down (+1). */
  onMove: (id: number, direction: -1 | 1) => void
}

/**
 * One section of the Featured page: the ordered selection with
 * remove/reorder controls and the picker trigger. Rendering is
 * item-agnostic — the page passes product/category label extractors.
 */
export function SectionCard<T extends SectionItem>({
  title,
  description,
  addLabel,
  emptyLabel,
  items,
  isLoading,
  labelOf,
  onAdd,
  onRemove,
  onMove,
}: SectionCardProps<T>) {
  return (
    <Card>
      <CardHeader className='flex flex-row items-center justify-between gap-2'>
        <div className='space-y-1.5'>
          <CardTitle>{title}</CardTitle>
          <p className='text-sm text-muted-foreground'>{description}</p>
        </div>
        <Button type='button' variant='outline' size='sm' onClick={onAdd}>
          <Plus />
          {addLabel}
        </Button>
      </CardHeader>
      <CardContent>
        {isLoading ? (
          <div className='space-y-2'>
            <Skeleton className='h-12 w-full' />
            <Skeleton className='h-12 w-full' />
          </div>
        ) : items.length === 0 ? (
          <p className='rounded-md border border-dashed p-6 text-center text-sm text-muted-foreground'>
            {emptyLabel}
          </p>
        ) : (
          <ol className='space-y-2'>
            {items.map((item, index) => {
              const { title: itemTitle, imageUrl, meta } = labelOf(item)

              return (
                <li
                  key={item.id}
                  className='flex items-center gap-3 rounded-md border p-2.5'
                >
                  <span className='w-5 text-center text-xs text-muted-foreground'>
                    {index + 1}
                  </span>
                  <span className='flex size-10 shrink-0 items-center justify-center overflow-hidden rounded-md bg-muted text-xs font-medium text-muted-foreground'>
                    {imageUrl ? (
                      <img
                        src={imageUrl}
                        alt={itemTitle}
                        className='size-full object-cover'
                        loading='lazy'
                      />
                    ) : (
                      itemTitle.slice(0, 2)
                    )}
                  </span>
                  <span className='min-w-0 flex-1'>
                    <span className='block truncate text-sm font-medium'>
                      {itemTitle}
                    </span>
                    {meta ? (
                      <span className='block truncate text-xs text-muted-foreground'>
                        {meta}
                      </span>
                    ) : null}
                  </span>
                  <div className='flex items-center gap-1'>
                    <Button
                      type='button'
                      variant='ghost'
                      size='icon'
                      disabled={index === 0}
                      onClick={() => onMove(item.id, -1)}
                    >
                      <ChevronUp />
                    </Button>
                    <Button
                      type='button'
                      variant='ghost'
                      size='icon'
                      disabled={index === items.length - 1}
                      onClick={() => onMove(item.id, 1)}
                    >
                      <ChevronDown />
                    </Button>
                    <Button
                      type='button'
                      variant='ghost'
                      size='icon'
                      onClick={() => onRemove(item.id)}
                    >
                      <X />
                    </Button>
                  </div>
                </li>
              )
            })}
          </ol>
        )}
      </CardContent>
    </Card>
  )
}
