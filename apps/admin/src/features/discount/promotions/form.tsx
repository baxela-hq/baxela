import { useEffect, useMemo, useState } from 'react';
import { type z } from 'zod';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { useNavigate, useParams } from '@tanstack/react-router';
import { useQuery } from '@tanstack/react-query';
import { ArrowLeftIcon, InfoIcon, LoaderIcon, SaveIcon, SearchIcon, XIcon } from 'lucide-react';
import { useAppTranslation } from '@/hooks/useAppTranslation';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
  Form,
  FormControl,
  FormDescription,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from '@/components/ui/form';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { Header } from '@/components/layout/header';
import { Main } from '@/components/layout/main';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { UtcDatePicker } from '@/features/discount/coupons/components/utc-date-picker';
import { useCategoryTree } from '@/features/catalog/categories/hooks/use-categories';
import { fetchProducts } from '@/features/catalog/products/api/products.api';
import type { Product } from '@/features/catalog/products/data/schema';
import { pickTranslation, getDefaultCurrency } from '@/shared/lib/locale';
import { HeaderActions } from '@/components/layout/header-actions';
import { Search } from '@/components/search';
import { Locales } from './data/routes';
import { fetchOnePromotion } from './api/promotions.api';
import { useSavePromotion } from './hooks/use-promotion-mutations';
import {
  buildEditValues,
  defaultValues,
  formSchema,
  type PromotionForm,
} from './data/schema';

/** Preview base the before/after example is computed from. */
const PREVIEW_BASE = 100

