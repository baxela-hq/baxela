import { ArrowLeftIcon, ListCheckIcon, Plus } from 'lucide-react'
import { Button } from '@/components/ui/button';
import { useOptionValues } from './provider.tsx';
import { FeatureRoutes, Locales } from '../data/routes.ts'
import { useNavigate } from '@tanstack/react-router';
import { useAppTranslation } from '@/hooks/useAppTranslation';

export function PrimaryButtons() {
  const { setOpen } = useOptionValues()
  const navigate = useNavigate()
  const { tAction } = useAppTranslation(Locales.SHARED_COMMON)
  const { tLabel } = useAppTranslation(Locales.OPTION_VALUE)


  return (
    <div className='flex gap-2'>
      <Button
        variant='outline'
        className='space-x-1'
        onClick={() => navigate({ to: FeatureRoutes.OPTIONS })}
      >
        <ArrowLeftIcon size={16} />
        <span>{tLabel("options")}</span>
        <ListCheckIcon size={18} />
      </Button>
      <Button className='space-x-1' onClick={() => setOpen('add')}>
        <span>{tAction('create')}</span> <Plus size={18} />
      </Button>
    </div>
  )
}
