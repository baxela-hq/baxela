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
import { fetchProducts } from '@/features/catalog/products/api/products.api'
import { type Product } from '../../products/data/schema'
import { Locales } from '../data/routes'

const MIN_QUERY_LENGTH = 2
const RESULT_LIMIT = 20

type ProductPickerDialogProps = {
  open: boolean
  onOpenChange: (open: boolean) => void
  /** Ids already in the selection — picked but disabled. */
  selectedIds: number[]
  /** Called with the newly picked products (uncommitted until confirmed). */
  onConfirm: (products: Product[]) => void
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
 * Server-side product picker: searches by (any-language) title through the
 * admin products endpoint, debounced, and returns the checked products.
 */
export function ProductPickerDialog({
  open,
  onOpenChange,
  selectedIds,
  onConfirm,
}: ProductPickerDialogProps) {
  const { tLabel, tPlaceHolder, tStatus } = useAppTranslation(Locales.FEATURED)
  const { tAction } = useAppTranslation(Locales.SHARED_COMMON)

  const [term, setTerm] = useState('')
  const [picked, setPicked] = useState<Product[]>([])

  const debouncedTerm = useDebouncedValue(term, 300)
  const trimmed = debouncedTerm.trim()
  const enabled = open && trimmed.length >= MIN_QUERY_LENGTH

  const results = useQuery({
    queryKey: ['featured', 'product-picker', trimmed],
    queryFn: () =>
      fetchProducts({ 'filter[title]': trimmed, per_page: RESULT_LIMIT }),
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

  const togglePick = (product: Product, checked: boolean) => {
    setPicked((prev) =>
      checked
        ? [...prev, product]
        : prev.filter((candidate) => candidate.id !== product.id)
    )
  }

  const handleConfirm = () => {
    onConfirm(picked)
    setTerm('')
    setPicked([])
    onOpenChange(false)
  }

  const labelOf = (product: Product) =>
    pickTranslation(product.translations)?.title ?? `#${product.id}`

  return (
    <Dialog open={open} onOpenChange={handleOpenChange}>
      <DialogContent className='flex max-h-[85vh] w-full flex-col gap-0 sm:max-w-lg'>
        <DialogHeader className='text-start'>
          <DialogTitle>{tLabel('add-products')}</DialogTitle>
          <DialogDescription>{tStatus('picker-search-hint')}</DialogDescription>
        </DialogHeader>

        <div className='min-h-0 flex-1 space-y-3 overflow-y-auto px-4 pt-2 pb-4'>
          <Input
            value={term}
            onChange={(event) => setTerm(event.target.value)}
            placeholder={tPlaceHolder('search-products')}
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
              {results.data?.data.map((product) => {
                const isAlreadySelected = existing.has(product.id)
                const isPicked =
                  isAlreadySelected ||
                  picked.some((candidate) => candidate.id === product.id)

                return (
                  <label
                    key={product.id}
                    className='flex cursor-pointer items-center gap-3 rounded-md border p-2.5'
                  >
                    <Checkbox
                      checked={isPicked}
                      disabled={isAlreadySelected}
                      onCheckedChange={(checked) =>
                        togglePick(product, checked === true)
                      }
                    />
                    <span className='flex size-9 shrink-0 items-center justify-center overflow-hidden rounded-md bg-muted text-xs text-muted-foreground'>
                      {product.images?.[0]?.url ? (
                        <img
                          src={product.images[0].url}
                          alt={labelOf(product)}
                          className='size-full object-cover'
                          loading='lazy'
                        />
                      ) : (
                        labelOf(product).slice(0, 2)
                      )}
                    </span>
                    <span className='min-w-0 flex-1'>
                      <span className='block truncate text-sm font-medium'>
                        {labelOf(product)}
                      </span>
                      <span className='block truncate text-xs text-muted-foreground'>
                        {product.variants?.[0]?.price ?? ''}
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
