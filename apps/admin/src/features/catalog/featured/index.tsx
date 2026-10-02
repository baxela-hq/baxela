import { useMemo, useState } from 'react'
import { pickTranslation } from '@/shared/lib/locale'
import { Save } from 'lucide-react'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import { Button } from '@/components/ui/button'
import { Header } from '@/components/layout/header'
import { HeaderActions } from '@/components/layout/header-actions'
import { Main } from '@/components/layout/main'
import { Search } from '@/components/search'
import { SkeletonWidget } from '@/components/shared/skeleton-widget'
import { type Category } from '../categories/data/schema'
import { type Product } from '../products/data/schema'
import { CategoryPickerDialog } from './components/category-picker-dialog'
import { ProductPickerDialog } from './components/product-picker-dialog'
import { SectionCard } from './components/section-card'
import { Locales } from './data/routes'
import { firstProductImageUrl } from './data/schema'
import { useFeatured } from './hooks/use-featured'
import { useUpdateFeatured } from './hooks/use-featured-mutations'

/**
 * Featured products & categories — the storefront home page selections.
 * Two sections, each an ordered list: pick items, reorder with the arrows,
 * then Save (full sync, array order encodes position).
 */
export function Featured() {
  const { tPageTitle, tLabel, tHelpText } = useAppTranslation(Locales.FEATURED)
  const { tAction } = useAppTranslation(Locales.SHARED_COMMON)
  const { data, isLoading } = useFeatured()
  const updateFeatured = useUpdateFeatured()

  const [products, setProducts] = useState<Product[]>([])
  const [categories, setCategories] = useState<Category[]>([])
  const [syncedProducts, setSyncedProducts] = useState<Product[] | null>(null)
  const [syncedCategories, setSyncedCategories] = useState<Category[] | null>(
    null
  )
  const [productPickerOpen, setProductPickerOpen] = useState(false)
  const [categoryPickerOpen, setCategoryPickerOpen] = useState(false)

  // Re-sync the editable lists whenever fresh server data arrives (initial
  // load and post-save refetch) — setState during render, per React's
  // "adjust state when props change" pattern.
  if (data && data.product !== syncedProducts) {
    setProducts(data.product)
    setSyncedProducts(data.product)
  }
  if (data && data.category !== syncedCategories) {
    setCategories(data.category)
    setSyncedCategories(data.category)
  }

  const saved = useMemo(
    () => ({
      productIds: (data?.product ?? []).map((product) => product.id),
      categoryIds: (data?.category ?? []).map((category) => category.id),
    }),
    [data]
  )

  const isDirty =
    products.map((product) => product.id).join(',') !==
      saved.productIds.join(',') ||
    categories.map((category) => category.id).join(',') !==
      saved.categoryIds.join(',')

  const moveItem = <T extends { id: number }>(
    items: T[],
    id: number,
    direction: -1 | 1
  ): T[] => {
    const index = items.findIndex((item) => item.id === id)
    const target = index + direction
    if (index === -1 || target < 0 || target >= items.length) return items

    const next = [...items]
    ;[next[index], next[target]] = [next[target], next[index]]

    return next
  }

  const handleSave = () => {
    updateFeatured.mutate({
      product_ids: products.map((product) => product.id),
      category_ids: categories.map((category) => category.id),
    })
  }

  const productLabel = (product: Product) => {
    const title =
      pickTranslation(product.translations)?.title ?? `#${product.id}`
    const price = product.variants?.[0]?.price

    return { title, imageUrl: firstProductImageUrl(product), meta: price }
  }

  const categoryLabel = (category: Category) => ({
    title: pickTranslation(category.translations)?.title ?? `#${category.id}`,
    imageUrl: category.image_url,
  })

  if (isLoading) {
    return (
      <>
        <Header fixed>
          <Search />
          <HeaderActions />
        </Header>
        <Main>
          <SkeletonWidget />
        </Main>
      </>
    )
  }

  return (
    <>
      <Header fixed>
        <Search />
        <HeaderActions />
      </Header>

      <Main className='flex flex-1 flex-col gap-4 sm:gap-6'>
        <div className='flex flex-wrap items-end justify-between gap-2'>
          <div>
            <h2 className='text-2xl font-bold tracking-tight'>
              {tPageTitle('index.title')}
            </h2>
            <p className='text-muted-foreground'>
              {tPageTitle('index.subtitle')}
            </p>
          </div>
          <Button
            type='button'
            disabled={!isDirty || updateFeatured.isPending}
            onClick={handleSave}
          >
            <Save />
            {tAction('save')}
          </Button>
        </div>

        <div className='grid gap-4 lg:grid-cols-2'>
          <SectionCard
            title={tLabel('products-section')}
            description={tHelpText('products-section')}
            addLabel={tLabel('add-products')}
            emptyLabel={tHelpText('empty-products')}
            items={products}
            labelOf={productLabel}
            onAdd={() => setProductPickerOpen(true)}
            onRemove={(id) =>
              setProducts((prev) => prev.filter((product) => product.id !== id))
            }
            onMove={(id, direction) =>
              setProducts((prev) => moveItem(prev, id, direction))
            }
          />

          <SectionCard
            title={tLabel('categories-section')}
            description={tHelpText('categories-section')}
            addLabel={tLabel('add-categories')}
            emptyLabel={tHelpText('empty-categories')}
            items={categories}
            labelOf={categoryLabel}
            onAdd={() => setCategoryPickerOpen(true)}
            onRemove={(id) =>
              setCategories((prev) =>
                prev.filter((category) => category.id !== id)
              )
            }
            onMove={(id, direction) =>
              setCategories((prev) => moveItem(prev, id, direction))
            }
          />
        </div>
      </Main>

      <ProductPickerDialog
        open={productPickerOpen}
        onOpenChange={setProductPickerOpen}
        selectedIds={products.map((product) => product.id)}
        onConfirm={(picked) =>
          setProducts((prev) => [
            ...prev,
            ...picked.filter(
              (product) => !prev.some((existing) => existing.id === product.id)
            ),
          ])
        }
      />

      <CategoryPickerDialog
        open={categoryPickerOpen}
        onOpenChange={setCategoryPickerOpen}
        selectedIds={categories.map((category) => category.id)}
        onConfirm={(picked) =>
          setCategories((prev) => [
            ...prev,
            ...picked.filter(
              (category) =>
                !prev.some((existing) => existing.id === category.id)
            ),
          ])
        }
      />
    </>
  )
}
