import { getRouteApi } from '@tanstack/react-router';
import { SkeletonWidget } from '@/components/shared/skeleton-widget'
import { Locales } from './data/routes.ts';
import { Dialogs } from './components/dialogs.tsx';
import { PrimaryButtons } from './components/primary-buttons.tsx';
import { Provider } from './components/provider.tsx';
import { DataTable } from './components/data-table.tsx';
import { useAttributeValuesList } from './hooks/use-attribute-values';
import { useOneAttribute } from '../attributes/hooks/use-attributes';
import { useAppTranslation } from '@/hooks/useAppTranslation'
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

  const {data, isLoading, isSuccess} = useAttributeValuesList(id, search);

  const {data: attributeData, isSuccess: attributeIsSuccess} = useOneAttribute(id);

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
