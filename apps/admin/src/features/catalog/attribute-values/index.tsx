import { useQuery } from '@tanstack/react-query';
import { getRouteApi } from '@tanstack/react-router';
import { type PaginatedResponse } from '@/shared/types/common.types.ts';
import { SkeletonWidget } from '@/components/shared/skeleton-widget'
import { FeatureRoutes, Locales } from './data/routes.ts';
import { Dialogs } from './components/dialogs.tsx';
import { PrimaryButtons } from './components/primary-buttons.tsx';
import { Provider } from './components/provider.tsx';
import { DataTable } from './components/data-table.tsx';
import { type AttributeValue } from './data/schema.ts';
import { fetchAttributeValues } from './api/attribute-values.api.ts'
import { fetchOneAttribute } from '../attributes/api/attributes.api.ts'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import { type Attribute } from '../attributes/data/schema.ts';
import { getDefaultLanguage } from '@/shared/lib/locale.ts';

const route = getRouteApi('/_authenticated/catalog/attributes/values/$id/')

export function AttributeValues() {
  const search = route.useSearch()
  const navigate = route.useNavigate()
  const { id } = route.useParams()
  const { tPageTitle } = useAppTranslation(Locales.SHARED_COMMON)
  const { tLabel } = useAppTranslation(Locales.ATTRIBUTE_VALUE)
  const entityName = {
    singular: tLabel("value"),
    plural: tLabel("values")
  };

  const {data, isLoading, isSuccess} = useQuery<PaginatedResponse<AttributeValue>>({
    queryKey: [FeatureRoutes.CACHE_KEY, id, search ],
    queryFn: () => fetchAttributeValues(id, search),
  });

  const {data: attributeData, isSuccess: attributeIsSuccess} = useQuery<Attribute>({
    queryKey: ['attribute', id ],
    queryFn: () => fetchOneAttribute(id),
  });

  const languageIndex = attributeIsSuccess && attributeData
    ? (getDefaultLanguage(attributeData.translations) ?? 0)
    : 0;


  return (
    <Provider>
      <div className='flex flex-1 flex-col gap-4'>
        <div className='flex flex-wrap items-end justify-between gap-2'>
          <div>
            <h2 className='text-2xl font-bold tracking-tight'>
              {tPageTitle("index.title", {entity: entityName.singular})}
              {' '} {attributeIsSuccess && attributeData.translations[languageIndex].title}
            </h2>
            <p className='text-muted-foreground'>
              {tPageTitle("index.subtitle", {entity: entityName.plural.toLowerCase()})}
            </p>
          </div>
          <PrimaryButtons />
        </div>
        {isLoading &&  <SkeletonWidget />}
        {isSuccess && <DataTable data={data} search={search} navigate={navigate} attributeId={id} />}
      </div>

      <Dialogs attributeId={id} />
    </Provider>
  )
}
