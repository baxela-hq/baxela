import { useEffect, useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { pickTranslation } from '@/shared/lib/locale'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Skeleton } from '@/components/ui/skeleton'
import { fetchPosts } from '@/features/content/posts/api/posts.api'
import { type Post } from '../../posts/data/schema'
import { Locales as PostsLocales } from '../../posts/data/routes'
import { Locales } from '../data/routes'

const MIN_QUERY_LENGTH = 2
const RESULT_LIMIT = 20

type PostPickerDialogProps = {
  open: boolean
  onOpenChange: (open: boolean) => void
  /** Ids already in the selection — picked but disabled. */
  selectedIds: number[]
  /** Called with the newly picked posts (uncommitted until confirmed). */
  onConfirm: (posts: Post[]) => void
}

function useDebouncedValue<T>(value: T, delayMs: number): T {
  const [debounced, setDebounced] = useState(value)

  useEffect(() => {
    const timeout = setTimeout(() => setDebounced(value), delayMs)
    return () => clearTimeout(timeout)
  }, [value, delayMs])

  return debounced
}

/**
 * Server-side post picker: searches by (any-language) title through the
 * admin posts endpoint, debounced, and returns the checked posts.
 */
export function PostPickerDialog({
  open,
  onOpenChange,
  selectedIds,
  onConfirm,
}: PostPickerDialogProps) {
  const { tLabel, tPlaceHolder, tStatus } = useAppTranslation(Locales.FEATURED)
  const { tAction } = useAppTranslation(Locales.SHARED_COMMON)
  const { tStatus: tPostStatus } = useAppTranslation(PostsLocales.POST)

  const [term, setTerm] = useState('')
  const [picked, setPicked] = useState<Post[]>([])

  const debouncedTerm = useDebouncedValue(term, 300)
  const trimmed = debouncedTerm.trim()
  const enabled = open && trimmed.length >= MIN_QUERY_LENGTH

  const results = useQuery({
    queryKey: ['featured-posts', 'post-picker', trimmed],
    queryFn: () => fetchPosts({ 'filter[title]': trimmed, per_page: RESULT_LIMIT }),
    enabled,
  })

  const existing = new Set(selectedIds)

  const handleOpenChange = (state: boolean) => {
    if (!state) {
      setTerm('')
      setPicked([])
    }
    onOpenChange(state)
  }

  const togglePick = (post: Post, checked: boolean) => {
    setPicked((prev) =>
      checked
        ? [...prev, post]
        : prev.filter((candidate) => candidate.id !== post.id)
    )
  }

  const handleConfirm = () => {
    onConfirm(picked)
    setTerm('')
    setPicked([])
    onOpenChange(false)
  }

  const labelOf = (post: Post) =>
    pickTranslation(post.translations)?.title ?? `#${post.id}`

  return (
    <Dialog open={open} onOpenChange={handleOpenChange}>
      <DialogContent className='flex max-h-[85vh] w-full flex-col gap-0 sm:max-w-lg'>
        <DialogHeader className='text-start'>
          <DialogTitle>{tLabel('add-posts')}</DialogTitle>
          <DialogDescription>{tStatus('picker-search-hint')}</DialogDescription>
        </DialogHeader>

        <div className='min-h-0 flex-1 space-y-3 overflow-y-auto px-4 pt-2 pb-4'>
          <Input
            value={term}
            onChange={(event) => setTerm(event.target.value)}
            placeholder={tPlaceHolder('search-posts')}
            autoFocus
          />

          {!enabled ? (
            <p className='py-8 text-center text-sm text-muted-foreground'>
              {tStatus('type-to-search')}
            </p>
          ) : results.isFetching ? (
            <div className='space-y-2'>
              <Skeleton className='h-10 w-full' />
              <Skeleton className='h-10 w-full' />
              <Skeleton className='h-10 w-full' />
            </div>
          ) : (results.data?.data ?? []).length === 0 ? (
            <p className='py-8 text-center text-sm text-muted-foreground'>
              {tStatus('no-results')}
            </p>
          ) : (
            <div className='space-y-1'>
              {results.data?.data.map((post) => {
                const isAlreadySelected = existing.has(post.id)
                const isPicked =
                  isAlreadySelected ||
                  picked.some((candidate) => candidate.id === post.id)

                return (
                  <label
                    key={post.id}
                    className='flex cursor-pointer items-center gap-3 rounded-md border p-2.5'
                  >
                    <Checkbox
                      checked={isPicked}
                      disabled={isAlreadySelected}
                      onCheckedChange={(checked) =>
                        togglePick(post, checked === true)
                      }
                    />
                    <span className='flex size-9 shrink-0 items-center justify-center overflow-hidden rounded-md bg-muted text-xs text-muted-foreground'>
                      {labelOf(post).slice(0, 2)}
                    </span>
                    <span className='min-w-0 flex-1'>
                      <span className='block truncate text-sm font-medium'>
                        {labelOf(post)}
                      </span>
                      <span className='block truncate text-xs text-muted-foreground'>
                        {tPostStatus(`status.${post.status}`)}
                      </span>
                    </span>
                  </label>
                )
              })}
            </div>
          )}
        </div>

        <DialogFooter>
          <Button
            type='button'
            variant='outline'
            onClick={() => handleOpenChange(false)}
          >
            {tAction('cancel')}
          </Button>
          <Button
            type='button'
            disabled={picked.length === 0}
            onClick={handleConfirm}
          >
            {tAction('confirm')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
