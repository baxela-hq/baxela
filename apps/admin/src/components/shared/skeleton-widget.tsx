import { cn } from '@/lib/utils.ts';
import { Skeleton } from '@/components/ui/skeleton';

interface SkeletonWidgetProps {
  className?: string | null | undefined;
}

export function SkeletonWidget( { className }: SkeletonWidgetProps ) {
  return (
    <div className={cn('flex items-center space-x-4', className)}>
      <Skeleton className='h-[125px] w-[250px] rounded-xl' />
      <div className='space-y-2'>
        <Skeleton className='h-4 w-[250px]' />
        <Skeleton className='h-4 w-[200px]' />
      </div>
    </div>
  )
}