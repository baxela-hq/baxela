import { DotsHorizontalIcon } from '@radix-ui/react-icons'
import { type Row } from '@tanstack/react-table'
import { PencilIcon, EyeIcon } from 'lucide-react'
import { Button } from '@/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuShortcut,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { type Order } from '../data/schema'
import { useOrders } from './provider.tsx'
import { Locales, FeatureRoutes } from '../data/routes';
import { useAppTranslation } from '@/hooks/useAppTranslation';
import { useNavigate } from '@tanstack/react-router'

type DataTableRowActionsProps = {
  row: Row<Order>
}

export function DataTableRowActions({ row }: DataTableRowActionsProps) {
  const { setOpen, setCurrentRow } = useOrders()
  const navigate = useNavigate()
  const { tAction } = useAppTranslation(Locales.SHARED_COMMON)

  return (
    <>
      <DropdownMenu modal={false}>
        <DropdownMenuTrigger asChild>
          <Button
            variant='ghost'
            className='flex h-8 w-8 p-0 data-[state=open]:bg-muted'
          >
            <DotsHorizontalIcon className='h-4 w-4' />
            <span className='sr-only'>Open menu</span>
          </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align='end' className='w-[160px]'>
          <DropdownMenuItem
            onClick={() => {
              setCurrentRow(row.original)
              setOpen('edit')
            }}
          >
            {tAction('edit')}
            <DropdownMenuShortcut>
              <PencilIcon size={16} />
            </DropdownMenuShortcut>
          </DropdownMenuItem>
          <DropdownMenuItem
            onClick={() => {
              const redirectUrl = FeatureRoutes.SHOW.replace('$id', row.original.id.toString())
              navigate({ to: redirectUrl })
            }}
          >
            {tAction('show_details')}
            <DropdownMenuShortcut>
              <EyeIcon size={16} />
            </DropdownMenuShortcut>
          </DropdownMenuItem>
          <DropdownMenuSeparator />
        </DropdownMenuContent>
      </DropdownMenu>
    </>
  )
}
