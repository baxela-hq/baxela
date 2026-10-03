import { Fragment, useEffect, useMemo, useState } from 'react'
import { toast } from 'sonner'
import { Download, FileSpreadsheet, Package, RefreshCcw } from 'lucide-react'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import {
  Dialog,
  DialogClose,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Label } from '@/components/ui/label'
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group'
import {
  Select,
  SelectContent,
  SelectGroup,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { Skeleton } from '@/components/ui/skeleton'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { MediaPickerDialog } from '@/features/media/components/media-picker-dialog'
import { type MediaItem } from '@/features/media/data/schema'
import { Locales } from '../data/routes'
import {
  type ProductImportPreview,
  type ProductImportResult,
} from '../api/products.api'
import {
  useProductImport,
  useProductImportPreview,
  useProductImportTemplate,
  useProductImportsList,
} from '../hooks/use-product-import'

type ImportProps = {
  open: boolean
  onOpenChange: (open: boolean) => void
}

type Step = 'file' | 'mapping' | 'options' | 'result'

const IGNORE = '__ignore__'

export function Import({ open, onOpenChange }: ImportProps) {
  const { tLabel, tStatus, tHelpText } = useAppTranslation(Locales.PRODUCT)
  const { tAction, tMessage } = useAppTranslation(Locales.SHARED_COMMON)

  const [step, setStep] = useState<Step>('file')
  const [media, setMedia] = useState<MediaItem | null>(null)
  const [preview, setPreview] = useState<ProductImportPreview | null>(null)
  const [mapping, setMapping] = useState<Record<string, string | null>>({})
  const [onDuplicate, setOnDuplicate] = useState<'update' | 'skip'>('update')
  const [createMissingOptions, setCreateMissingOptions] = useState(true)
  const [dryRun, setDryRun] = useState(true)
  const [result, setResult] = useState<ProductImportResult | null>(null)
  const [pickerOpen, setPickerOpen] = useState(false)
  const [expandedErrorId, setExpandedErrorId] = useState<number | null>(null)

  const previewMutation = useProductImportPreview()
  const importMutation = useProductImport()
  const templateMutation = useProductImportTemplate()
  const historyQuery = useProductImportsList()

  useEffect(() => {
    if (!open) {
      setStep('file')
      setMedia(null)
      setPreview(null)
      setMapping({})
      setOnDuplicate('update')
      setCreateMissingOptions(true)
      setDryRun(true)
      setResult(null)
      setExpandedErrorId(null)
      previewMutation.reset()
      importMutation.reset()
    }
    // Full reset whenever the dialog closes; mutation objects are stable
    // enough that listing them as deps would re-fire this every render.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open])

  const requiredFields = useMemo(
    () =>
      preview
        ? ['variant.sku', 'variant.price', `title.${preview.defaultLanguage}`]
        : [],
    [preview],
  )
  const mappedFields = useMemo(
    () => Object.values(mapping).filter((field): field is string => !!field),
    [mapping],
  )
  const requiredMapped = requiredFields.every((field) =>
    mappedFields.includes(field),
  )
  const rowCapExceeded = preview?.warnings.includes('row_cap_exceeded') ?? false

  const handleSelectMedia = (item: MediaItem) => {
    if (!item) return
    if ((item.extension ?? '').toLowerCase() !== 'csv') {
      toast.error(tMessage('error.validation'))
      return
    }
    setMedia(item)
    previewMutation.mutate(item.id, {
      onSuccess: (data) => {
        setPreview(data)
        setMapping({ ...data.suggestedMapping })
        setStep('mapping')
      },
    })
  }

  const handleRun = () => {
    if (!media || !preview) return
    importMutation.mutate(
      {
        media_id: media.id,
        mapping,
        on_duplicate: onDuplicate,
        dry_run: dryRun,
        create_missing_options: createMissingOptions,
      },
      {
        onSuccess: (data) => {
          setResult(data)
          setStep('result')
        },
      },
    )
  }

  const groupLabel = (key: string) => {
    if (key === 'variant') return tLabel('import_group_variant')
    if (key === 'options') return tLabel('import_group_options')
    if (key === 'base') return tLabel('import_group_base')
    return key.toUpperCase()
  }

  const renderWarnings = (warnings: string[]) => {
    const capWarning = warnings.includes('row_cap_exceeded')
    const others = warnings.filter(
      (warning) =>
        warning !== 'row_cap_exceeded' && warning !== '' && !warning.startsWith('unmapped_column:'),
    )
    return (
      <div className='flex flex-col gap-1'>
        {capWarning && (
          <p className='text-sm font-medium text-destructive'>
            {tLabel('import_row_cap_exceeded', { cap: String(preview?.rowCap ?? 0) })}
          </p>
        )}
        {others.map((warning) => (
          <p key={warning} className='text-sm text-amber-600 dark:text-amber-400'>
            {warning.replace('duplicate_header:', 'duplicate column: ')}
          </p>
        ))}
      </div>
    )
  }

  const sampleValue = (headerIndex: number) => {
    if (!preview) return ''
    return preview.sampleRows
      .map((row) => (row[headerIndex] ?? '').trim())
      .filter(Boolean)
      .slice(0, 3)
      .join(' · ')
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className='flex h-[85vh] w-full flex-col gap-0 sm:max-w-3xl'>
        <DialogHeader className='text-start'>
          <DialogTitle className='flex items-center gap-2'>
            <Package size={18} /> {tLabel('import_products')}
          </DialogTitle>
          <DialogDescription>{tLabel('import_description')}</DialogDescription>
        </DialogHeader>

        <div className='min-h-0 flex-1 overflow-y-auto px-4 pt-2 pb-4'>
          <Tabs defaultValue='run' key={open ? 'open' : 'closed'}>
            <TabsList>
              <TabsTrigger value='run'>{tLabel('import_tab_run')}</TabsTrigger>
              <TabsTrigger value='history'>{tLabel('import_history')}</TabsTrigger>
            </TabsList>

            <TabsContent value='run' className='pt-4'>
              {/* Step 1 — file */}
              {step === 'file' && (
                <div className='flex flex-col gap-4'>
                  <p className='text-sm text-muted-foreground'>
                    {tHelpText('import')}
                  </p>
                  <div className='flex flex-wrap items-center gap-2'>
                    <Button variant='outline' onClick={() => setPickerOpen(true)}>
                      <FileSpreadsheet size={16} /> {tLabel('import_choose_file')}
                    </Button>
                    <Button
                      variant='outline'
                      disabled={templateMutation.isPending}
                      onClick={() =>
                        templateMutation.mutate(undefined, {
                          onError: () =>
                            toast.error(tMessage('error.general')),
                        })
                      }
                    >
                      <Download size={16} /> {tLabel('import_download_template')}
                    </Button>
                  </div>
                  {previewMutation.isPending && (
                    <div className='space-y-2'>
                      <Skeleton className='h-4 w-1/3' />
                      <Skeleton className='h-16 w-full' />
                    </div>
                  )}
                </div>
              )}

              {/* Step 2 — mapping */}
              {step === 'mapping' && preview && (
                <div className='flex flex-col gap-3'>
                  <div className='flex flex-wrap items-center justify-between gap-2'>
                    <div className='flex items-center gap-2 text-sm text-muted-foreground'>
                      <Badge variant='outline'>
                        {media?.name ?? preview.filename}
                      </Badge>
                      <span>
                        {tLabel('import_rows')}: {preview.rowCount}
                      </span>
                    </div>
                    <Button variant='outline' size='sm' onClick={() => setPickerOpen(true)}>
                      <RefreshCcw size={16} /> {tLabel('import_change_file')}
                    </Button>
                  </div>

                  {preview.warnings.length > 0 && renderWarnings(preview.warnings)}

                  <Table>
                    <TableHeader>
                      <TableRow>
                        <TableHead>{tLabel('import_file_column')}</TableHead>
                        <TableHead>{tLabel('import_sample_value')}</TableHead>
                        <TableHead className='w-[45%]'>{tLabel('import_field')}</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {preview.headers.map((header, index) => {
                        const value = mapping[header] ?? ''
                        return (
                          <TableRow key={`${header}-${index}`}>
                            <TableCell className='font-medium'>
                              {header}
                              {requiredFields.includes(header) && (
                                <span className='text-destructive'> *</span>
                              )}
                            </TableCell>
                            <TableCell className='max-w-48 truncate text-muted-foreground'>
                              {sampleValue(index)}
                            </TableCell>
                            <TableCell>
                              <Select
                                value={value === '' ? IGNORE : value}
                                onValueChange={(field) =>
                                  setMapping((prev) => ({
                                    ...prev,
                                    [header]: field === IGNORE ? null : field,
                                  }))
                                }
                              >
                                <SelectTrigger size='sm' className='w-full'>
                                  <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                  <SelectItem value={IGNORE}>
                                    {tLabel('import_ignore')}
                                  </SelectItem>
                                  {Object.entries(preview.availableFields).map(
                                    ([group, fields]) => (
                                      <SelectGroup key={group}>
                                        <SelectItem
                                          key={`${group}-label`}
                                          value={`${group}-label`}
                                          disabled
                                          className='text-muted-foreground'
                                        >
                                          {groupLabel(group)}
                                        </SelectItem>
                                        {fields.map((field) => (
                                          <SelectItem key={field} value={field}>
                                            {field}
                                          </SelectItem>
                                        ))}
                                      </SelectGroup>
                                    ),
                                  )}
                                </SelectContent>
                              </Select>
                            </TableCell>
                          </TableRow>
                        )
                      })}
                    </TableBody>
                  </Table>
                </div>
              )}

              {/* Step 3 — options & run */}
              {step === 'options' && preview && (
                <div className='flex flex-col gap-5'>
                  <div className='flex flex-col gap-2'>
                    <Label>{tLabel('import_duplicate_strategy')}</Label>
                    <RadioGroup
                      value={onDuplicate}
                      onValueChange={(value: string) =>
                        setOnDuplicate(value === 'skip' ? 'skip' : 'update')
                      }
                      className='gap-3'
                    >
                      <div className='flex items-center gap-2'>
                        <RadioGroupItem value='update' id='strategy-update' />
                        <Label htmlFor='strategy-update'>
                          {tStatus('import_strategy.update')}
                        </Label>
                      </div>
                      <div className='flex items-center gap-2'>
                        <RadioGroupItem value='skip' id='strategy-skip' />
                        <Label htmlFor='strategy-skip'>
                          {tStatus('import_strategy.skip')}
                        </Label>
                      </div>
                    </RadioGroup>
                  </div>

                  <div className='flex items-start gap-2'>
                    <Checkbox
                      id='create-missing-options'
                      checked={createMissingOptions}
                      onCheckedChange={(checked) => setCreateMissingOptions(checked === true)}
                    />
                    <div className='flex flex-col gap-1'>
                      <Label htmlFor='create-missing-options'>
                        {tLabel('import_create_missing_options')}
                      </Label>
                      <p className='text-sm text-muted-foreground'>
                        {tHelpText('import_create_missing_options')}
                      </p>
                    </div>
                  </div>

                  <div className='flex items-start gap-2'>
                    <Checkbox
                      id='dry-run'
                      checked={dryRun}
                      onCheckedChange={(checked) => setDryRun(checked === true)}
                    />
                    <div className='flex flex-col gap-1'>
                      <Label htmlFor='dry-run'>{tLabel('import_dry_run')}</Label>
                      <p className='text-sm text-muted-foreground'>
                        {tHelpText('import_dry_run')}
                      </p>
                    </div>
                  </div>
                </div>
              )}

              {/* Result */}
              {step === 'result' && result && (
                <div className='flex flex-col gap-4'>
                  <div className='flex flex-wrap items-center gap-2'>
                    <Badge
                      variant={result.status === 'completed' ? 'secondary' : 'destructive'}
                    >
                      {tStatus(`import_status.${result.status}`)}
                    </Badge>
                    {result.dry_run && (
                      <Badge variant='outline'>{tLabel('import_dry_run')}</Badge>
                    )}
                    <span className='text-sm text-muted-foreground'>
                      {tLabel('import_total_rows')}: {result.total_rows} ·{' '}
                      {tLabel('import_duration')}: {result.duration_ms} ms
                    </span>
                  </div>

                  <div className='grid grid-cols-2 gap-3 sm:grid-cols-4'>
                    {(
                      [
                        ['import_created', result.created_count],
                        ['import_updated', result.updated_count],
                        ['import_skipped', result.skipped_count],
                        ['import_failed', result.failed_count],
                      ] as const
                    ).map(([label, count]) => (
                      <div
                        key={label}
                        className='flex flex-col items-center rounded-md border p-3'
                      >
                        <span className='text-2xl font-semibold'>{count}</span>
                        <span className='text-sm text-muted-foreground'>
                          {tLabel(label)}
                        </span>
                      </div>
                    ))}
                  </div>

                  {result.errors.length > 0 ? (
                    <Table>
                      <TableHeader>
                        <TableRow>
                          <TableHead className='w-20'>{tLabel('import_row')}</TableHead>
                          <TableHead>{tLabel('import_messages')}</TableHead>
                        </TableRow>
                      </TableHeader>
                      <TableBody>
                        {result.errors.map((error, index) => (
                          <TableRow key={`${error.row}-${index}`}>
                            <TableCell className='font-medium'>{error.row}</TableCell>
                            <TableCell className='text-destructive'>
                              {error.messages.join(' ')}
                            </TableCell>
                          </TableRow>
                        ))}
                      </TableBody>
                    </Table>
                  ) : (
                    <p className='text-sm text-muted-foreground'>
                      {tLabel('import_no_errors')}
                    </p>
                  )}
                </div>
              )}
            </TabsContent>

            <TabsContent value='history' className='pt-4'>
              {historyQuery.isLoading ? (
                <Skeleton className='h-24 w-full' />
              ) : (historyQuery.data?.data ?? []).length === 0 ? (
                <p className='text-sm text-muted-foreground'>
                  {tLabel('import_no_history')}
                </p>
              ) : (
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>{tLabel('import_filename')}</TableHead>
                      <TableHead>{tLabel('status')}</TableHead>
                      <TableHead>{tLabel('import_created')}</TableHead>
                      <TableHead>{tLabel('import_updated')}</TableHead>
                      <TableHead>{tLabel('import_failed')}</TableHead>
                      <TableHead>{tLabel('import_run_at')}</TableHead>
                      <TableHead className='w-24' />
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {(historyQuery.data?.data ?? []).map((row) => (
                      <Fragment key={row.id}>
                        <TableRow>
                          <TableCell className='font-medium'>
                            {row.filename}
                            {row.dry_run && (
                              <Badge variant='outline' className='ms-2'>
                                {tLabel('import_dry_run')}
                              </Badge>
                            )}
                          </TableCell>
                          <TableCell>
                            <Badge
                              variant={
                                row.status === 'completed' ? 'secondary' : 'destructive'
                              }
                            >
                              {tStatus(`import_status.${row.status}`)}
                            </Badge>
                          </TableCell>
                          <TableCell>{row.created_count}</TableCell>
                          <TableCell>{row.updated_count}</TableCell>
                          <TableCell>{row.failed_count}</TableCell>
                          <TableCell className='text-muted-foreground'>
                            {row.created_at
                              ? new Date(row.created_at).toLocaleString()
                              : '—'}
                          </TableCell>
                          <TableCell>
                            {row.errors && row.errors.length > 0 && (
                              <Button
                                variant='ghost'
                                size='sm'
                                onClick={() =>
                                  setExpandedErrorId(
                                    expandedErrorId === row.id ? null : row.id,
                                  )
                                }
                              >
                                {expandedErrorId === row.id
                                  ? tLabel('import_hide_errors')
                                  : tLabel('import_view_errors')}
                              </Button>
                            )}
                          </TableCell>
                        </TableRow>
                        {expandedErrorId === row.id && (
                          <TableRow>
                            <TableCell colSpan={7}>
                              <ul className='list-disc space-y-1 ps-4 text-sm text-destructive'>
                                {(row.errors ?? []).map((error, index) => (
                                  <li key={`${row.id}-${index}`}>
                                    {tLabel('import_row')} {error.row}:{' '}
                                    {error.messages.join(' ')}
                                  </li>
                                ))}
                              </ul>
                            </TableCell>
                          </TableRow>
                        )}
                      </Fragment>
                    ))}
                  </TableBody>
                </Table>
              )}
            </TabsContent>
          </Tabs>
        </div>

        <DialogFooter className='gap-y-2 border-t pt-3'>
          <DialogClose asChild>
            <Button variant='outline'>{tAction('cancel')}</Button>
          </DialogClose>

          {step === 'mapping' && (
            <>
              <Button variant='outline' onClick={() => setStep('file')}>
                {tLabel('import_back')}
              </Button>
              <Button
                disabled={!requiredMapped || rowCapExceeded}
                onClick={() => setStep('options')}
              >
                {tLabel('import_next')}
              </Button>
            </>
          )}

          {step === 'options' && (
            <>
              <Button variant='outline' onClick={() => setStep('mapping')}>
                {tLabel('import_back')}
              </Button>
              <Button
                disabled={importMutation.isPending}
                onClick={handleRun}
              >
                {dryRun ? tLabel('import_run_dry_run') : tLabel('import_run_import')}
              </Button>
            </>
          )}

          {step === 'result' && (
            <>
              {result?.dry_run && (
                <Button variant='outline' onClick={() => setStep('options')}>
                  {tLabel('import_back')}
                </Button>
              )}
              {(!result?.dry_run || result.failed_count > 0) && (
                <Button
                  variant='outline'
                  onClick={() => {
                    setStep('file')
                    setResult(null)
                    setPreview(null)
                    setMedia(null)
                  }}
                >
                  {tLabel('import_new_import')}
                </Button>
              )}
            </>
          )}
        </DialogFooter>
      </DialogContent>

      <MediaPickerDialog
        open={pickerOpen}
        onOpenChange={setPickerOpen}
        accept='.csv,text/csv'
        title={tLabel('import_choose_file')}
        onSelect={handleSelectMedia}
      />
    </Dialog>
  )
}
