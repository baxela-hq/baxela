import { useQuery } from '@tanstack/react-query';
import { getRouteApi } from '@tanstack/react-router';
import { type PaginatedResponse } from '@/shared/types/common.types.ts';
import { SkeletonWidget } from '@/components/shared/skeleton-widget'
import { FeatureRoutes, Locales } from './data/routes.ts';
import { Dialogs } from './components/dialogs.tsx';
import { PrimaryButtons } from './components/primary-buttons.tsx';
import { Provider } from './components/provider.tsx';
import { DataTable } from './components/data-table.tsx';
import { type Attribute } from './data/schema.ts';
import { fetchAttributes } from './api/attributes.api.ts'
import { useAppTranslation } from '@/hooks/useAppTranslation'

const route = getRouteApi('/_authenticated/catalog/attributes/')

export function Attributes() {
  const search = route.useSearch()
  const navigate = route.useNavigate()
  const { tPageTitle } = useAppTranslation(Locales.SHARED_COMMON)
  const { tLabel } = useAppTranslation(Locales.ATTRIBUTE)
  const entityName = {
    singular: tLabel("attribute"),
    plural: tLabel("attributes")
  };

  const {data, isLoading, isSuccess} = useQuery<PaginatedResponse<Attribute>>({
    queryKey: [FeatureRoutes.CACHE_KEY, search ],
    queryFn: () => fetchAttributes(search),
  });

  return (
    <Provider>
      <div className='flex flex-1 flex-col gap-4'>
        <div className='flex flex-wrap items-end justify-between gap-2'>
          <div>
            <h2 className='text-2xl font-bold tracking-tight'>
              {tPageTitle("index.title", {entity: entityName.singular})}
            </h2>
            <p className='text-muted-foreground'>
              {tPageTitle("index.subtitle", {entity: entityName.plural.toLowerCase()})}
            </p>
          </div>
          <PrimaryButtons />
        </div>
        {isLoading &&  <SkeletonWidget />}
        {isSuccess && <DataTable data={data} search={search} navigate={navigate} />}
      </div>

      <Dialogs />
    </Provider>
  )
}
