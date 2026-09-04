import { getRouteApi } from '@tanstack/react-router';
import { useAppTranslation } from '@/hooks/useAppTranslation';
import { Badge } from "@/components/ui/badge";
import { Card, CardHeader, CardTitle, CardContent } from "@/components/ui/card";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import { ConfigDrawer } from '@/components/config-drawer';
import { Header } from '@/components/layout/header';
import { Main } from '@/components/layout/main';
import { ProfileDropdown } from '@/components/profile-dropdown';
import { Search } from '@/components/search';
import { SkeletonWidget as SkeletonWidgetFromFile } from '@/components/shared/skeleton-widget'
import { ThemeSwitch } from '@/components/theme-switch'
import { Dialogs } from './components/dialogs.tsx';
import { Provider } from './components/provider.tsx';
import { Buttons } from './components/buttons.tsx'
import { Locales } from './data/routes.ts'
import {
  useOneOrder,
  useOrderItems,
  useInvalidateOrder,
} from './hooks/use-orders'
import { getDefaultCurrency } from '@/shared/lib/locale'
import { useFormatDateTime } from '@/shared/hooks/use-format-date-time.ts'



const route = getRouteApi('/_authenticated/order/orders/$id/show')


export function OrderShow() {
  const { id } = route.useParams()
  const { tPageTitle, tLabel:tcLabel } = useAppTranslation(Locales.SHARED_COMMON)
  const { tLabel, tMessage, tStatus } = useAppTranslation(Locales.ORDER)
  const { formatDateTime } = useFormatDateTime()
  const currency = getDefaultCurrency()
  const symbol = currency?.symbol ?? '$'
  const formatPrice = (price: string | number) =>
    currency?.is_symbol_right ? `${price} ${symbol}` : `${symbol}${price}`
  const entityName = {
    singular: tLabel("order"),
    plural: tLabel("orders")
  };

  const clearCache = useInvalidateOrder(id)

  const {data: record, isLoading } = useOneOrder(id)

  const {data: items} = useOrderItems(id)


  return (
    <Provider>
      <Header fixed>
        <Search />
        <div className='ms-auto flex items-center space-x-4'>
          <ThemeSwitch />
          <ConfigDrawer />
          <ProfileDropdown />
        </div>
      </Header>

      <Main className='flex flex-1 flex-col gap-4 sm:gap-6'>
        <div className='flex flex-wrap items-end justify-between gap-2'>
          <div>
            <h2 className='text-2xl font-bold tracking-tight'>
              {tPageTitle("show.title", {entity: entityName.singular, id: id ? id.toString() : ""})}
            </h2>
            <p className='text-muted-foreground'>
              {tPageTitle("show.subtitle")}
            </p>
          </div>
          <div className='flex gap-2'>
            {record && <Buttons entityName={entityName} record={record} />}
          </div>
        </div>

        <div className="w-full space-y-3">

          { isLoading && <SkeletonWidgetFromFile /> }

          {/* ORDER INFORMATION */}
          { record &&
            <Card className="w-full">
              <CardHeader>
                <CardTitle>{entityName.singular} #{record.id}</CardTitle>
              </CardHeader>

              <CardContent className="space-y-3">
                <Table className="w-full">
                  <TableHeader>
                    <TableRow>
                      <TableHead>{tLabel('id')}</TableHead>
                      <TableHead>{tLabel('user_id')}</TableHead>
                      <TableHead>{tLabel('total_amount')}</TableHead>
                      <TableHead>{tLabel('description')}</TableHead>
                      <TableHead>{tLabel('status')}</TableHead>
                      <TableHead>{tLabel('created_at')}</TableHead>
                      <TableHead>{tLabel('updated_at')}</TableHead>
                    </TableRow>
                  </TableHeader>

                  <TableBody>
                    <TableRow>
                      <TableCell>{record.id}</TableCell>
                      <TableCell>{record.user_id}</TableCell>
                      <TableCell>{formatPrice(record.total_amount)}</TableCell>
                      <TableCell className="max-w-[240px] truncate">
                        {record.description}
                      </TableCell>
                      <TableCell>
                        <Badge variant="outline">{tStatus(`status.${record.status}`)}</Badge>
                      </TableCell>
                      <TableCell>{formatDateTime(record.created_at)}</TableCell>
                      <TableCell>{formatDateTime(record.updated_at)}</TableCell>
                    </TableRow>
                  </TableBody>
                </Table>
              </CardContent>
            </Card>
          }

          {/* ITEMS SECTION */}
            {
              items && items.length > 0 &&
              <Card>
                <CardHeader>
                  <CardTitle>{tcLabel('items')} ({items.length})</CardTitle>
                </CardHeader>

                <CardContent className="space-y-6">
                  <Table>
                    <TableHeader>
                      <TableRow>
                        <TableHead>{tLabel('id')}</TableHead>
                        <TableHead>{tLabel('variant_id')}</TableHead>
                        <TableHead>{tLabel('product_name_snapshot')}</TableHead>
                        <TableHead>{tLabel('quantity')}</TableHead>
                        <TableHead>{tLabel('price_snapshot')}</TableHead>
                      </TableRow>
                    </TableHeader>

                    <TableBody>
                      {items.map((item) => (
                        <TableRow key={item.id}>
                          <TableCell>{item.id}</TableCell>
                          <TableCell>{item.variant_id}</TableCell>
                          <TableCell className="font-medium">
                            {item.product_name_snapshot}
                          </TableCell>
                          <TableCell>{item.quantity}</TableCell>
                          <TableCell>{formatPrice(item.price_snapshot)}</TableCell>
                        </TableRow>
                      ))}

                      {items.length === 0 && (
                        <TableRow>
                          <TableCell
                            colSpan={5}
                            className="text-center text-muted-foreground"
                          >
                            {tMessage('no_items')}
                          </TableCell>
                        </TableRow>
                      )}
                    </TableBody>
                  </Table>
                </CardContent>
              </Card>
            }

        </div>

      </Main>

      <Dialogs onUpdate={clearCache} />
    </Provider>
  )
}
