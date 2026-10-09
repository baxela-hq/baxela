import { Globe } from 'lucide-react'

const TITLE_LIMIT = 60
const DESCRIPTION_LIMIT = 160

type SerpPreviewProps = {
  title?: string | null
  description?: string | null
  url?: string | null
  siteName?: string | null
}

function truncate(value: string, limit: number): string {
  if (value.length <= limit) return value
  return `${value.slice(0, limit).trimEnd()}…`
}

/**
 * Read-only Google search-result preview: renders how the page's title,
 * url and meta description would appear in the search results, honoring
 * Google's practical title (60) and snippet (160) character limits.
 */
export function SerpPreview({ title, description, url, siteName }: SerpPreviewProps) {
  const storeUrl: string = import.meta.env.VITE_STORE_FRONT_URL ?? ''
  let host = storeUrl
  try {
    host = new URL(storeUrl).hostname
  } catch {
    host = storeUrl
  }

  const displayUrl = (url ?? '').trim() || storeUrl

  const trimmedTitle = (title ?? '').trim()
  const trimmedDescription = (description ?? '').trim()

  return (
    <div className='space-y-2 rounded-md border bg-muted/30 p-4'>
      <div className='flex items-center gap-2'>
        <span className='flex size-6 shrink-0 items-center justify-center rounded-full border bg-background'>
          <Globe size={14} className='text-muted-foreground' />
        </span>
        <span className='truncate text-xs leading-4 text-[#4d5156] dark:text-[#bdc1c6]'>
          {siteName ?? host}
        </span>
      </div>
      {/* the full post url, green like Google's result links; urls are
          always LTR, even when the admin UI is RTL */}
      <div dir='ltr' className='truncate text-xs leading-5 text-[#006621] dark:text-[#57c978]'>
        {displayUrl}
      </div>
      <p className='text-base leading-6 text-[#1a0dab] dark:text-[#8ab4f8]'>
        {trimmedTitle ? truncate(trimmedTitle, TITLE_LIMIT) : '—'}
      </p>
      <p className='line-clamp-2 text-sm leading-5 text-[#4d5156] dark:text-[#bdc1c6]'>
        {trimmedDescription ? truncate(trimmedDescription, DESCRIPTION_LIMIT) : '—'}
      </p>
    </div>
  )
}
