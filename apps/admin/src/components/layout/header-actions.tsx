import { ExternalLink } from 'lucide-react'
import { ConfigDrawer } from '@/components/config-drawer'
import { NotificationsMenu } from '@/features/notification/components/notifications-menu'
import { ProfileDropdown } from '@/components/profile-dropdown'
import { ThemeSwitch } from '@/components/theme-switch'
import { Button } from '@/components/ui/button'
import { useAppTranslation } from '@/hooks/useAppTranslation'

/**
 * The right-side header cluster shared by every page — hoisted here so the
 * notifications bell (and future additions) land everywhere at once.
 */
export function HeaderActions() {
  const { t } = useAppTranslation('shared/layout')

  return (
    <div className='ms-auto flex items-center space-x-4'>
      <Button
        variant='ghost'
        size='icon'
        className='scale-95 rounded-full'
        asChild
      >
        <a
          href={import.meta.env.VITE_STORE_FRONT_URL}
          target='_blank'
          rel='noopener noreferrer'
        >
          <ExternalLink className='size-[1.2rem]' />
          <span className='sr-only'>{t('toolbar.view-website')}</span>
        </a>
      </Button>
      <ThemeSwitch />
      <ConfigDrawer />
      <NotificationsMenu />
      <ProfileDropdown />
    </div>
  )
}
