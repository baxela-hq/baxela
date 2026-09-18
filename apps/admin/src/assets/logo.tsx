import { cn } from '@/lib/utils'

export function Logo({ className }: { className?: string }) {
  return (
    <span
      aria-hidden='true'
      className={cn(
        'grid size-8 place-items-center rounded-lg bg-primary text-lg font-bold leading-none text-primary-foreground',
        className
      )}
    >
      B
    </span>
  )
}

export function BaxelaMark({ className }: { className?: string }) {
  return (
    <span className={cn('text-base font-bold leading-none', className)}>B</span>
  )
}
