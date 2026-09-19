import { ConfigDrawer } from '@/components/config-drawer'
import { NotificationsMenu } from '@/features/notification/components/notifications-menu'
import { ProfileDropdown } from '@/components/profile-dropdown'
import { ThemeSwitch } from '@/components/theme-switch'

/**
 * The right-side header cluster shared by every page — hoisted here so the
 * notifications bell (and future additions) land everywhere at once.
 */
export function HeaderActions() {
  return (
    <div className='ms-auto flex items-center space-x-4'>
      <ThemeSwitch />
      <ConfigDrawer />
      <NotificationsMenu />
      <ProfileDropdown />
    </div>
  )
}
