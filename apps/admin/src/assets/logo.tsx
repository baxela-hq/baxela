import { cn } from '@/lib/utils'

/**
 * Baxela brand mark — the parcel: an isometric box built from flat
 * stripes (hexagon frame + two kinked tape bands) in Persian turquoise.
 */
function Parcel({ className }: { className?: string }) {
  return (
    <svg
      viewBox="0 0 96 96"
      fill="none"
      xmlns="http://www.w3.org/2000/svg"
      aria-hidden='true'
      className={className}
    >
      <path
        fill='#16A8B1'
        fillRule='evenodd'
        d='M 48,6 L 90,29 L 90,68 L 48,91 L 4,68 L 4,29 Z M 48,17.3 L 78.9,34.2 L 78.9,62.8 L 48,79.7 L 17.1,62.8 L 17.1,34.2 Z'
      />
      <path fill='#16A8B1' d='M 4,38 L 48,61 L 90,38 L 90,47 L 48,70 L 4,47 Z' />
      <path fill='#16A8B1' d='M 4,53 L 48,76 L 90,53 L 90,62 L 48,85 L 4,62 Z' />
    </svg>
  )
}

export function Logo({ className }: { className?: string }) {
  return <Parcel className={cn('size-8', className)} />
}

export function BaxelaMark({ className }: { className?: string }) {
  return <Parcel className={cn('size-5', className)} />
}
