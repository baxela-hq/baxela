import { ArrowLeftIcon, ListCheckIcon, ListTree, Plus } from 'lucide-react'
import { Button } from '@/components/ui/button';
import { useMenuLinks } from './provider.tsx';
import { FeatureRoutes, Locales } from '../data/routes'
import { useNavigate } from '@tanstack/react-router';
import { useAppTranslation } from '@/hooks/useAppTranslation';

export function PrimaryButtons() {
  const { setOpen } = useMenuLinks()
  const navigate = useNavigate()
  const { tAction } = useAppTranslation(Locales.SHARED_COMMON)
  const { tLabel } = useAppTranslation(Locales.MENU_LINK)


  return (
    <div className='flex gap-2'>
      <Button
        variant='outline'
        className='space-x-1'
        onClick={() => navigate({ to: FeatureRoutes.MENUS })}
      >
        <ArrowLeftIcon size={16} />
        <span>{tLabel("menus")}</span>
        <ListCheckIcon size={18} />
      </Button>
      <Button
        variant='outline'
        className='space-x-1'
        onClick={() => setOpen('sort')}
      >
        <span>{tLabel('sort')}</span>
        <ListTree size={18} />
      </Button>
      <Button className='space-x-1' onClick={() => setOpen('add')}>
        <span>{tAction('create')}</span> <Plus size={18} />
      </Button>
    </div>
  )
}
