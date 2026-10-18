import { Star } from 'lucide-react'
import { cn } from '@/lib/utils'

export function RatingStars({
  rating,
  className,
}: {
  rating: number
  className?: string
}) {
  return (
    <div
      className={cn('flex items-center gap-0.5', className)}
      aria-label={`${rating} / 5`}
    >
      {[1, 2, 3, 4, 5].map((star) => (
        <Star
          key={star}
          className={cn(
            'size-3.5',
            star <= rating
              ? 'fill-amber-400 text-amber-400'
              : 'text-muted-foreground/40'
          )}
        />
      ))}
    </div>
  )
}
