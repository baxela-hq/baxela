import { useQueryClient } from '@tanstack/react-query'
import { FeatureRoutes } from '../data/routes'
import { usePayments } from './provider.tsx'
import { SettleDialog } from './settle-dialog.tsx'
import { RefundDialog } from './refund-dialog.tsx'
import { RefundsDialog } from './refunds-dialog.tsx'

export function Dialogs() {
  const { open, setOpen, currentRow, setCurrentRow } = usePayments()
  const queryClient = useQueryClient()

  return (
    <>
      {currentRow && (
        <>
          <SettleDialog
            key={`payment-confirm-${currentRow.id}`}
            open={open === 'confirm'}
            onOpenChange={() => {
              setOpen('confirm')
              setTimeout(async () => {
                setCurrentRow(null)
                await queryClient.invalidateQueries({
                  queryKey: [FeatureRoutes.CACHE_KEY],
                })
              }, 500)
            }}
            currentRow={currentRow}
            status='success'
          />

          <SettleDialog
            key={`payment-fail-${currentRow.id}`}
            open={open === 'fail'}
            onOpenChange={() => {
              setOpen('fail')
              setTimeout(async () => {
                setCurrentRow(null)
                await queryClient.invalidateQueries({
                  queryKey: [FeatureRoutes.CACHE_KEY],
                })
              }, 500)
            }}
            currentRow={currentRow}
            status='failed'
          />

          <RefundDialog
            key={`payment-refund-${currentRow.id}`}
            open={open === 'refund'}
            onOpenChange={(v) => {
              setOpen(v ? 'refund' : null)
              if (!v) setTimeout(() => setCurrentRow(null), 500)
            }}
            currentRow={currentRow}
          />

          <RefundsDialog
            key={`payment-refunds-${currentRow.id}`}
            open={open === 'refunds'}
            onOpenChange={(v) => {
              setOpen(v ? 'refunds' : null)
              if (!v) setTimeout(() => setCurrentRow(null), 500)
            }}
            currentRow={currentRow}
          />
        </>
      )}
    </>
  )
}
