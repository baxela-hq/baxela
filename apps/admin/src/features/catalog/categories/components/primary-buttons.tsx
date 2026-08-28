import { Plus } from 'lucide-react'
import { Button } from '@/components/ui/button';
import { useCategories } from './provider.tsx';
import {  Locales } from '../data/routes'
import { useAppTranslation } from '@/hooks/useAppTranslation';

export function PrimaryButtons() {
  const { setOpen } = useCategories()
  const { tAction } = useAppTranslation(Locales.SHARED_COMMON)

  return (
    <div className='flex gap-2'>
      <Button className='space-x-1' onClick={() => setOpen('add')}>
        <span>{tAction('create')}</span> <Plus size={18} />
      </Button>
    </div>
  )
}
