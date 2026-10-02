import { useState } from 'react'
import { toast } from 'sonner'
import { ChevronLeft, DatabaseZap, FileJson, RefreshCcw } from 'lucide-react'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { Label } from '@/components/ui/label'
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group'
import { Skeleton } from '@/components/ui/skeleton'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { MediaPickerDialog } from '@/features/media/components/media-picker-dialog'
import { type MediaItem } from '@/features/media/data/schema'
import { Locales } from '../data/routes'
import { type CatalogImportPreview, type CatalogImportResult } from '../api/data.api'
import { useCatalogImport, useCatalogImportPreview } from '../hooks/use-data-transfer'

type Step = 'file' | 'review' | 'result'

export function ImportCard() {
  const { tLabel, tStatus, tHelpText } = useAppTranslation(Locales.DATA)
  const { tAction, tMessage } = useAppTranslation(Locales.SHARED_COMMON)

  const [step, setStep] = useState<Step>('file')
  const [media, setMedia] = useState<MediaItem | null>(null)
  const [preview, setPreview] = useState<CatalogImportPreview | null>(null)
  const [onDuplicate, setOnDuplicate] = useState<'update' | 'skip'>('update')
  const [dryRun, setDryRun] = useState(true)
  const [result, setResult] = useState<CatalogImportResult | null>(null)
  const [pickerOpen, setPickerOpen] = useState(false)

  const previewMutation = useCatalogImportPreview()
  const importMutation = useCatalogImport()

  const resetFlow = () => {
    setStep('file')
    setMedia(null)
    setPreview(null)
    setOnDuplicate('update')
    setDryRun(true)
    setResult(null)
    previewMutation.reset()
    importMutation.reset()
  }

  const handleSelectMedia = (item: MediaItem) => {
    if (!item) return
    if ((item.extension ?? '').toLowerCase() !== 'json') {
      toast.error(tMessage('error.validation'))
      return
    }
    setMedia(item)
    previewMutation.mutate(item.id, {
      onSuccess: (data) => {
        setPreview(data)
        setStep('review')
      },
    })
  }

  const handleRun = () => {
    if (!media || !preview) return
    importMutation.mutate(
      {
        media_id: media.id,
        on_duplicate: onDuplicate,
        dry_run: dryRun,
      },
      {
        onSuccess: (data) => {
          setResult(data)
          setStep('result')
        },
      },
    )
  }

  const rowCapExceeded = preview?.warnings.includes('row_cap_exceeded') ?? false
  const otherWarnings = (preview?.warnings ?? []).filter(
    (warning) => warning !== 'row_cap_exceeded',
  )

  return (
    <>
      {step === 'file' && (
        <div className='flex flex-col gap-3'>
          <p className='text-sm text-muted-foreground'>{tHelpText('import')}</p>
          <div className='flex flex-wrap items-center gap-2'>
            <Button variant='outline' onClick={() => setPickerOpen(true)}>
              <FileJson size={16} /> {tLabel('choose_file')}
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

      {step === 'review' && preview && (
        <div className='flex flex-col gap-4'>
          <div className='flex flex-wrap items-center justify-between gap-2'>
            <div className='flex flex-wrap items-center gap-2 text-sm text-muted-foreground'>
              <Badge variant='outline'>{media?.name ?? preview.filename}</Badge>
              <span>
                {tLabel('total_rows')}: {preview.total_rows}
              </span>
              {preview.exported_at && (
                <span>
                  · {tLabel('exported_at')}: {preview.exported_at}
                </span>
              )}
            </div>
            <Button variant='outline' size='sm' onClick={() => setPickerOpen(true)}>
              <RefreshCcw size={16} /> {tLabel('change_file')}
            </Button>
          </div>

          {rowCapExceeded && (
            <p className='text-sm font-medium text-destructive'>
              {tLabel('row_cap_exceeded', { cap: String(preview.row_cap) })}
            </p>
          )}
          {otherWarnings.length > 0 && (
            <ul className='flex flex-col gap-1'>
              {otherWarnings.map((warning) => (
                <li
                  key={warning}
                  className='text-sm text-amber-600 dark:text-amber-400'
                >
                  {warning}
                </li>
              ))}
            </ul>
          )}

          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>{tLabel('section')}</TableHead>
                <TableHead>{tLabel('rows')}</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {preview.sections.map((section) => (
                <TableRow key={section.entity}>
                  <TableCell className='font-medium'>
                    {tStatus(`section.${section.entity}`)}
                  </TableCell>
                  <TableCell>{section.rows}</TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>

          <div className='flex flex-col gap-2'>
            <Label>{tLabel('duplicate_strategy')}</Label>
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
                  {tStatus('strategy.update')}
                </Label>
              </div>
              <div className='flex items-center gap-2'>
                <RadioGroupItem value='skip' id='strategy-skip' />
                <Label htmlFor='strategy-skip'>{tStatus('strategy.skip')}</Label>
              </div>
            </RadioGroup>
          </div>

          <div className='flex items-start gap-2'>
            <Checkbox
              id='dry-run'
              checked={dryRun}
              onCheckedChange={(checked) => setDryRun(checked === true)}
            />
            <div className='flex flex-col gap-1'>
              <Label htmlFor='dry-run'>{tLabel('dry_run')}</Label>
              <p className='text-sm text-muted-foreground'>
                {tHelpText('dry_run')}
              </p>
            </div>
          </div>

          <Button disabled={importMutation.isPending} onClick={handleRun}>
            {dryRun ? tLabel('run_dry_run') : tAction('import')}
          </Button>
        </div>
      )}

      {step === 'result' && result && (
        <div className='flex flex-col gap-4'>
          <div className='flex flex-wrap items-center gap-2'>
            <Badge
              variant={result.status === 'completed' ? 'secondary' : 'destructive'}
            >
              {tStatus(`status.${result.status}`)}
            </Badge>
            {result.dry_run && (
              <Badge variant='outline'>{tLabel('dry_run')}</Badge>
            )}
            <span className='text-sm text-muted-foreground'>
              {tLabel('total_rows')}: {result.total_rows} ·{' '}
              {tLabel('duration')}: {result.duration_ms} ms
            </span>
          </div>

          <div className='grid grid-cols-2 gap-3 sm:grid-cols-4'>
            {(
              [
                ['created', result.created_count],
                ['updated', result.updated_count],
                ['skipped', result.skipped_count],
                ['failed', result.failed_count],
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

          {Object.keys(result.sections).length > 0 && (
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>{tLabel('section')}</TableHead>
                  <TableHead>{tLabel('created')}</TableHead>
                  <TableHead>{tLabel('updated')}</TableHead>
                  <TableHead>{tLabel('skipped')}</TableHead>
                  <TableHead>{tLabel('failed')}</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {Object.entries(result.sections).map(([entity, counts]) => (
                  <TableRow key={entity}>
                    <TableCell className='font-medium'>
                      {tStatus(`section.${entity}`)}
                    </TableCell>
                    <TableCell>{counts.created}</TableCell>
                    <TableCell>{counts.updated}</TableCell>
                    <TableCell>{counts.skipped}</TableCell>
                    <TableCell>{counts.failed}</TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          )}

          {result.warnings.length > 0 && (
            <ul className='flex flex-col gap-1'>
              {result.warnings.map((warning) => (
                <li
                  key={warning}
                  className='text-sm text-amber-600 dark:text-amber-400'
                >
                  {warning}
                </li>
              ))}
            </ul>
          )}

          {result.errors.length > 0 ? (
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead className='w-40'>{tLabel('section')}</TableHead>
                  <TableHead className='w-16'>#</TableHead>
                  <TableHead>{tLabel('messages')}</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {result.errors.map((error, index) => (
                  <TableRow key={`${error.section}-${error.row}-${index}`}>
                    <TableCell className='font-medium'>{error.section}</TableCell>
                    <TableCell>{error.row}</TableCell>
                    <TableCell className='text-destructive'>
                      {error.messages.join(' ')}
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          ) : (
            <p className='text-sm text-muted-foreground'>{tLabel('no_errors')}</p>
          )}

          <div className='flex gap-2'>
            <Button variant='outline' onClick={resetFlow}>
              <ChevronLeft size={16} /> {tLabel('new_import')}
            </Button>
            {result.dry_run && (
              <Button
                disabled={importMutation.isPending}
                onClick={() =>
                  media &&
                  importMutation.mutate(
                    {
                      media_id: media.id,
                      on_duplicate: onDuplicate,
                      dry_run: false,
                    },
                    { onSuccess: (data) => setResult(data) },
                  )
                }
              >
                {tAction('import')}
              </Button>
            )}
          </div>
        </div>
      )}

      <MediaPickerDialog
        open={pickerOpen}
        onOpenChange={setPickerOpen}
        accept='.json,application/json'
        title={tLabel('choose_file')}
        onSelect={handleSelectMedia}
      />
    </>
  )
}

export function ImportCardTitle() {
  const { tLabel } = useAppTranslation(Locales.DATA)
  return (
    <span className='flex items-center gap-2'>
      <DatabaseZap size={18} /> {tLabel('import_title')}
    </span>
  )
}
