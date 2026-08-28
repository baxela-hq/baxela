import { useNavigate, useLocation } from '@tanstack/react-router'
import { useAuthStore } from '@/stores/auth-store'
import { ConfirmDialog } from '@/components/confirm-dialog'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import { StorageUtility } from '@/shared/lib/storage-utility'

interface SignOutDialogProps {
  open: boolean
  onOpenChange: (open: boolean) => void
}

export function SignOutDialog({ open, onOpenChange }: SignOutDialogProps) {
  const navigate = useNavigate()
  const { reset } = useAuthStore()
  const location = useLocation()
  const { t } = useAppTranslation('shared/layout')

  const handleSignOut = () => {
    reset()

    // clear currency & language
    StorageUtility.clear()

    // Preserve current location for redirect after sign-in
    const currentPath = location.href
    navigate({
      to: '/sign-in',
      search: { redirect: currentPath },
      replace: true,
    })
  }

  return (
    <ConfirmDialog
      open={open}
      onOpenChange={onOpenChange}
      title={t('dialog.sign-out.title')}
      desc={t('dialog.sign-out.body')}
      confirmText={t('dialog.sign-out.title')}
      cancelBtnText={t('dialog.cancel')}
      destructive
      handleConfirm={handleSignOut}
      className='sm:max-w-sm'
    />
  )
}
