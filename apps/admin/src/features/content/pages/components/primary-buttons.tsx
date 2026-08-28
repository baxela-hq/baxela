import { Plus } from 'lucide-react'
import { Button } from '@/components/ui/button';
import { useNavigate } from '@tanstack/react-router'
import { FeatureRoutes, Locales } from '../data/routes.ts'
import { useAppTranslation } from '@/hooks/useAppTranslation';

export function PrimaryButtons() {
  const navigate = useNavigate()
  const { tAction } = useAppTranslation(Locales.SHARED_COMMON)

  return (
    <div className='flex gap-2'>
      <Button className='space-x-1' onClick={() => navigate({ to: FeatureRoutes.CREATE }) }>
        <span>{tAction('create')}</span> <Plus size={18} />
      </Button>
    </div>
  )
}
