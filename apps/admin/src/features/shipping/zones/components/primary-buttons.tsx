import { Plus } from 'lucide-react'
import { Button } from '@/components/ui/button';
import { useZones } from './provider.tsx';
import {  Locales } from '../data/routes'
import { useAppTranslation } from '@/hooks/useAppTranslation';

export function PrimaryButtons() {
  const { setOpen } = useZones()
  const { tAction } = useAppTranslation(Locales.SHARED_COMMON)

  return (
    <div className='flex gap-2'>
      <Button className='space-x-1' onClick={() => setOpen('add')}>
        <span>{tAction('create')}</span> <Plus size={18} />
      </Button>
    </div>
  )
}
