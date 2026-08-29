import { useEffect, useState } from 'react';
import { type z } from 'zod';
import { useFieldArray, useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { useNavigate } from '@tanstack/react-router';
import { useLanguages } from '@/features/core/languages/hooks/use-languages'
import { parseAndToastError } from '@/shared/lib/utils';
import { type ApiError } from '@/shared/lib/api-error';
import { ListCheckIcon, LoaderIcon, SaveIcon, ArrowLeftIcon, XIcon, InfoIcon, ImagePlusIcon, ChevronLeftIcon, ChevronRightIcon, StarIcon, ExternalLinkIcon } from 'lucide-react';
import { toast } from 'sonner';
import { useAppTranslation } from '@/hooks/useAppTranslation';
import { Button } from '@/components/ui/button';
import { Checkbox } from "@/components/ui/checkbox";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs"
import { Tooltip, TooltipContent, TooltipTrigger } from "@/components/ui/tooltip"
import { Form, FormControl, FormField, FormItem, FormLabel, FormMessage, FormDescription } from '@/components/ui/form';
import { Input } from '@/components/ui/input';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Textarea } from '@/components/ui/textarea.tsx';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { ConfigDrawer } from '@/components/config-drawer';
import { InputGroup, InputGroupAddon, InputGroupInput } from "@/components/ui/input-group";
import { Header } from '@/components/layout/header';
import { Main } from '@/components/layout/main';
import { ProfileDropdown } from '@/components/profile-dropdown';
import { Search } from '@/components/search';
import { ThemeSwitch } from '@/components/theme-switch';
import { TiptapEditor } from '@/components/tiptap/tiptap-editor';
import { cn } from '@/lib/utils';
import { MediaPickerDialog } from '@/features/media/components/media-picker-dialog';
import { type MediaItem, getMediaUrl } from '@/features/media/data/schema';
import { FeatureRoutes, Locales } from './data/routes';
import { fetchOneProduct } from './api/products.api';
import { useCategoryTree } from '@/features/catalog/categories/hooks/use-categories';
import { useOptionsAll } from '@/features/catalog/options/hooks/use-options';
import { useSaveProduct } from './hooks/use-product-mutations';
import { Provider } from './components/provider.tsx';
import { formSchema, STATUSES, TYPES, IMAGE_COLLECTION, type Product, buildDefaultValues, buildEditValues } from './data/schema';
import { ProductAttributesTab } from './components/attributes-tab';
import { ProductShippingTab } from './components/shipping-tab';
import { getDefaultCurrency, pickTranslation } from '@/shared/lib/locale.ts';
import type { Option } from '@/features/catalog/options/data/schema';
import { fetchOptionValues } from '@/features/catalog/option-values/api/option-values.api';
import type { OptionValue } from '@/features/catalog/option-values/data/schema';
import { generateVariants, type MatrixOption } from './data/variant-matrix';




