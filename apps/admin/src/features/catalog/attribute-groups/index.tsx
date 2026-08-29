import { getRouteApi } from '@tanstack/react-router';
import { SkeletonWidget } from '@/components/shared/skeleton-widget'
import { Locales } from './data/routes.ts';
import { Dialogs } from './components/dialogs.tsx';
import { PrimaryButtons } from './components/primary-buttons.tsx';
import { Provider } from './components/provider.tsx';
import { DataTable } from './components/data-table.tsx';
import { useAttributeGroupsList } from './hooks/use-attribute-groups';
import { useAppTranslation } from '@/hooks/useAppTranslation'

const route = getRouteApi('/_authenticated/catalog/attributes/groups')

export function AttributeGroups() {
  const search = route.useSearch()
  const navigate = route.useNavigate()
  const { tPageTitle } = useAppTranslation(Locales.SHARED_COMMON)
  const { tLabel } = useAppTranslation(Locales.ATTRIBUTE_GROUP)
  const entityName = {
    singular: tLabel("attribute_group"),
    plural: tLabel("attribute_groups")
  };

  const {data, isLoading, isSuccess} = useAttributeGroupsList(search);

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
