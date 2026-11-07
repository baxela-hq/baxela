import { useRef, useState } from 'react'
import { ChevronLeft, DatabaseZap, FileJson, RefreshCcw } from 'lucide-react'
import { toast } from 'sonner'
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
import {
  type TransferEndpoints,
  type TransferImportPreview,
  type TransferImportResult,
} from '../api/transfer.api'
import { Locales } from '../data/routes'
import {
  useTransferCapabilities,
  useTransferImport,
  useTransferImportPreview,
} from '../hooks/use-transfer'

type Step = 'file' | 'review' | 'result'

export function ImportCard() {
  const { tLabel, tStatus, tHelpText } = useAppTranslation(Locales.TRANSFER)
  const { tAction, tMessage } = useAppTranslation(Locales.SHARED_COMMON)

  const capabilitiesQuery = useTransferCapabilities()
  const fileInputRef = useRef<HTMLInputElement>(null)
  const [step, setStep] = useState<Step>('file')
  const [file, setFile] = useState<File | null>(null)
  const [endpoints, setEndpoints] = useState<TransferEndpoints | null>(null)
  const [preview, setPreview] = useState<TransferImportPreview | null>(null)
  const [onDuplicate, setOnDuplicate] = useState<'update' | 'skip'>('update')
  const [dryRun, setDryRun] = useState(true)
  const [result, setResult] = useState<TransferImportResult | null>(null)

  const previewMutation = useTransferImportPreview()
  const importMutation = useTransferImport()

  const resetFlow = () => {
    setStep('file')
    setFile(null)
    setEndpoints(null)
    setPreview(null)
    setOnDuplicate('update')
    setDryRun(true)
    setResult(null)
    previewMutation.reset()
    importMutation.reset()
    if (fileInputRef.current) {
      fileInputRef.current.value = ''
    }
  }

  const pickFile = () => {
    fileInputRef.current?.click()
  }

  const handleFileChange = async (
    event: React.ChangeEvent<HTMLInputElement>
  ) => {
    const selected = event.target.files?.[0]
    if (!selected) return

    const name = selected.name.toLowerCase()
    if (!name.endsWith('.json')) {
      toast.error(tMessage('error.validation'))
      event.target.value = ''
      return
    }

    // The envelope declares its module — read it client-side to route
    // the upload to that module's own data endpoints.
    const text = await selected.text()
    const declared = /"module"\s*:\s*"([^"]+)"/.exec(text.slice(0, 4096))?.[1]
    const capability = declared
      ? capabilitiesQuery.data?.find((item) => item.module === declared)
      : undefined

    if (!declared || !capability?.endpoints) {
      toast.error(tLabel('module_not_supported', { module: declared ?? '?' }))
      event.target.value = ''
      return
    }

    setFile(selected)
    setEndpoints(capability.endpoints)
    previewMutation.mutate(
      { file: selected, previewUrl: capability.endpoints.import_preview },
      {
        onSuccess: (data) => {
          setPreview(data)
          setStep('review')
        },
      }
    )
  }

  const handleRun = () => {
    if (!file || !preview || !endpoints) return
    importMutation.mutate(
      {
        file,
        on_duplicate: onDuplicate,
        dry_run: dryRun,
        importUrl: endpoints.import,
      },
      {
        onSuccess: (data) => {
          setResult(data)
          setStep('result')
        },
      }
    )
  }

  const rowCapExceeded = preview?.warnings.includes('row_cap_exceeded') ?? false
  const notImportable = (preview?.warnings ?? [])
    .filter((warning) => warning.startsWith('not_importable:'))
    .map((warning) => warning.slice('not_importable:'.length))
  const otherWarnings = (preview?.warnings ?? []).filter(
    (warning) =>
      warning !== 'row_cap_exceeded' && !warning.startsWith('not_importable:')
  )

  return (
    <div className='flex flex-col gap-4'>
      {/* Hidden native input — imports go up as multipart, not through Media. */}
      <input
        ref={fileInputRef}
        type='file'
        accept='.json,application/json'
        className='hidden'
        onChange={handleFileChange}
      />

      {step === 'file' && (
        <div className='flex flex-col gap-3'>
          <p className='text-sm text-muted-foreground'>{tHelpText('import')}</p>
          <div>
            <Button variant='outline' onClick={pickFile}>
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
              <Badge variant='outline'>{preview.filename}</Badge>
              <Badge variant='secondary' className='capitalize'>
                {preview.module}
              </Badge>
              <span>
                {tLabel('total_rows')}: {preview.total_rows}
              </span>
              {preview.exported_at && (
                <span>
                  · {tLabel('exported_at')}: {preview.exported_at}
                </span>
              )}
            </div>
            <Button variant='outline' size='sm' onClick={pickFile}>
              <RefreshCcw size={16} /> {tLabel('change_file')}
            </Button>
          </div>

          {rowCapExceeded && (
            <p className='text-sm font-medium text-destructive'>
              {tLabel('row_cap_exceeded', { cap: String(preview.row_cap) })}
            </p>
          )}
          {notImportable.length > 0 && (
            <p className='text-sm text-amber-600 dark:text-amber-400'>
              {tLabel('warning_not_importable', {
                sections: notImportable.join(', '),
              })}
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
                    {section.entity}
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
                <RadioGroupItem value='update' id='transfer-strategy-update' />
                <Label htmlFor='transfer-strategy-update'>
                  {tStatus('strategy.update')}
                </Label>
              </div>
              <div className='flex items-center gap-2'>
                <RadioGroupItem value='skip' id='transfer-strategy-skip' />
                <Label htmlFor='transfer-strategy-skip'>
                  {tStatus('strategy.skip')}
                </Label>
              </div>
            </RadioGroup>
          </div>

          <div className='flex items-start gap-2'>
            <Checkbox
              id='transfer-dry-run'
              checked={dryRun}
              onCheckedChange={(checked) => setDryRun(checked === true)}
            />
            <div className='flex flex-col gap-1'>
              <Label htmlFor='transfer-dry-run'>{tLabel('dry_run')}</Label>
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
              variant={
                result.status === 'completed' ? 'secondary' : 'destructive'
              }
            >
              {tStatus(`status.${result.status}`)}
            </Badge>
            {result.dry_run && (
              <Badge variant='outline'>{tLabel('dry_run')}</Badge>
            )}
            <span className='text-sm text-muted-foreground capitalize'>
              {result.module} · {tLabel('total_rows')}: {result.total_rows} ·{' '}
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
                    <TableCell className='font-medium'>{entity}</TableCell>
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
            <ul className='flex flex-col gap-1'>
              {result.errors.map((error, index) => (
                <li
                  key={`${error}-${index}`}
                  className='text-sm text-destructive'
                >
                  {error}
                </li>
              ))}
            </ul>
          ) : (
            <p className='text-sm text-muted-foreground'>
              {tLabel('no_errors')}
            </p>
          )}

          <div className='flex gap-2'>
            <Button variant='outline' onClick={resetFlow}>
              <ChevronLeft size={16} /> {tLabel('new_import')}
            </Button>
            {result.dry_run && (
              <Button
                disabled={importMutation.isPending}
                onClick={() =>
                  file &&
                  endpoints &&
                  importMutation.mutate(
                    {
                      file,
                      on_duplicate: onDuplicate,
                      dry_run: false,
                      importUrl: endpoints.import,
                    },
                    { onSuccess: (data) => setResult(data) }
                  )
                }
              >
                {tAction('import')}
              </Button>
            )}
          </div>
        </div>
      )}
    </div>
  )
}

export function ImportCardTitle() {
  const { tLabel } = useAppTranslation(Locales.TRANSFER)
  return (
    <span className='flex items-center gap-2'>
      <DatabaseZap size={18} /> {tLabel('import_title')}
    </span>
  )
}
