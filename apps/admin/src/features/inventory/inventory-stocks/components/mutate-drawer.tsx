import { useEffect, useState } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { useQuery } from '@tanstack/react-query'
import { useAppTranslation } from '@/hooks/useAppTranslation';
import { Button } from '@/components/ui/button';
import { Form, FormControl, FormDescription, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Sheet, SheetClose, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import {
  fetchOneProduct,
} from '@/features/catalog/products/api/products.api'
import {
  productLabel,
  useProductOptions,
} from '@/features/catalog/products/hooks/use-products'
import { Locales } from '../data/routes'
import {
  formSchema,
  defaultValues,
  type InventoryStockForm,
  type InventoryStock,
  buildEditValues,
} from '../data/schema'
import { useSaveInventoryStock } from '../hooks/use-inventory-stock-mutations'

/** Variants of one product (product → variant cascade on the drawer). */
function useProductVariants(productId: number) {
  return useQuery({
    queryKey: ['products', 'single', productId],
    queryFn: () => fetchOneProduct(productId.toString()),
    enabled: productId > 0,
  })
}

const variantLabel = (variant: { id: number; sku?: string | null }) =>
  `#${variant.id}${variant.sku ? ` — ${variant.sku}` : ''}`
type MutateDrawerProps = {
  open: boolean
  onOpenChange: (open: boolean) => void
  currentRow?: InventoryStock
}

export function MutateDrawer({
  open,
  onOpenChange,
  currentRow,
}: MutateDrawerProps) {
  const isUpdate = !!currentRow
  const { tAction, tPageTitle, tPlaceHolder } = useAppTranslation(Locales.SHARED_COMMON)
  const { tLabel, tHelpText } = useAppTranslation(Locales.INVENTORY_STOCK)

  const entityName = {
    singular: tLabel("inventory-stock"),
    plural: tLabel("inventory-stocks")
  };

  const products = useProductOptions()

  // The API form only carries variant_id + quantity; the selected product is
  // drawer-local UI state narrowing the variant select. The dialogs host
  // remounts this drawer per row (key), so seeding from props is sufficient.
  const [productId, setProductId] = useState<number>(
    currentRow?.variant?.product?.id ?? 0
  )

  const { data: productDetail } = useProductVariants(productId)
  // the products form schema omits the variant id, but the API returns it —
  // narrow to the picker shape
  const variants = productDetail?.variants as
    | Array<{ id: number; sku?: string | null }>
    | undefined

  const form = useForm<InventoryStockForm>({
    resolver: zodResolver(formSchema),
    defaultValues: { ...defaultValues },
  })

  useEffect(() => {
    form.reset(buildEditValues(currentRow))
  }, [currentRow]) // eslint-disable-line react-hooks/exhaustive-deps

  const saveInventoryStock = useSaveInventoryStock()

  const onSubmit = (data: InventoryStockForm) => {
    saveInventoryStock.mutate(
      { id: currentRow?.id?.toString(), data },
      {
        onSuccess: () => {
          onOpenChange(false)
          form.reset()
          setProductId(0)
        },
      }
    )
  }

  return (
    <Sheet
      open={open}
      onOpenChange={(v) => {
        onOpenChange(v)
        form.reset()
        setProductId(0)
      }}
    >
      <SheetContent className='flex flex-col'>
        <SheetHeader className='text-start'>
          <SheetTitle>
            {
              isUpdate ?
                tPageTitle("form.title_edit", {entity: entityName.singular, id: currentRow?.id?.toString()}) :
                tPageTitle("form.title_create", {entity: entityName.singular})
            }
          </SheetTitle>
          <SheetDescription>
            {tPageTitle("form.subtitle")}
          </SheetDescription>
        </SheetHeader>
        <Form {...form}>
          <form
            id='inventory-stocks-form'
            onSubmit={form.handleSubmit(onSubmit)}
            className='flex-1 space-y-6 overflow-y-auto px-4'
          >
            <FormField
              control={form.control}
              name='variant_id'
              render={() => (
                <FormItem>
                  <FormLabel>{tLabel('product')}</FormLabel>
                  <Select
                    onValueChange={(value) => {
                      setProductId(Number(value))
                      // switching the product invalidates the picked variant
                      form.setValue('variant_id', 0)
                    }}
                    value={productId ? String(productId) : ''}
                  >
                    <FormControl>
                      <SelectTrigger className="w-full">
                        <SelectValue placeholder={tPlaceHolder('select')} />
                      </SelectTrigger>
                    </FormControl>
                    <SelectContent>
                      {products.map((product) => (
                        <SelectItem key={product.id} value={String(product.id)}>
                          {productLabel(product)}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                  <FormDescription>
                    {tHelpText('product')}
                  </FormDescription>
                </FormItem>
              )}
            />

            <FormField
              control={form.control}
              name='variant_id'
              render={({ field }) => (
                <FormItem>
                  <FormLabel>{tLabel('variant')}</FormLabel>
                  <Select
                    onValueChange={(value) => field.onChange(Number(value))}
                    value={field.value ? String(field.value) : ''}
                    disabled={productId === 0}
                  >
                    <FormControl>
                      <SelectTrigger className="w-full">
                        <SelectValue
                          placeholder={
                            productId === 0
                              ? tPlaceHolder('select_product_first')
                              : tPlaceHolder('select')
                          }
                        />
                      </SelectTrigger>
                    </FormControl>
                    <SelectContent>
                      {variants?.map((variant) => (
                        <SelectItem key={variant.id} value={String(variant.id)}>
                          {variantLabel(variant)}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                  <FormMessage />
                  <FormDescription>
                    {tHelpText('variant')}
                  </FormDescription>
                </FormItem>
              )}
            />

            <FormField
              control={form.control}
              name='quantity'
              render={({ field }) => (
                <FormItem>
                  <FormLabel>{tLabel('quantity')}</FormLabel>
                  <FormControl>
                    <Input
                      type="number"
                      min="1"
                      step="1"
                      {...field}
                      placeholder={tPlaceHolder('input')}
                    />
                  </FormControl>
                  <FormMessage />
                  <FormDescription>
                    {tHelpText('quantity')}
                  </FormDescription>
                </FormItem>
              )}
            />
          </form>
        </Form>
        <SheetFooter className='gap-2'>
          <Button form='inventory-stocks-form' type='submit'>
            {tAction(isUpdate ? 'save' : 'submit')}
          </Button>
          <SheetClose asChild>
            <Button variant='outline'>{tAction('close')}</Button>
          </SheetClose>
        </SheetFooter>
      </SheetContent>
    </Sheet>
  )
}
