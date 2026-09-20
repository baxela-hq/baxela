import { useAppTranslation } from '@/hooks/useAppTranslation'
import { PushSettingsCard } from '@/features/notification/components/push-settings-card'
import { Locales } from '@/features/notification/data/routes'
import { ContentSection } from '../components/content-section'

export function SettingsNotifications() {
  const { t } = useAppTranslation(Locales.NOTIFICATION)

  return (
    <ContentSection title={t('settings.title')} desc={t('settings.desc')}>
      <div className='space-y-4'>
        <PushSettingsCard />
      </div>
    </ContentSection>
  )
}
