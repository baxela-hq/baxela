import { createFileRoute } from '@tanstack/react-router'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import { ContentSection } from '@/features/settings/components/content-section'
import { TwoFactorSettings } from '@/features/auth/two-factor/components/two-factor-settings'
import { Locales as TwoFactorLocales } from '@/features/auth/two-factor/data/routes'

/**
 * Account security (S.1): self-service 2FA management for the signed-in
 * admin — enrollment, disable, recovery-code regeneration.
 */
export const Route = createFileRoute('/_authenticated/settings/security')({
  component: SecuritySettingsPage,
})

function SecuritySettingsPage() {
  const { t } = useAppTranslation(TwoFactorLocales.TWO_FACTOR)

  return (
    <ContentSection
      title={t('page-title')}
      desc={t('page-description')}
      contentClassName='lg:max-w-xl'
    >
      <TwoFactorSettings />
    </ContentSection>
  )
}
