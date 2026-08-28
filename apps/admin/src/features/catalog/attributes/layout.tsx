import { Link, Outlet, useLocation } from '@tanstack/react-router'
import { cn } from '@/lib/utils'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import { ConfigDrawer } from '@/components/config-drawer'
import { Header } from '@/components/layout/header'
import { Main } from '@/components/layout/main'
import { ProfileDropdown } from '@/components/profile-dropdown'
import { Search } from '@/components/search'
import { ThemeSwitch } from '@/components/theme-switch'
import { Locales } from './data/routes'
import { Locales as AttributeGroupLocales } from '../attribute-groups/data/routes'
import { Locales as AttributeTemplateLocales } from '../attribute-templates/data/routes'

export function AttributesLayout() {
  const { t } = useAppTranslation('shared/layout')
  const { tLabel: tAttributeLabel } = useAppTranslation(Locales.ATTRIBUTE)
  const { tLabel: tAttributeGroupLabel } = useAppTranslation(
    AttributeGroupLocales.ATTRIBUTE_GROUP
  )
  const { tLabel: tAttributeTemplateLabel } = useAppTranslation(
    AttributeTemplateLocales.ATTRIBUTE_TEMPLATE
  )
  const { pathname } = useLocation()

  const tabs = [
    {
      to: '/catalog/attributes',
      title: tAttributeLabel('attributes'),
      isActive: pathname === '/catalog/attributes',
    },
    {
      to: '/catalog/attributes/groups',
      title: tAttributeGroupLabel('attribute_groups'),
      isActive: pathname.startsWith('/catalog/attributes/groups'),
    },
    {
      to: '/catalog/attributes/templates',
      title: tAttributeTemplateLabel('attribute_templates'),
      isActive: pathname.startsWith('/catalog/attributes/templates'),
    },
  ]

  return (
    <>
      <Header fixed>
        <Search />
        <div className='ms-auto flex items-center space-x-4'>
          <ThemeSwitch />
          <ConfigDrawer />
          <ProfileDropdown />
        </div>
      </Header>

      <Main className='flex flex-1 flex-col gap-4 sm:gap-6'>
        <div className='flex flex-wrap items-end justify-between gap-2'>
          <div>
            <h1 className='text-2xl font-bold tracking-tight'>
              {t('sidebar.attributes')}
            </h1>
          </div>
        </div>

        <nav className='flex flex-wrap gap-6 border-b'>
          {tabs.map((tab) => (
            <Link
              key={tab.to}
              to={tab.to}
              className={cn(
                '-mb-px border-b-2 pb-2 text-sm font-medium',
                tab.isActive
                  ? 'border-primary text-foreground'
                  : 'border-transparent text-muted-foreground hover:text-foreground'
              )}
            >
              {tab.title}
            </Link>
          ))}
        </nav>

        <Outlet />
      </Main>
    </>
  )
}
