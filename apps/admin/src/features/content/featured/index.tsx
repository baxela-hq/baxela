import { useMemo, useState } from 'react'
import { pickTranslation } from '@/shared/lib/locale'
import { Save } from 'lucide-react'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import { Button } from '@/components/ui/button'
import { Header } from '@/components/layout/header'
import { HeaderActions } from '@/components/layout/header-actions'
import { Main } from '@/components/layout/main'
import { Search } from '@/components/search'
import { SectionCard } from '@/components/shared/section-card'
import { SkeletonWidget } from '@/components/shared/skeleton-widget'
import { type Post } from '../posts/data/schema'
import { Locales as PostsLocales } from '../posts/data/routes'
import { PostPickerDialog } from './components/post-picker-dialog'
import { Locales } from './data/routes'
import { useFeatured } from './hooks/use-featured'
import { useUpdateFeatured } from './hooks/use-featured-mutations'

/**
 * Featured posts — the storefront featured posts selection.
 * An ordered list: pick posts, reorder with the arrows, then Save
 * (full sync, array order encodes position).
 */
export function Featured() {
  const { tPageTitle, tLabel, tHelpText } = useAppTranslation(Locales.FEATURED)
  const { tAction } = useAppTranslation(Locales.SHARED_COMMON)
  const { tStatus: tPostStatus } = useAppTranslation(PostsLocales.POST)
  const { data, isLoading } = useFeatured()
  const updateFeatured = useUpdateFeatured()

  const [posts, setPosts] = useState<Post[]>([])
  const [syncedPosts, setSyncedPosts] = useState<Post[] | null>(null)
  const [postPickerOpen, setPostPickerOpen] = useState(false)

  // Re-sync the editable list whenever fresh server data arrives (initial
  // load and post-save refetch) — setState during render, per React's
  // "adjust state when props change" pattern.
  if (data && data.post !== syncedPosts) {
    setPosts(data.post)
    setSyncedPosts(data.post)
  }

  const savedPostIds = useMemo(
    () => (data?.post ?? []).map((post) => post.id).join(','),
    [data]
  )

  const isDirty = posts.map((post) => post.id).join(',') !== savedPostIds

  const moveItem = (id: number, direction: -1 | 1): void => {
    const index = posts.findIndex((post) => post.id === id)
    const target = index + direction
    if (index === -1 || target < 0 || target >= posts.length) return

    const next = [...posts]
    ;[next[index], next[target]] = [next[target], next[index]]

    setPosts(next)
  }

  const handleSave = () => {
    updateFeatured.mutate({
      post_ids: posts.map((post) => post.id),
    })
  }

  const postLabel = (post: Post) => ({
    title: pickTranslation(post.translations)?.title ?? `#${post.id}`,
    meta: tPostStatus(`status.${post.status}`),
  })

  if (isLoading) {
    return (
      <>
        <Header fixed>
          <Search />
          <HeaderActions />
        </Header>
        <Main>
          <SkeletonWidget />
        </Main>
      </>
    )
  }

  return (
    <>
      <Header fixed>
        <Search />
        <HeaderActions />
      </Header>

      <Main className='flex flex-1 flex-col gap-4 sm:gap-6'>
        <div className='flex flex-wrap items-end justify-between gap-2'>
          <div>
            <h2 className='text-2xl font-bold tracking-tight'>
              {tPageTitle('index.title')}
            </h2>
            <p className='text-muted-foreground'>
              {tPageTitle('index.subtitle')}
            </p>
          </div>
          <Button
            type='button'
            disabled={!isDirty || updateFeatured.isPending}
            onClick={handleSave}
          >
            <Save />
            {tAction('save')}
          </Button>
        </div>

        <SectionCard
          title={tLabel('posts-section')}
          description={tHelpText('posts-section')}
          addLabel={tLabel('add-posts')}
          emptyLabel={tHelpText('empty-posts')}
          items={posts}
          labelOf={postLabel}
          onAdd={() => setPostPickerOpen(true)}
          onRemove={(id) =>
            setPosts((prev) => prev.filter((post) => post.id !== id))
          }
          onMove={(id, direction) => moveItem(id, direction)}
        />
      </Main>

      <PostPickerDialog
        open={postPickerOpen}
        onOpenChange={setPostPickerOpen}
        selectedIds={posts.map((post) => post.id)}
        onConfirm={(picked) =>
          setPosts((prev) => [
            ...prev,
            ...picked.filter(
              (post) => !prev.some((existing) => existing.id === post.id)
            ),
          ])
        }
      />
    </>
  )
}
