import { DatabaseBackup } from 'lucide-react'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import { Button } from '@/components/ui/button'
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import { useCatalogDataExport } from '../hooks/use-data-transfer'
import { Locales } from '../data/routes'

export function ExportCard() {
  const { tLabel, tHelpText } = useAppTranslation(Locales.DATA)
  const { tAction } = useAppTranslation(Locales.SHARED_COMMON)
  const exportMutation = useCatalogDataExport()

  return (
    <Card>
      <CardHeader>
        <CardTitle className='flex items-center gap-2'>
          <DatabaseBackup size={18} /> {tLabel('export_title')}
        </CardTitle>
        <CardDescription>{tHelpText('export')}</CardDescription>
      </CardHeader>
      <CardContent>
        <Button
          disabled={exportMutation.isPending}
          onClick={() => exportMutation.mutate()}
        >
          {tAction('export')}
        </Button>
      </CardContent>
    </Card>
  )
}
