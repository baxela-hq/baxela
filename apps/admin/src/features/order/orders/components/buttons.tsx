import { useNavigate } from '@tanstack/react-router';
import { ListCheckIcon, PencilIcon } from 'lucide-react';
import { useAppTranslation } from '@/hooks/useAppTranslation';
import { Button } from '@/components/ui/button'
import { Locales, FeatureRoutes } from '../data/routes.ts'
import { useOrders } from './provider.tsx';
import { type Order } from '../data/schema.ts'

type ButtonsProps = {
  entityName: {singular: string, plural: string},
  record: Order
}

export function Buttons({entityName, record}: ButtonsProps) {
  const { setOpen, setCurrentRow } = useOrders()
  const navigate = useNavigate()
  const { tAction } = useAppTranslation(Locales.SHARED_COMMON)

  return (
    <div className='flex gap-2'>
      <Button
        variant='outline'
        className='space-x-1'
        onClick={() => {
          setCurrentRow(record)
          setOpen('edit')
        }}
      >
        <span>{tAction('edit')}</span> <PencilIcon size={18} />
      </Button>
      <Button
        variant='outline'
        className='space-x-1'
        onClick={() => navigate({ to: FeatureRoutes.LIST })}
      >
        <span>{entityName.plural}</span> <ListCheckIcon size={18} />
      </Button>
    </div>
  )
}
