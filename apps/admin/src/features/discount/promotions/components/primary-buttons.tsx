import { Plus } from 'lucide-react'
import { useNavigate } from '@tanstack/react-router'
import { Button } from '@/components/ui/button';
import { Locales } from '../data/routes'
import { useAppTranslation } from '@/hooks/useAppTranslation';

export function PrimaryButtons() {
  const navigate = useNavigate()
  const { tAction } = useAppTranslation(Locales.SHARED_COMMON)

  return (
    <div className='flex gap-2'>
      <Button
        className='space-x-1'
        onClick={() => navigate({ to: '/discount/promotions/create' })}
      >
        <span>{tAction('create')}</span> <Plus size={18} />
      </Button>
    </div>
  )
}
