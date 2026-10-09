import { useEffect, useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { LoaderIcon, SearchIcon, XIcon } from 'lucide-react'
import { Input } from '@/components/ui/input'
import { fetchProducts } from '@/features/catalog/products/api/products.api'
import type { Product } from '@/features/catalog/products/data/schema'
import { pickTranslation } from '@/shared/lib/locale'
import { FeatureRoutes } from '../data/routes'

/**
 * Searchable async multi-select over the admin products endpoint: type to
 * search (title filter, debounced), click a result to add it, chips carry
 * the selection. Existing selections survive re-searches untouched.
 */
export function PostProductPicker({
  selectedIds,
  onChange,
  label,
  placeholder,
  noResults,
}: {
  selectedIds: number[]
  onChange: (ids: number[]) => void
  label: string
  placeholder: string
  noResults: string
}) {
  const [term, setTerm] = useState('')
  const [debounced, setDebounced] = useState('')

  useEffect(() => {
    const timer = setTimeout(() => setDebounced(term), 400)
    return () => clearTimeout(timer)
  }, [term])

  const { data, isFetching } = useQuery({
    queryKey: [FeatureRoutes.CACHE_KEY, 'product-picker', debounced],
    queryFn: () =>
      fetchProducts({
        'filter[title]': debounced,
        per_page: 5,
      }),
    enabled: debounced.trim().length > 0,
  })

  const results = (data?.data ?? []).filter(
    (product: Product) => !selectedIds.includes(product.id)
  )

  return (
    <div className='space-y-2'>
      <div className='relative'>
        <SearchIcon className='pointer-events-none absolute start-2.5 top-2.5 size-4 text-muted-foreground' />
        <Input
          aria-label={label}
          className='ps-8'
          placeholder={placeholder}
          value={term}
          onChange={(e) => setTerm(e.target.value)}
        />
        {isFetching && (
          <LoaderIcon className='absolute end-2.5 top-2.5 size-4 animate-spin text-muted-foreground' />
        )}
      </div>

      {debounced.trim().length > 0 && (
        <div className='rounded-md border'>
          {results.length === 0 && !isFetching ? (
            <p className='p-2 text-sm text-muted-foreground'>{noResults}</p>
          ) : (
            results.map((product: Product) => {
              const title =
                pickTranslation(product.translations ?? [])?.title ??
                `#${product.id}`
              return (
                <button
                  key={product.id}
                  type='button'
                  className='block w-full p-2 text-start text-sm hover:bg-muted'
                  onClick={() => onChange([...selectedIds, product.id])}
                >
                  {title} <span className='text-muted-foreground'>(#{product.id})</span>
                </button>
              )
            })
          )}
        </div>
      )}

      {selectedIds.length > 0 && (
        <div className='flex flex-wrap gap-1.5'>
          {selectedIds.map((productId) => (
            <SelectedProductChip
              key={productId}
              productId={productId}
              onRemove={() =>
                onChange(selectedIds.filter((v) => v !== productId))
              }
            />
          ))}
        </div>
      )}
    </div>
  )
}

/** Resolves the product's title lazily — the picker only stores ids. */
function SelectedProductChip({
  productId,
  onRemove,
}: {
  productId: number
  onRemove: () => void
}) {
  const { data } = useQuery({
    queryKey: [FeatureRoutes.CACHE_KEY, 'product-chip', productId],
    queryFn: async () => {
      // Exact id filter keeps it one request within the shared cache entry
      const page = await fetchProducts({ 'filter[id]': productId, per_page: 1 })
      return page.data[0] ?? null
    },
    staleTime: 5 * 60 * 1000,
  })

  const title = data
    ? (pickTranslation(data.translations ?? [])?.title ?? `#${productId}`)
    : `#${productId}`

  return (
    <span className='inline-flex items-center gap-1 rounded-md border bg-muted/40 px-2 py-0.5 text-sm'>
      {title}
      <button type='button' onClick={onRemove} aria-label={`remove ${productId}`}>
        <XIcon className='size-3.5 text-muted-foreground' />
      </button>
    </span>
  )
}
