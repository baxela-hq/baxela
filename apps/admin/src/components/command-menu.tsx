import React from 'react'
import { useNavigate } from '@tanstack/react-router'
import { ArrowRight, ChevronRight, Laptop, Moon, Sun } from 'lucide-react'
import { useSearch } from '@/context/search-provider'
import { useTheme } from '@/context/theme-provider'
import {
  CommandDialog,
  CommandEmpty,
  CommandGroup,
  CommandInput,
  CommandItem,
  CommandList,
  CommandSeparator,
} from '@/components/ui/command'
import { ScrollArea } from './ui/scroll-area'
import { useSidebarData } from './layout/data/sidebar-data'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import {
  useCommandEntitySearch,
  type CommandEntityGroup,
} from '@/shared/hooks/use-command-entity-search'

const SEARCH_DEBOUNCE_MS = 300

export function CommandMenu() {
  const navigate = useNavigate()
  const { setTheme } = useTheme()
  const { open, setOpen } = useSearch()
  const sidebarData  =  useSidebarData()
  const { t } = useAppTranslation('shared/layout')

  const [input, setInput] = React.useState('')
  const [debouncedInput, setDebouncedInput] = React.useState('')

  React.useEffect(() => {
    const timer = setTimeout(() => setDebouncedInput(input), SEARCH_DEBOUNCE_MS)
    return () => clearTimeout(timer)
  }, [input])

  // reset both when the palette closes so reopening starts clean
  React.useEffect(() => {
    if (!open) {
      setInput('')
      setDebouncedInput('')
    }
  }, [open])

  const entitySearch = useCommandEntitySearch(debouncedInput)

  const runCommand = React.useCallback(
    (command: () => unknown) => {
      setOpen(false)
      command()
    },
    [setOpen]
  )

  const entityGroupLabel = (group: CommandEntityGroup) =>
    group === 'products' ? t('command-menu.products-heading') : t('command-menu.orders-heading')

  return (
    <CommandDialog modal open={open} onOpenChange={setOpen}>
      <CommandInput
        placeholder={t('search.command_input')}
        value={input}
        onValueChange={setInput}
      />
      <CommandList>
        <ScrollArea type='hover' className='h-72 pe-1'>
          <CommandEmpty>{t('command-menu.no-results')}</CommandEmpty>
          {entitySearch.enabled && entitySearch.results.length > 0 && (
            <>
              <CommandGroup heading={t('command-menu.records-heading')}>
                {entitySearch.results.map((result) => (
                  <CommandItem
                    key={`${result.group}-${result.id}`}
                    value={`${result.group}-${result.id} ${result.label}`}
                    // server matches (e.g. another language's title) must
                    // survive cmdk's client-side filter for the typed query
                    keywords={[debouncedInput]}
                    onSelect={() => {
                      runCommand(() => navigate({ to: result.to }))
                    }}
                  >
                    <div className='flex size-4 items-center justify-center'>
                      <ArrowRight className='size-2 text-muted-foreground/80' />
                    </div>
                    <span className='text-muted-foreground text-xs'>
                      {entityGroupLabel(result.group)}
                    </span>
                    {result.label}
                  </CommandItem>
                ))}
              </CommandGroup>
              <CommandSeparator />
            </>
          )}
          {sidebarData.navGroups.map((group) => (
            <CommandGroup key={group.title} heading={group.title}>
              {group.items.map((navItem, i) => {
                if (navItem.url)
                  return (
                    <CommandItem
                      key={`${navItem.url}-${i}`}
                      value={navItem.title}
                      onSelect={() => {
                        runCommand(() => navigate({ to: navItem.url }))
                      }}
                    >
                      <div className='flex size-4 items-center justify-center'>
                        <ArrowRight className='size-2 text-muted-foreground/80' />
                      </div>
                      {navItem.title}
                    </CommandItem>
                  )

                return navItem.items?.map((subItem, i) => (
                  <CommandItem
                    key={`${navItem.title}-${subItem.url}-${i}`}
                    value={`${navItem.title}-${subItem.url}`}
                    onSelect={() => {
                      runCommand(() => navigate({ to: subItem.url }))
                    }}
                  >
                    <div className='flex size-4 items-center justify-center'>
                      <ArrowRight className='size-2 text-muted-foreground/80' />
                    </div>
                    {navItem.title} <ChevronRight /> {subItem.title}
                  </CommandItem>
                ))
              })}
            </CommandGroup>
          ))}
          <CommandSeparator />
          <CommandGroup heading={t('command-menu.theme-heading')}>
            <CommandItem onSelect={() => runCommand(() => setTheme('light'))}>
              <Sun /> <span>{t('toolbar.theme.light')}</span>
            </CommandItem>
            <CommandItem onSelect={() => runCommand(() => setTheme('dark'))}>
              <Moon className='scale-90' />
              <span>{t('toolbar.theme.dark')}</span>
            </CommandItem>
            <CommandItem onSelect={() => runCommand(() => setTheme('system'))}>
              <Laptop />
              <span>{t('toolbar.theme.system')}</span>
            </CommandItem>
          </CommandGroup>
        </ScrollArea>
      </CommandList>
    </CommandDialog>
  )
}
