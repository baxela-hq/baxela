import { useState } from 'react'
import { CheckIcon, ChevronsUpDownIcon } from 'lucide-react'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import { Button } from '@/components/ui/button'
import {
  Command,
  CommandEmpty,
  CommandGroup,
  CommandInput,
  CommandItem,
  CommandList,
} from '@/components/ui/command'
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from '@/components/ui/popover'
import { cn } from '@/lib/utils'
import { Locales } from '../data/routes'

/**
 * Searchable timezone picker — a Popover + Command combobox instead of a
 * plain Select, which becomes unusable once the full PHP timezone list
 * (hundreds of identifiers across regions) is rendered.
 */
type TimezoneSelectProps = {
  timezones: Record<string, string[]>
  value?: string
  onValueChange: (value: string) => void
  disabled?: boolean
  id?: string
}

export function TimezoneSelect({
  timezones,
  value,
  onValueChange,
  disabled,
  id,
}: TimezoneSelectProps) {
  const { tPlaceHolder } = useAppTranslation(Locales.SHARED_COMMON)
  const { t } = useAppTranslation(Locales.SHARED_DATA_TABLE)
  const [open, setOpen] = useState(false)

  return (
    <Popover open={open} onOpenChange={setOpen}>
      <PopoverTrigger asChild>
        <Button
          id={id}
          type='button'
          variant='outline'
          role='combobox'
          aria-expanded={open}
          disabled={disabled}
          className='h-9 w-full justify-between px-3 py-2 font-normal shadow-xs aria-invalid:border-destructive aria-invalid:ring-destructive/20 dark:bg-input/30 dark:hover:bg-input/50'
        >
          <span
            className={cn('line-clamp-1', !value && 'text-muted-foreground')}
          >
            {value ?? tPlaceHolder('select')}
          </span>
          <ChevronsUpDownIcon className='size-4 opacity-50' />
        </Button>
      </PopoverTrigger>
      <PopoverContent
        className='w-(--radix-popover-trigger-width) p-0'
        align='start'
      >
        <Command>
          <CommandInput placeholder={tPlaceHolder('search')} />
          <CommandList className='max-h-64'>
            <CommandEmpty>{t('no_results')}</CommandEmpty>
            {Object.entries(timezones).map(([region, identifiers]) => (
              <CommandGroup key={region} heading={region}>
                {identifiers.map((identifier) => (
                  <CommandItem
                    key={identifier}
                    value={identifier}
                    // cmdk scores against the raw value only — offer a
                    // space-separated variant so "New York" matches
                    // "America/New_York".
                    keywords={[identifier.replace(/[_/]/g, ' ')]}
                    onSelect={() => {
                      onValueChange(identifier)
                      setOpen(false)
                    }}
                  >
                    <CheckIcon
                      className={cn(
                        'size-4',
                        value === identifier ? 'opacity-100' : 'opacity-0'
                      )}
                    />
                    {identifier}
                  </CommandItem>
                ))}
              </CommandGroup>
            ))}
          </CommandList>
        </Command>
      </PopoverContent>
    </Popover>
  )
}
