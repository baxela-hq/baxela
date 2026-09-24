import { useAppTranslation } from '@/hooks/useAppTranslation'
import { Header } from '@/components/layout/header'
import { HeaderActions } from '@/components/layout/header-actions'
import { Main } from '@/components/layout/main'
import { Search } from '@/components/search'
import { SkeletonWidget } from '@/components/shared/skeleton-widget'
import { MethodRow } from './components/method-row'
import { Locales } from './data/routes'
import { type PaymentMethod, type PaymentMethodUpdate } from './data/schema'
import {
  useReorderPaymentMethods,
  useUpdatePaymentMethod,
} from './hooks/use-payment-method-mutations'
import { usePaymentMethods } from './hooks/use-payment-methods'

export function PaymentMethods() {
  const { tPageTitle } = useAppTranslation(Locales.SHARED_COMMON)
  const { tLabel } = useAppTranslation(Locales.PAYMENT_METHOD)
  const entityName = {
    singular: tLabel('payment-method'),
    plural: tLabel('payment-methods'),
  }

  const { data, isLoading } = usePaymentMethods()
  const updateMethod = useUpdatePaymentMethod()
  const reorderMethods = useReorderPaymentMethods()

  const methods = data?.data ?? []
  const pending = updateMethod.isPending || reorderMethods.isPending

  const handleToggle = (method: PaymentMethod, isActive: boolean) => {
    updateMethod.mutate({
      id: method.id,
      data: {
        is_active: isActive,
        sort_order: method.sort_order,
      },
    })
  }

  // Moving a row reindexes positions sequentially and only patches the
  // rows whose sort_order actually changed.
  const handleMove = (method: PaymentMethod, direction: 'up' | 'down') => {
    const index = methods.findIndex((row) => row.id === method.id)
    const target = direction === 'up' ? index - 1 : index + 1
    if (index < 0 || target < 0 || target >= methods.length) return

    const reordered = [...methods]
    ;[reordered[index], reordered[target]] = [
      reordered[target],
      reordered[index],
    ]

    const updates = reordered
      .map((row, position) => ({
        id: row.id,
        data: {
          is_active: row.is_active,
          sort_order: position + 1,
        } satisfies PaymentMethodUpdate,
        changed: row.sort_order !== position + 1,
      }))
      .filter((update) => update.changed)
      .map(({ id, data }) => ({ id, data }))

    if (updates.length > 0) reorderMethods.mutate({ updates })
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
              {tPageTitle('index.title', { entity: entityName.plural })}
            </h2>
            <p className='text-muted-foreground'>
              {tPageTitle('index.subtitle', {
                entity: entityName.plural.toLowerCase(),
              })}
            </p>
          </div>
        </div>

        {isLoading && <SkeletonWidget />}
        {!isLoading && (
          <div className='flex flex-col gap-3'>
            {methods.map((method, index) => (
              <MethodRow
                key={method.id}
                method={method}
                isFirst={index === 0}
                isLast={index === methods.length - 1}
                pending={pending}
                onToggle={handleToggle}
                onMove={handleMove}
              />
            ))}
          </div>
        )}
      </Main>
    </>
  )
}
