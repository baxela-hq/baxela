import { ArrowDownToLine, DatabaseBackup } from 'lucide-react'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import { type TransferSection } from '../api/transfer.api'
import { Locales } from '../data/routes'
import {
  useTransferCapabilities,
  useTransferExport,
} from '../hooks/use-transfer'

function SectionBadges({ sections }: { sections: TransferSection[] }) {
  const { tLabel } = useAppTranslation(Locales.TRANSFER)

  if (sections.length === 0) {
    return <span className='text-sm text-muted-foreground'>—</span>
  }

  return (
    <div className='flex flex-wrap gap-1.5'>
      {sections.map((section) => (
        <Badge key={section.key} variant='outline' className='font-normal'>
          {section.key}
          {!section.exportable && (
            <span className='ms-1 text-muted-foreground'>
              ({tLabel('import_only')})
            </span>
          )}
          {!section.importable && (
            <span className='ms-1 text-muted-foreground'>
              ({tLabel('export_only')})
            </span>
          )}
        </Badge>
      ))}
    </div>
  )
}

/**
 * The coverage matrix of the data-transfer standard: every enabled
 * module with its sections and an export action for the participating
 * ones. Modules that did not opt in stay visible but muted.
 */
export function ExportCard() {
  const { tLabel, tHelpText } = useAppTranslation(Locales.TRANSFER)
  const { tAction } = useAppTranslation(Locales.SHARED_COMMON)
  const capabilitiesQuery = useTransferCapabilities()
  const exportMutation = useTransferExport()

  const capabilities = capabilitiesQuery.data ?? []
  const supported = capabilities.filter((capability) => capability.supported)
  const unsupported = capabilities
    .filter((capability) => !capability.supported)
    .map((capability) => capability.module)

  return (
    <Card>
      <CardHeader>
        <CardTitle className='flex items-center gap-2'>
          <DatabaseBackup size={18} /> {tLabel('export_title')}
        </CardTitle>
        <CardDescription>{tHelpText('export')}</CardDescription>
      </CardHeader>
      <CardContent className='flex flex-col gap-3'>
        {capabilitiesQuery.isLoading && (
          <div className='space-y-2'>
            <Skeleton className='h-10 w-full' />
            <Skeleton className='h-10 w-full' />
            <Skeleton className='h-10 w-2/3' />
          </div>
        )}

        {supported.map((capability) => (
          <div
            key={capability.module}
            className='flex flex-wrap items-center justify-between gap-2 rounded-md border p-3'
          >
            <div className='flex min-w-0 flex-col gap-1.5'>
              <span className='text-sm font-medium capitalize'>
                {capability.module}
              </span>
              <SectionBadges sections={capability.sections} />
            </div>
            {capability.endpoints && (
              <Button
                variant='outline'
                size='sm'
                disabled={exportMutation.isPending}
                onClick={() =>
                  exportMutation.mutate({
                    module: capability.module,
                    exportUrl: capability.endpoints!.export,
                  })
                }
              >
                <ArrowDownToLine size={16} /> {tAction('export')}
              </Button>
            )}
          </div>
        ))}

        {capabilitiesQuery.isSuccess && unsupported.length > 0 && (
          <p className='text-sm text-muted-foreground'>
            {tLabel('not_supported_yet')}: {unsupported.join(', ')}
          </p>
        )}
      </CardContent>
    </Card>
  )
}