export function ProductForm() {
  const [isLoading, setIsLoading] = useState(false)
  const [id, setId] = useState<number | null>(null)
  const navigate = useNavigate()
  const [currentRow, setCurrentRow] = useState<Product | null>(null)
  const categories = useCategoryTree()
  const options = useOptionsAll()
  const [matrix, setMatrix] = useState<MatrixOption[]>([]);
  const [addOptionValue, setAddOptionValue] = useState('');
  const [activeTab, setActiveTab] = useState('general');
  const { tAction, tPageTitle, tPlaceHolder, tMessage } = useAppTranslation(Locales.SHARED_COMMON)
  const { tLabel, tTooltip, tStatus, tHelpText } = useAppTranslation(Locales.PRODUCT)

  const entityName = {
    singular: tLabel("product"),
    plural: tLabel("products")
  };

  const { data: languages, isLoading: languagesIsLoading } = useLanguages();

  const languagesSafe = languages ?? []

  const saveProduct = useSaveProduct()

  const [mediaPickerOpen, setMediaPickerOpen] = useState(false)
  const [previewImage, setPreviewImage] = useState<string | null>(null)

  const form = useForm<z.input<typeof formSchema>, undefined, z.output<typeof formSchema>>({
    resolver: zodResolver(formSchema),
    defaultValues: buildDefaultValues(languagesSafe),
  });

  const { fields, remove, replace } = useFieldArray({
    control: form.control,
    name: "variants",
  });

  const {
    fields: imageFields,
    append: appendImages,
    remove: removeImage,
    move: moveImage,
  } = useFieldArray({
    control: form.control,
    name: "images",
  });

  const watchedType = form.watch('type');
  const watchedTranslations = form.watch('translations');
  const hasTitle = watchedTranslations?.some((t) => t.title?.trim().length > 0) ?? false;
  const availableOptions = options.filter((o) => !matrix.some((m) => m.option.id === o.id));
  const currency = getDefaultCurrency();

  useEffect(() => {
    if (languagesSafe.length > 0) {
      form.reset(buildEditValues(languagesSafe, currentRow ?? undefined))
    }
  }, [languages, currentRow]) // eslint-disable-line react-hooks/exhaustive-deps

  useEffect(() => {
    const currentPath = window.location.pathname
    const match = currentPath.match(/^\/catalog\/products\/(\d+)\/edit$/);


    if (match && match[1]) {
      const intId = Number(match[1])
      setId(intId)
      const fetchData = async () => {
        getItem(intId)
      }
      fetchData()
    }
  }, [])

  useEffect(() => {
    if (!currentRow || currentRow.type !== 'variable' || options.length === 0) return;

    const optionIds = new Set<number>();
    currentRow.variants.forEach((variant) => {
      (variant.optionValues ?? []).forEach((ov) => optionIds.add(ov.option_id));
    });
    const usedOptions = options.filter((o) => optionIds.has(o.id));
    if (usedOptions.length === 0) return;

    let cancelled = false;
    (async () => {
      try {
        const rebuilt: MatrixOption[] = [];
        for (const option of usedOptions) {
          const res = await fetchOptionValues(String(option.id), { per_page: 1000 });
          const values = res.data.map((v) => ({ id: v.id, title: getValueTitle(v) }));
          const selectedIds = new Set<number>();
          currentRow.variants.forEach((variant) => {
            (variant.optionValues ?? []).forEach((ov) => {
              if (ov.option_id === option.id) selectedIds.add(ov.id);
            });
          });
          rebuilt.push({ option, values, selectedIds: Array.from(selectedIds) });
        }
        if (!cancelled) setMatrix(rebuilt);
      } catch (error) {
        if (!cancelled) parseAndToastError(error as ApiError);
      }
    })();
    return () => {
      cancelled = true;
    };
  }, [currentRow, options])

  async function getItem(id: number) {
    setIsLoading(true)
    const result = await fetchOneProduct(id.toString());
    setCurrentRow(result)
    setIsLoading(false)
  }

  const tabForField = (field: string): string => {
    if (field === 'type' || field.startsWith('variants')) return 'variants';
    if (field === 'images' || field.startsWith('images')) return 'images';
    if (field === 'categories') return 'categories';
    if (field.startsWith('attribute_values')) return 'attributes';
    if (field === 'shipping' || field.startsWith('shipping')) return 'shipping';
    if (field === 'status' || field === 'is_published') return 'publish';
    return 'general';
  }

  const handleSelectImages = (items: MediaItem[]) => {
    const existingIds = new Set(form.getValues('images').map((image) => image.media_id));
    const nextPosition = form.getValues('images').length;
    const additions = items
      .filter((item) => !existingIds.has(item.id))
      .map((item, index) => ({
        position: nextPosition + index + 1,
        collection: IMAGE_COLLECTION,
        media_id: item.id,
        url: getMediaUrl(item) ?? '',
      }));
    if (additions.length > 0) appendImages(additions);
  };

  const handleMoveImage = (index: number, direction: -1 | 1) => {
    const target = index + direction;
    if (target < 0 || target >= imageFields.length) return;
    moveImage(index, target);
  };

  async function handleSubmit(values: z.infer<typeof formSchema>) {
    setIsLoading(true)
    try {
      const request = await saveProduct.mutateAsync({ id, values })
      if (id) {
        getItem(id)
      } else {
        const redirectUrl = FeatureRoutes.EDIT.replace('$id', request.id.toString())
        setId(request.id)
        navigate({ to: redirectUrl })
      }
    } catch {
      // errors are toasted by the mutation hook's onError
    } finally {
      setIsLoading(false)
    }
  }


  // Function to handle the toggle of the 'is_default' checkbox
  const handleDefaultToggle = (indexToToggle: number, isChecked: boolean) => {
    const currentItems = form.getValues('variants');
    const updatedItems = currentItems.map((item, index) => {
      if (index === indexToToggle) {
        // If this is the item being toggled, set its value based on isChecked
        return { ...item, is_default: isChecked };
      } else if (isChecked) {
        // If another item was checked, uncheck all others
        return { ...item, is_default: false };
      }
      // Otherwise, keep the item's current is_default state
      return item;
    });
    form.setValue('variants', updatedItems);
  };

  const handleTypeChange = (value: string) => {
    if (value === 'simple') {
      replace([{ sku: '', price: '', quantity: 0, is_default: true, currency_id: getDefaultCurrency()?.id ?? null, option_value_ids: [] }]);
    }
  };

  const getOptionTitle = (option: Option) => {
    return pickTranslation(option.translations)?.title ?? '';
  };

  const getValueTitle = (value: OptionValue) => {
    return pickTranslation(value.translations)?.title ?? '';
  };

  const handleAddOption = async (optionId: string) => {
    const id = Number(optionId);
    if (matrix.some((m) => m.option.id === id)) return;
    const option = options.find((o) => o.id === id);
    if (!option) return;
    try {
      const res = await fetchOptionValues(optionId, { per_page: 1000 });
      const values = res.data.map((v) => ({ id: v.id, title: getValueTitle(v) }));
      setMatrix((prev) => [...prev, { option, values, selectedIds: [] }]);
    } catch (error) {
      parseAndToastError(error as ApiError)
    }
    setAddOptionValue('');
  };

  const handleRemoveOption = (optionId: number) => {
    setMatrix((prev) => prev.filter((m) => m.option.id !== optionId));
  };

  const handleToggleValue = (optionId: number, valueId: number, checked: boolean) => {
    setMatrix((prev) =>
      prev.map((m) => {
        if (m.option.id !== optionId) return m;
        const selectedIds = checked
          ? Array.from(new Set([...m.selectedIds, valueId]))
          : m.selectedIds.filter((v) => v !== valueId);
        return { ...m, selectedIds };
      })
    );
  };

  const getProductTitle = () => {
    const translations = form.getValues('translations');
    return pickTranslation(translations ?? [])?.title?.trim() ?? '';
  };

  const handleGenerate = () => {
    const active = matrix.filter((m) => m.selectedIds.length > 0);
    if (active.length === 0) {
      toast.warning(tLabel('select_values_before_generate'));
      return;
    }
    replace(generateVariants(getProductTitle(), matrix, getDefaultCurrency()?.id ?? null));
  };

  const renderVariantEditor = (key: string, index: number, single: boolean) => (
    <tr key={key} className="border-b last:border-0">
      <td className="px-4 py-3 align-middle">
        <FormField
          control={form.control}
          name={`variants.${index}.sku`}
          render={({ field }) => (
            <FormItem className="m-0">
              <FormControl>
                <Input
                  placeholder={tPlaceHolder('input')}
                  {...field}
                  value={field.value ?? ''}
                  className="min-w-44"
                />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />
      </td>
      <td className="px-4 py-3 align-middle">
        <FormField
          control={form.control}
          name={`variants.${index}.price`}
          render={({ field }) => (
            <FormItem className="m-0">
              <FormControl>
                <InputGroup>
                  <InputGroupInput
                    step="0.01"
                    placeholder="99.99"
                    {...field}
                    value={field.value ?? ''}
                    onChange={(e) => field.onChange(e.target.value)}
                    className="min-w-28"
                  />
                  <InputGroupAddon align={currency?.is_symbol_right ? 'inline-end' : 'inline-start'}>
                    {currency?.symbol ?? '$'}
                  </InputGroupAddon>
                </InputGroup>
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />
      </td>
      <td className="px-4 py-3 align-middle">
        <FormField
          control={form.control}
          name={`variants.${index}.quantity`}
          render={({ field }) => (
            <FormItem className="m-0">
              <FormControl>
                <Input
                  type="number"
                  step="1"
                  min={0}
                  placeholder="0"
                  {...field}
                  value={field.value ?? 0}
                  onChange={(e) => field.onChange(e.target.value === '' ? 0 : Number(e.target.value))}
                  className="w-24"
                />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />
      </td>
      <td className="px-4 py-3 align-middle">
        <FormField
          control={form.control}
          name={`variants.${index}.is_default`}
          render={({ field }) => (
            <FormItem className="m-0">
              <FormControl>
                <Checkbox
                  checked={field.value}
                  onCheckedChange={(checked) => handleDefaultToggle(index, !!checked)}
                />
              </FormControl>
            </FormItem>
          )}
        />
      </td>
      <td className="px-4 py-3 text-end align-middle">
        {!single && (
          <Button type="button" variant="ghost" size="sm" onClick={() => remove(index)}>
            <XIcon size={16} />
          </Button>
        )}
      </td>
    </tr>
  );

  const renderVariantTable = (single: boolean) => (
    <div className="overflow-hidden rounded-md border">
      <table className="w-full text-sm">
        <thead>
          <tr className="bg-muted/50 text-muted-foreground">
            <th className="px-4 py-2 text-start font-medium">{tLabel('sku')}</th>
            <th className="px-4 py-2 text-start font-medium">{tLabel('price')}</th>
            <th className="px-4 py-2 text-start font-medium">{tLabel('quantity')}</th>
            <th className="px-4 py-2 text-start font-medium">
              <Tooltip>
                <TooltipTrigger asChild>
                  <span className="ml-1 cursor-pointer text-muted-foreground">
                    {tLabel('is_default')}
                    <InfoIcon size={14} className="ml-1 inline-block" />
                  </span>
                </TooltipTrigger>
                <TooltipContent>
                  <p>{tHelpText('is_default')}</p>
                </TooltipContent>
              </Tooltip>
            </th>
            <th className="px-4 py-2" />
          </tr>
        </thead>
        <tbody>
          {fields.map((item, index) => renderVariantEditor(item.id, index, single))}
        </tbody>
      </table>
    </div>
  );

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
              {
                id ?
                  tPageTitle("form.title_edit", { entity: entityName.singular, id: id.toString() }) :
                  tPageTitle("form.title_create", { entity: entityName.singular })
              }
            </h2>
            <p className='text-muted-foreground'>
              {tPageTitle("form.subtitle")}
            </p>
          </div>
          <div className='flex gap-2'>
            <Button
              variant='outline'
              className='space-x-1'
              onClick={() => navigate({ to: FeatureRoutes.LIST })}
            >
              <ArrowLeftIcon size={16} />
              <span>{entityName.plural}</span>
              <ListCheckIcon size={18} />
            </Button>
            <Button type="submit" form="product-form" className='btn' disabled={isLoading}>
              <LoaderIcon className={isLoading ? 'animate-spin' : 'hidden'} />
              <SaveIcon />
              {tAction('submit')}
            </Button>

          </div>
        </div>

        <Form {...form}>
          <form
            id="product-form"
            onSubmit={form.handleSubmit(handleSubmit, (errors) => {
              const firstField = Object.keys(errors)[0]
              if (firstField) setActiveTab(tabForField(firstField))
              toast.error(tMessage('error.validation'))
            })}
            className={isLoading ? 'hidden' : 'space-y-8'}
          >

            <Tabs value={activeTab} onValueChange={setActiveTab}>
              <TabsList>
                <TabsTrigger value="general">{tLabel('general')}</TabsTrigger>
                <TabsTrigger value="variants" disabled={!hasTitle}>{tLabel('variants')}</TabsTrigger>
                <TabsTrigger value="images">{tLabel('images')}</TabsTrigger>
                <TabsTrigger value="categories">{tLabel('categories')}</TabsTrigger>
                <TabsTrigger value="attributes">{tLabel('attributes')}</TabsTrigger>
                <TabsTrigger value="shipping">{tLabel('shipping')}</TabsTrigger>
                <TabsTrigger value="publish">{tLabel('publish')}</TabsTrigger>
              </TabsList>
              <TabsContent value="general" className="pt-5 pb-5">

                {languagesIsLoading && (
                  <div className='py-8 text-center text-sm text-muted-foreground'>
                    {tMessage('info.loading')}
                  </div>
                )}

                {!languagesIsLoading && languagesSafe.length > 0 && (
                  <Tabs defaultValue={languagesSafe[0]?.code} className='w-full'>
                    <TabsList className='w-full'>
                      {languagesSafe.map((language) => (
                        <TabsTrigger key={language.code} value={language.code}>
                          {language.name}
                        </TabsTrigger>
                      ))}
                    </TabsList>

                    {languagesSafe.map((language, index) => (
                      <TabsContent key={language.code} value={language.code} className='space-y-4'>
                        {/* Title Name Field */}
                        <FormField
                          control={form.control}
                          name={`translations.${index}.title`}
                          render={({ field }) => (
                            <FormItem className='grid gap-2 mb-5'>
                              <FormLabel htmlFor={`title-${language.code}`}>{tLabel('title')}</FormLabel>
                              <FormControl>
                                <Input
                                  id={`title-${language.code}`}
                                  placeholder={tPlaceHolder('input')}
                                  {...field}
                                  value={field.value ?? ''}
                                />
                              </FormControl>
                              <FormMessage />
                            </FormItem>
                          )}
                        />

                        {/* Slug Name Field */}
                        <FormField
                          control={form.control}
                          name={`translations.${index}.slug`}
                          render={({ field }) => (
                            <FormItem className='grid gap-2 mb-5'>
                              <FormLabel htmlFor={`slug-${language.code}`}>{tLabel('slug')}</FormLabel>
                              <FormControl>
                                <Input
                                  id={`slug-${language.code}`}
                                  placeholder={tPlaceHolder('input')}
                                  {...field}
                                  value={field.value ?? ''}
                                />
                              </FormControl>
                              <FormMessage />
                              <FormDescription>
                                {import.meta.env.VITE_STORE_FRONT_URL+'/product/'+language.code+'/'+(field.value ?? '')}
                              </FormDescription>
                            </FormItem>
                          )}
                        />

                        {/* Description Field */}
                        <FormField
                          control={form.control}
                          name={`translations.${index}.description`}
                          render={({ field }) => (
                            <FormItem className='grid gap-2 mb-5'>
                              <FormLabel htmlFor={`description-${language.code}`}>{tLabel('description')}</FormLabel>
                              <FormControl>
                                <Textarea
                                  id={`description-${language.code}`}
                                  placeholder={tPlaceHolder('textarea')}
                                  {...field}
                                  value={field.value ?? ''}
                                />
                              </FormControl>
                              <FormMessage />
                            </FormItem>
                          )}
                        />

                        {/* Content Field */}
                        <FormField
                          control={form.control}
                          name={`translations.${index}.content`}
                          render={({ field }) => (
                            <FormItem className='grid gap-2 mb-5'>
                              <FormLabel htmlFor={`content-${language.code}`}>{tLabel('content')}</FormLabel>
                              <FormControl>
                                <TiptapEditor
                                  initialContent={field.value}
                                  onUpdate={({ html }) => field.onChange(html)}
                                />
                              </FormControl>
                              <FormMessage />
                            </FormItem>
                          )}
                        />
                      </TabsContent>
                    ))}
                  </Tabs>
                )}

              </TabsContent>
              <TabsContent value="variants" className="pt-5 pb-5">

                <div className="space-y-6">
                  {/* Type Name Field */}
                  <FormField
                    control={form.control}
                    name='type'
                    render={({ field }) => (
                      <FormItem>
                        <FormLabel>{tLabel('type')}</FormLabel>
                        <Select
                          onValueChange={(value) => {
                            field.onChange(value);
                            handleTypeChange(value);
                          }}
                          value={field.value || undefined}
                          defaultValue={field.value}
                        >
                          <FormControl className='w-full'>
                            <SelectTrigger className="w-full">
                              <SelectValue placeholder={tPlaceHolder('select')} />
                            </SelectTrigger>
                          </FormControl>
                          <SelectContent>
                            {TYPES.map((item: string) => (
                              <SelectItem key={item} value={item}>
                                {tStatus(`type.${item}`)}
                              </SelectItem>
                            ))}
                          </SelectContent>
                        </Select>
                        <FormMessage />
                      </FormItem>
                    )}
                  />

                  {watchedType === 'simple' && fields.length > 0 && renderVariantTable(true)}

                  {watchedType === 'variable' && (
                    <>
                      {/* Option matrix */}
                      <div className="space-y-4">
                        <div className="flex flex-wrap items-center gap-2">
                          <h3 className="text-sm font-medium">{tLabel('options')}</h3>
                          <div className="w-56">
                            <Select
                              value={addOptionValue || undefined}
                              onValueChange={(value) => handleAddOption(value)}
                            >
                              <SelectTrigger className="w-full">
                                <SelectValue placeholder={tLabel('add_option')} />
                              </SelectTrigger>
                              <SelectContent>
                                {availableOptions.length === 0 && (
                                  <div className="px-2 py-1.5 text-sm text-muted-foreground">
                                    {tLabel('no_more_options')}
                                  </div>
                                )}
                                {availableOptions.map((option) => (
                                  <SelectItem key={option.id} value={String(option.id)}>
                                    {getOptionTitle(option)}
                                  </SelectItem>
                                ))}
                              </SelectContent>
                            </Select>
                          </div>
                        </div>

                        {matrix.length === 0 && (
                          <p className="text-sm text-muted-foreground">{tLabel('no_options_added')}</p>
                        )}

                        {matrix.map((m) => (
                          <div key={m.option.id} className="rounded-md border p-4">
                            <div className="flex items-center justify-between gap-2">
                              <h4 className="text-sm font-semibold">{getOptionTitle(m.option)}</h4>
                              <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                onClick={() => handleRemoveOption(m.option.id)}
                              >
                                {tAction('remove')}
                              </Button>
                            </div>

                            <div className="mt-3 max-h-48 space-y-2 overflow-y-auto">
                              {m.values.map((v) => (
                                <div key={v.id} className="flex items-center gap-2">
                                  <Checkbox
                                    id={`value-${m.option.id}-${v.id}`}
                                    checked={m.selectedIds.includes(v.id)}
                                    onCheckedChange={(checked) => handleToggleValue(m.option.id, v.id, !!checked)}
                                  />
                                  <label
                                    htmlFor={`value-${m.option.id}-${v.id}`}
                                    className="text-sm cursor-pointer"
                                  >
                                    {v.title}
                                  </label>
                                </div>
                              ))}
                            </div>
                          </div>
                        ))}

                        {matrix.length > 0 && (
                          <Button type="button" onClick={handleGenerate}>
                            {tLabel('generate')}
                          </Button>
                        )}
                      </div>

                      {/* Generated variants */}
                      {fields.length > 0 && (
                        <div>
                          <h3 className="mb-3 text-sm font-medium">{tLabel('variants')}</h3>
                          {renderVariantTable(false)}
                        </div>
                      )}
                    </>
                  )}
                </div>
              </TabsContent>
              <TabsContent value="images" className="pt-5 pb-5">
                <FormField
                  control={form.control}
                  name="images"
                  render={() => (
                    <FormItem>
                      <FormLabel>{tLabel('images')}</FormLabel>
                      <div className="flex flex-wrap items-start gap-4">
                        <button
                          type="button"
                          onClick={() => setMediaPickerOpen(true)}
                          className="flex h-32 w-32 flex-col items-center justify-center gap-2 rounded-md border border-dashed text-muted-foreground transition-colors hover:border-primary hover:bg-muted/50 hover:text-foreground"
                        >
                          <ImagePlusIcon size={24} />
                          <span className="px-2 text-center text-xs font-medium">
                            {tLabel('add_images')}
                          </span>
                        </button>

                        {imageFields.map((image, index) => (
                          <div key={image.id} className="space-y-1.5">
                            <button
                              type="button"
                              onClick={() => setPreviewImage(image.url)}
                              title={tLabel('image_preview')}
                              aria-label={tLabel('image_preview')}
                              className={cn(
                                'relative h-32 w-32 cursor-zoom-in overflow-hidden rounded-md border',
                                index === 0
                                  ? 'border-2 border-primary dark:border-amber-400'
                                  : 'border-border hover:border-primary/50'
                              )}
                            >
                              <img
                                src={image.url}
                                alt={image.url.split('/').pop() ?? ''}
                                className="h-full w-full object-cover"
                              />
                              {index === 0 && (
                                <div className='absolute inset-x-0 bottom-0 flex items-center justify-center gap-1.5 bg-primary/95 py-1 text-primary-foreground'>
                                  <StarIcon size={12} className='fill-current shrink-0' />
                                  <span className='text-xs font-semibold'>
                                    {tLabel('featured_image')}
                                  </span>
                                </div>
                              )}
                            </button>
                            <div className="flex items-center justify-center gap-1">
                              <Button
                                type="button"
                                variant="outline"
                                size="icon"
                                className="h-7 w-7"
                                disabled={index === 0}
                                onClick={() => handleMoveImage(index, -1)}
                                title={tLabel('move_left')}
                                aria-label={tLabel('move_left')}
                              >
                                <ChevronLeftIcon size={14} className="rtl:rotate-180" />
                              </Button>
                              <Button
                                type="button"
                                variant="outline"
                                size="icon"
                                className="h-7 w-7 text-destructive hover:text-destructive"
                                onClick={() => removeImage(index)}
                                title={tAction('remove')}
                                aria-label={tAction('remove')}
                              >
                                <XIcon size={14} />
                              </Button>
                              <Button
                                type="button"
                                variant="outline"
                                size="icon"
                                className="h-7 w-7"
                                disabled={index === imageFields.length - 1}
                                onClick={() => handleMoveImage(index, 1)}
                                title={tLabel('move_right')}
                                aria-label={tLabel('move_right')}
                              >
                                <ChevronRightIcon size={14} className="rtl:rotate-180" />
                              </Button>
                            </div>
                          </div>
                        ))}
                      </div>
                      <FormDescription>
                        {tHelpText('images')}
                      </FormDescription>
                      <FormMessage />
                    </FormItem>
                  )}
                />

                <MediaPickerDialog
                  open={mediaPickerOpen}
                  onOpenChange={setMediaPickerOpen}
                  multiple
                  accept="image/*"
                  onSelect={handleSelectImages}
                />

                <Dialog
                  open={previewImage !== null}
                  onOpenChange={(open) => {
                    if (!open) setPreviewImage(null)
                  }}
                >
                  <DialogContent className="max-w-fit">
                    <DialogHeader className="text-start">
                      <DialogTitle>{tLabel('image_preview')}</DialogTitle>
                    </DialogHeader>
                    <img
                      src={previewImage ?? ''}
                      alt={tLabel('image_preview')}
                      className="max-h-[70vh] w-auto max-w-full object-contain"
                    />
                    <DialogFooter>
                      <Button variant="outline" asChild>
                        <a
                          href={previewImage ?? '#'}
                          target="_blank"
                          rel="noreferrer"
                        >
                          <ExternalLinkIcon size={16} />
                          {tLabel('open_original')}
                        </a>
                      </Button>
                    </DialogFooter>
                  </DialogContent>
                </Dialog>
              </TabsContent>
              <TabsContent value="categories" className="pt-5 pb-5">
                {/* Categories Field */}
                <FormField
                  control={form.control}
                  name="categories"
                  render={() => (
                    <FormItem>
                      <FormLabel>{tLabel('categories')}</FormLabel>
                      <div className='max-h-64 space-y-2 overflow-y-auto rounded-md border p-3'>
                        {categories.map((cat) => (
                          <FormField
                            key={cat.id}
                            control={form.control}
                            name="categories"
                            render={({ field }) => (
                              <FormItem
                                key={cat.id}
                                className="flex flex-row items-center gap-2"
                                style={{ paddingLeft: `${cat.depth * 1.25}rem` }}
                              >
                                <FormControl>
                                  <Checkbox
                                    checked={field.value?.includes(cat.id)}
                                    onCheckedChange={(checked) => {
                                      return checked
                                        ? field.onChange([...field.value, cat.id])
                                        : field.onChange(
                                          field.value?.filter(
                                            (value: number) => value !== cat.id
                                          )
                                        )
                                    }}
                                  />
                                </FormControl>
                                <FormLabel className="text-sm font-normal">{cat.title}</FormLabel>
                              </FormItem>
                            )}
                          />
                        ))}
                      </div>
                      <FormMessage />
                    </FormItem>
                  )}
                />
              </TabsContent>
              <TabsContent value="attributes" className="pt-5 pb-5">
                <ProductAttributesTab control={form.control} />
              </TabsContent>
              <TabsContent value="shipping" className="pt-5 pb-5">
                <ProductShippingTab control={form.control} />
              </TabsContent>
              <TabsContent value="publish" className="pt-5 pb-5">

                <FormField
                  control={form.control}
                  name='is_published'
                  render={({ field }) => (
                    <FormItem className='mb-5'>
                      <FormLabel>{tLabel('is_published')}</FormLabel>
                      <Select
                        onValueChange={(value) => field.onChange(value === "true")}
                        defaultValue={String(field.value)}
                      >
                        <FormControl className='w-full'>
                          <SelectTrigger className="w-full">
                            <SelectValue placeholder={tPlaceHolder('select')} />
                          </SelectTrigger>
                        </FormControl>
                        <SelectContent>
                          <SelectItem key="true" value="true">
                            {tStatus(`is_published.true`)}
                          </SelectItem>
                          <SelectItem key="false" value="false">
                            {tStatus(`is_published.false`)}
                          </SelectItem>
                        </SelectContent>
                      </Select>
                      <FormMessage />
                        <FormDescription>
                          {tTooltip(`is_published.${field.value ? 'active' : 'inactive'}`)}
                        </FormDescription>
                    </FormItem>
                  )}
                />

                {/* Status Name Field */}
                <FormField
                  control={form.control}
                  name='status'
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>{tLabel('status')}</FormLabel>
                      <Select
                        onValueChange={field.onChange}
                        value={field.value}
                        defaultValue={field.value}
                      >
                        <FormControl className='w-full'>
                          <SelectTrigger>
                            <SelectValue placeholder={tPlaceHolder('select')} />
                          </SelectTrigger>
                        </FormControl>
                        <SelectContent>
                          {STATUSES.map((item: string) => (
                            <SelectItem key={item} value={item}>
                              {tStatus(`status.${item}`)}
                            </SelectItem>
                          ))}
                        </SelectContent>
                      </Select>
                      <FormMessage />
                        <FormDescription>
                          {field.value && tTooltip(`status.${field.value}`)}
                        </FormDescription>
                    </FormItem>
                  )}
                />
              </TabsContent>
            </Tabs>

          </form>
        </Form>
      </Main>

    </Provider>
  )
}
