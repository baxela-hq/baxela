import { Toaster as Sonner, ToasterProps } from 'sonner'
import { useDirection } from '@/context/direction-provider'
import { useTheme } from '@/context/theme-provider'

export function Toaster({ ...props }: ToasterProps) {
  const { theme = 'system' } = useTheme()
  const { dir } = useDirection()

  return (
    <Sonner
      theme={theme as ToasterProps['theme']}
      dir={dir}
      className='toaster group [&_div[data-content]]:w-full'
      style={
        {
          '--normal-bg': 'var(--popover)',
          '--normal-text': 'var(--popover-foreground)',
          '--normal-border': 'var(--border)',
        } as React.CSSProperties
      }
      {...props}
    />
  )
}
