import { type Row } from '@tanstack/react-table'
import { Button } from '@/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { DotsHorizontalIcon, DownloadIcon } from '@radix-ui/react-icons'
import { toast } from 'sonner'
import { type CreditNote } from '../data/schema'
import { downloadCreditNotePdf } from '../api/invoices.api'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import { Locales } from '../data/routes'

/**
 * Credit-note row actions (gap-B1 sliver): download only — CNs are
 * append-only refund documents; there is no void/retry path (the admin
 * surface exposes list + PDF alone).
 */
export function CreditNoteRowActions({ row }: { row: Row<CreditNote> }) {
  const note = row.original
  const { tLabel } = useAppTranslation(Locales.INVOICE)

  const handleDownload = async () => {
    try {
      const blob = await downloadCreditNotePdf(note.id)
      const url = URL.createObjectURL(blob)
      const a = document.createElement('a')
      a.href = url
      a.download = `${note.number}.pdf`
      a.click()
      URL.revokeObjectURL(url)
    } catch {
      toast.error(tLabel('download_failed'))
    }
  }

  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button
          variant='ghost'
          className='flex h-8 w-8 p-0 data-[state=open]:bg-muted'
        >
          <DotsHorizontalIcon className='h-4 w-4' />
          <span className='sr-only'>Open menu</span>
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align='end' className='w-40'>
        <DropdownMenuLabel>{tLabel('actions')}</DropdownMenuLabel>
        {note.pdf_status === 'ready' && (
          <DropdownMenuItem onClick={handleDownload}>
            <DownloadIcon className='mr-2 h-4 w-4' />
            {tLabel('download_pdf')}
          </DropdownMenuItem>
        )}
      </DropdownMenuContent>
    </DropdownMenu>
  )
}
