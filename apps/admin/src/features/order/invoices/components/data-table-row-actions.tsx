import { type Row } from '@tanstack/react-table'
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog'
import { Button } from '@/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import {
  DotsHorizontalIcon,
  DownloadIcon,
  ExclamationTriangleIcon,
  UpdateIcon,
} from '@radix-ui/react-icons'
import { toast } from 'sonner'
import { useQueryClient } from '@tanstack/react-query'
import { type Invoice } from '../data/schema'
import { downloadInvoicePdf, retryPdfGeneration, voidInvoice } from '../api/invoices.api'
import { FeatureRoutes, Locales } from '../data/routes'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import { useState } from 'react'
import { Textarea } from '@/components/ui/textarea'
import { Label } from '@/components/ui/label'

interface DataTableRowActionsProps {
  row: Row<Invoice>
}

export function DataTableRowActions({ row }: DataTableRowActionsProps) {
  const invoice = row.original
  const { tLabel } = useAppTranslation(Locales.INVOICE)
  const { t: tCommon } = useAppTranslation(Locales.SHARED_COMMON)
  const queryClient = useQueryClient()
  const [voidOpen, setVoidOpen] = useState(false)
  const [voidReason, setVoidReason] = useState('')

  const handleDownload = async () => {
    try {
      const blob = await downloadInvoicePdf(invoice.id)
      const url = URL.createObjectURL(blob)
      const a = document.createElement('a')
      a.href = url
      a.download = `${invoice.number}.pdf`
      a.click()
      URL.revokeObjectURL(url)
    } catch {
      toast.error(tLabel('download_failed'))
    }
  }

  const handleRetry = async () => {
    try {
      await retryPdfGeneration(invoice.id)
      queryClient.invalidateQueries({ queryKey: [FeatureRoutes.CACHE_KEY] })
      toast.success(tLabel('retry_success'))
    } catch {
      toast.error(tLabel('retry_failed'))
    }
  }

  const handleVoid = async () => {
    try {
      await voidInvoice(invoice.id, voidReason)
      queryClient.invalidateQueries({ queryKey: [FeatureRoutes.CACHE_KEY] })
      toast.success(tLabel('void_success'))
      setVoidOpen(false)
    } catch {
      toast.error(tLabel('void_failed'))
    }
  }

  return (
    <>
      <DropdownMenu>
        <DropdownMenuTrigger asChild>
          <Button
            variant="ghost"
            className="flex h-8 w-8 p-0 data-[state=open]:bg-muted"
          >
            <DotsHorizontalIcon className="h-4 w-4" />
            <span className="sr-only">Open menu</span>
          </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" className="w-[200px]">
          <DropdownMenuLabel>{tCommon('actions.title')}</DropdownMenuLabel>
          {invoice.pdf_status === 'ready' && (
            <DropdownMenuItem onClick={handleDownload}>
              <DownloadIcon className="mr-2 h-4 w-4" />
              {tLabel('download_pdf')}
            </DropdownMenuItem>
          )}
          {invoice.pdf_status === 'generation_failed' && (
            <DropdownMenuItem onClick={handleRetry}>
              <UpdateIcon className="mr-2 h-4 w-4" />
              {tLabel('retry_generation')}
            </DropdownMenuItem>
          )}
          {invoice.status === 'active' && (
            <>
              <DropdownMenuSeparator />
              <DropdownMenuItem
                onClick={() => setVoidOpen(true)}
                className="text-red-600 focus:text-red-600"
              >
                <ExclamationTriangleIcon className="mr-2 h-4 w-4" />
                {tLabel('void')}
              </DropdownMenuItem>
            </>
          )}
        </DropdownMenuContent>
      </DropdownMenu>

      <AlertDialog open={voidOpen} onOpenChange={setVoidOpen}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{tLabel('void_confirm_title')}</AlertDialogTitle>
            <AlertDialogDescription>
              {tLabel('void_confirm_body', { number: invoice.number })}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <div className="grid gap-2 py-2">
            <Label htmlFor="void-reason">{tLabel('void_reason')}</Label>
            <Textarea
              id="void-reason"
              value={voidReason}
              onChange={(e) => setVoidReason(e.target.value)}
              placeholder={tLabel('void_reason_placeholder')}
              rows={3}
            />
          </div>
          <AlertDialogFooter>
            <AlertDialogCancel>{tCommon('actions.cancel')}</AlertDialogCancel>
            <AlertDialogAction
              onClick={handleVoid}
              disabled={!voidReason.trim()}
              className="bg-red-600 hover:bg-red-700"
            >
              {tLabel('void')}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </>
  )
}
