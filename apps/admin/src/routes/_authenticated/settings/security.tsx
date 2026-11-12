import { createFileRoute } from '@tanstack/react-router'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import { Separator } from '@/components/ui/separator'
import { ContentSection } from '@/features/settings/components/content-section'
import { TwoFactorSettings } from '@/features/auth/two-factor/components/two-factor-settings'
import { SessionsSection } from '@/features/auth/sessions/components/sessions-section'
import { Locales as TwoFactorLocales } from '@/features/auth/two-factor/data/routes'
import { Locales as SessionsLocales } from '@/features/auth/sessions/data/routes'

/**
 * Account security: self-service 2FA management (S.1) and active
 * sessions (S.2) for the signed-in admin.
 */
export const Route = createFileRoute('/_authenticated/settings/security')({
  component: SecuritySettingsPage,
})

function SecuritySettingsPage() {
  const { t: t2fa } = useAppTranslation(TwoFactorLocales.TWO_FACTOR)
  const { t: tSessions } = useAppTranslation(SessionsLocales.SESSIONS)

  return (
    <ContentSection
      title={t2fa('page-title')}
      desc={t2fa('page-description')}
      contentClassName='lg:max-w-xl'
    >
      <>
        <TwoFactorSettings />
        <div className='my-8'>
          <h3 className='text-lg font-medium'>{tSessions('section-title')}</h3>
          <Separator className='my-4' />
          <SessionsSection />
        </div>
      </>
    </ContentSection>
  )
}
