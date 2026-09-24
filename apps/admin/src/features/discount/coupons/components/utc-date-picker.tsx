import { format } from 'date-fns'
import { Calendar as CalendarIcon } from 'lucide-react'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import { Button } from '@/components/ui/button'
import { Calendar } from '@/components/ui/calendar'
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from '@/components/ui/popover'
import { Locales } from '../data/routes'

/**
 * Unrestricted single-date picker for coupon validity bounds. Values are
 * UTC instants end to end: what the admin picks is what the API stores.
 */
type UtcDatePickerProps = {
  selected: Date | undefined
  onSelect: (date: Date | undefined) => void
  placeholder: string
}

export function UtcDatePicker({
  selected,
  onSelect,
  placeholder,
}: UtcDatePickerProps) {
  const { tAction } = useAppTranslation(Locales.SHARED_COMMON)

  return (
    <Popover>
      <PopoverTrigger asChild>
        <Button
          variant='outline'
          data-empty={!selected}
          className='w-full justify-start text-start font-normal data-[empty=true]:text-muted-foreground'
        >
          {selected ? format(selected, 'MMM d, yyyy') : <span>{placeholder}</span>}
          <CalendarIcon className='ms-auto h-4 w-4 opacity-50' />
        </Button>
      </PopoverTrigger>
      <PopoverContent className='w-auto p-0' align='start'>
        <Calendar
          mode='single'
          captionLayout='dropdown'
          selected={selected}
          defaultMonth={selected}
          onSelect={onSelect}
        />
        {selected && (
          <div className='border-t p-2'>
            <Button
              variant='ghost'
              size='sm'
              className='w-full'
              onClick={() => onSelect(undefined)}
            >
              {tAction('clear')}
            </Button>
          </div>
        )}
      </PopoverContent>
    </Popover>
  )
}