export function PromotionForm() {
  const navigate = useNavigate()
  const params = useParams({ strict: false })
  const id = typeof params.id === 'string' ? params.id : undefined

  const { tAction, tPageTitle } = useAppTranslation(Locales.SHARED_COMMON)
  const { tLabel, tHelpText, tStatus, tPlaceHolder } = useAppTranslation(
    Locales.PROMOTION
  )

  const entityName = { singular: tLabel('promotion'), plural: tLabel('promotions') }
  const currency = getDefaultCurrency()
  const symbol = currency?.symbol ?? '$'
  const formatMoney = (amount: string) =>
    currency?.is_symbol_right ? `${amount} ${symbol}` : `${symbol}${amount}`

  const categories = useCategoryTree()

  const { data: currentRow } = useQuery({
    queryKey: ['discount-promotions', 'detail', id],
    queryFn: () => fetchOnePromotion(id!),
    enabled: id !== undefined,
    // The edit values are snapshotted once below; no refetch churn needed
    staleTime: Infinity,
  })

  const savePromotion = useSavePromotion()

  const form = useForm<z.input<typeof formSchema>, undefined, PromotionForm>({
    resolver: zodResolver(formSchema),
    defaultValues,
  })

  useEffect(() => {
    if (currentRow) form.reset(buildEditValues(currentRow))
  }, [currentRow]) // eslint-disable-line react-hooks/exhaustive-deps

  const watchedScope = form.watch('scope')
  const watchedType = form.watch('type')
  const watchedValue = form.watch('value')

  // The before/after example — computed with the exact same clamping rules
  // the backend applies, so a fixed value above the base shows "free"
  const preview = useMemo(() => {
    const numeric = Number(watchedValue)
    if (!Number.isFinite(numeric) || numeric <= 0) return null
    const discount =
      watchedType === 'percent'
        ? (PREVIEW_BASE * numeric) / 100
        : numeric
    const applied = Math.min(discount, PREVIEW_BASE)
    return {
      before: (PREVIEW_BASE).toFixed(2),
      after: (PREVIEW_BASE - applied).toFixed(2),
      discount: applied.toFixed(2),
    }
  }, [watchedType, watchedValue])

  // Store-wide activations deserve one explicit confirmation step
  const [confirmAllOpen, setConfirmAllOpen] = useState(false)
  const [pendingSubmit, setPendingSubmit] = useState<z.output<typeof formSchema> | null>(null)

  const onSubmit = (values: z.output<typeof formSchema>) => {
    if (values.scope === 'all' && values.is_active) {
      setPendingSubmit(values)
      setConfirmAllOpen(true)
      return
    }
    void doSubmit(values)
  }

  const doSubmit = async (values: z.output<typeof formSchema>) => {
    const result = await savePromotion.mutateAsync({ id, data: values })
    if (result) {
      navigate({ to: '/discount/promotions' })
    }
  }

  return (
    <div>
      <Header fixed>
        <Search />
        <HeaderActions />
      </Header>

      <Main className='flex flex-1 flex-col gap-4 sm:gap-6'>
        <div className='flex flex-wrap items-end justify-between gap-2'>
          <div>
            <h2 className='text-2xl font-bold tracking-tight'>
              {id
                ? tPageTitle('form.title_edit', {
                    entity: entityName.singular,
                    id,
                  })
                : tPageTitle('form.title_create', { entity: entityName.singular })}
            </h2>
            <p className='text-muted-foreground'>{tPageTitle('form.subtitle')}</p>
          </div>
          <Button
            variant='outline'
            onClick={() => navigate({ to: '/discount/promotions' })}
          >
            <ArrowLeftIcon /> {tAction('cancel')}
          </Button>
        </div>

        <Form {...form}>
          <form
            onSubmit={form.handleSubmit(onSubmit)}
            className='grid gap-6 lg:grid-cols-2'
          >
            <div className='space-y-6'>
              <FormField
                control={form.control}
                name='name'
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>{tLabel('name')}</FormLabel>
                    <FormControl>
                      <Input
                        placeholder={tPlaceHolder('name')}
                        value={field.value ?? ''}
                        onChange={field.onChange}
                      />
                    </FormControl>
                    <FormDescription>{tHelpText('name')}</FormDescription>
                    <FormMessage />
                  </FormItem>
                )}
              />

              <div className='grid grid-cols-2 gap-4'>
                <FormField
                  control={form.control}
                  name='type'
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>{tLabel('type')}</FormLabel>
                      <Select onValueChange={field.onChange} value={field.value}>
                        <FormControl>
                          <SelectTrigger className='w-full'>
                            <SelectValue />
                          </SelectTrigger>
                        </FormControl>
                        <SelectContent>
                          <SelectItem value='percent'>
                            {tStatus('percent')}
                          </SelectItem>
                          <SelectItem value='fixed'>{tStatus('fixed')}</SelectItem>
                        </SelectContent>
                      </Select>
                      <FormDescription>
                        {tHelpText(`type_${watchedType}`)}
                      </FormDescription>
                      <FormMessage />
                    </FormItem>
                  )}
                />

                <FormField
                  control={form.control}
                  name='value'
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>{tLabel('value')}</FormLabel>
                      <FormControl>
                        <Input
                          inputMode='decimal'
                          placeholder={watchedType === 'percent' ? '20.00' : '15.00'}
                          value={field.value ?? ''}
                          onChange={field.onChange}
                        />
                      </FormControl>
                      <FormDescription>
                        {tHelpText(`value_${watchedType}`)}
                      </FormDescription>
                      <FormMessage />
                    </FormItem>
                  )}
                />
              </div>

              <div className='grid grid-cols-2 gap-4'>
                <FormField
                  control={form.control}
                  name='starts_at'
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>{tLabel('starts_at')}</FormLabel>
                      <FormControl>
                        <UtcDatePicker
                          selected={field.value ?? undefined}
                          onSelect={field.onChange}
                          placeholder={tPlaceHolder('date')}
                        />
                      </FormControl>
                      <FormDescription>{tHelpText('starts_at')}</FormDescription>
                      <FormMessage />
                    </FormItem>
                  )}
                />

                <FormField
                  control={form.control}
                  name='ends_at'
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>{tLabel('ends_at')}</FormLabel>
                      <FormControl>
                        <UtcDatePicker
                          selected={field.value ?? undefined}
                          onSelect={field.onChange}
                          placeholder={tPlaceHolder('date')}
                        />
                      </FormControl>
                      <FormDescription>{tHelpText('ends_at')}</FormDescription>
                    </FormItem>
                  )}
                />
              </div>

              <div className='grid grid-cols-2 gap-4'>
                <FormField
                  control={form.control}
                  name='priority'
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>{tLabel('priority')}</FormLabel>
                      <FormControl>
                        <Input
                          type='number'
                          min={0}
                          value={field.value ?? 0}
                          onChange={(e) => field.onChange(Number(e.target.value))}
                        />
                      </FormControl>
                      <FormDescription>{tHelpText('priority')}</FormDescription>
                      <FormMessage />
                    </FormItem>
                  )}
                />

                <FormField
                  control={form.control}
                  name='is_active'
                  render={({ field }) => (
                    <FormItem className='flex flex-row items-center gap-2 pt-7'>
                      <FormControl>
                        <Checkbox
                          checked={field.value}
                          onCheckedChange={(checked) => field.onChange(!!checked)}
                        />
                      </FormControl>
                      <FormLabel className='!mt-0 text-sm font-normal'>
                        {tLabel('is_active')}
                      </FormLabel>
                      <FormDescription>{tHelpText('is_active')}</FormDescription>
                    </FormItem>
                  )}
                />
              </div>

              {preview && (
                <div className='rounded-md border bg-muted/40 p-4 text-sm'>
                  <div className='mb-1 font-medium'>{tLabel('preview')}</div>
                  <div className='flex items-center gap-2'>
                    <span className='text-muted-foreground line-through'>
                      {formatMoney(preview.before)}
                    </span>
                    <span className='font-semibold'>
                      {formatMoney(preview.after)}
                    </span>
                    <span className='text-muted-foreground'>
                      (−{formatMoney(preview.discount)})
                    </span>
                  </div>
                  <p className='mt-1 text-xs text-muted-foreground'>
                    {tHelpText('preview_note', { base: PREVIEW_BASE.toFixed(2) })}
                  </p>
                </div>
              )}
            </div>

            <div className='space-y-6'>
              <FormField
                control={form.control}
                name='scope'
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>{tLabel('scope')}</FormLabel>
                    <FormControl>
                      <RadioGroup
                        onValueChange={field.onChange}
                        value={field.value}
                        className='gap-2'
                      >
                        <Label className='flex items-center gap-2 rounded-md border p-3 font-normal'>
                          <RadioGroupItem value='all' />
                          {tStatus('scope_all')}
                        </Label>
                        <Label className='flex items-center gap-2 rounded-md border p-3 font-normal'>
                          <RadioGroupItem value='specific' />
                          {tStatus('scope_specific')}
                        </Label>
                      </RadioGroup>
                    </FormControl>
                    <FormDescription>{tHelpText('scope')}</FormDescription>
                    <FormMessage />
                  </FormItem>
                )}
              />

              {watchedScope === 'specific' && (
                <>
                  <FormField
                    control={form.control}
                    name='category_ids'
                    render={({ field }) => (
                      <FormItem>
                        <FormLabel>{tLabel('category_ids')}</FormLabel>
                        <div className='max-h-52 space-y-1 overflow-y-auto rounded-md border p-3'>
                          {categories.length === 0 && (
                            <p className='text-sm text-muted-foreground'>
                              {tHelpText('no_categories')}
                            </p>
                          )}
                          {categories.map((cat) => (
                            <label
                              key={cat.id}
                              className='flex items-center gap-2 text-sm'
                              style={{ paddingInlineStart: `${cat.depth * 1.25}rem` }}
                            >
                              <Checkbox
                                checked={field.value.includes(cat.id)}
                                onCheckedChange={(checked) =>
                                  checked
                                    ? field.onChange([...field.value, cat.id])
                                    : field.onChange(
                                        field.value.filter((v) => v !== cat.id)
                                      )
                                }
                              />
                              {cat.title}
                            </label>
                          ))}
                        </div>
                        <FormDescription>{tHelpText('category_ids')}</FormDescription>
                        <FormMessage />
                      </FormItem>
                    )}
                  />

                  <FormField
                    control={form.control}
                    name='product_ids'
                    render={({ field }) => (
                      <FormItem>
                        <FormLabel>{tLabel('product_ids')}</FormLabel>
                        <ProductPicker
                          selectedIds={field.value}
                          onChange={field.onChange}
                          label={tLabel('product_ids')}
                          placeholder={tPlaceHolder('product_search')}
                          noResults={tHelpText('no_products')}
                        />
                        <FormDescription>{tHelpText('product_ids')}</FormDescription>
                        <FormMessage />
                      </FormItem>
                    )}
                  />
                </>
              )}

              <div className='flex items-start gap-2 rounded-md border border-dashed p-4 text-sm text-muted-foreground'>
                <InfoIcon className='mt-0.5 size-4 shrink-0' />
                <span>{tHelpText('precedence')}</span>
              </div>

              {form.formState.isSubmitting ? (
                <Button disabled>
                  <LoaderIcon className='animate-spin' /> {tAction('submit')}
                </Button>
              ) : (
                <Button type='submit'>{tAction('save')} <SaveIcon /></Button>
              )}
            </div>
          </form>
        </Form>
      </Main>

      <ConfirmDialog
        open={confirmAllOpen}
        onOpenChange={setConfirmAllOpen}
        handleConfirm={() => {
          setConfirmAllOpen(false)
          if (pendingSubmit) void doSubmit(pendingSubmit)
        }}
        title={tLabel('confirm_all_title')}
        desc={tHelpText('confirm_all')}
        destructive
      />
    </div>
  )
}

/**
 * Searchable async multi-select over the admin products endpoint: type to
 * search (title filter, debounced), click a result to add it, chips carry
 * the selection. Existing selections survive re-searches untouched.
 */
function ProductPicker({
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
    queryKey: ['discount-promotions', 'product-picker', debounced],
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
    queryKey: ['discount-promotions', 'product-chip', productId],
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
