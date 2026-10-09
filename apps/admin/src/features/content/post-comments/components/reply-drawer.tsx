import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import { Button } from '@/components/ui/button'
import {
  Form,
  FormControl,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from '@/components/ui/form'
import { Textarea } from '@/components/ui/textarea'
import {
  Sheet,
  SheetClose,
  SheetContent,
  SheetDescription,
  SheetFooter,
  SheetHeader,
  SheetTitle,
} from '@/components/ui/sheet'
import { pickTranslation } from '@/shared/lib/locale'
import { Locales } from '../data/routes'
import {
  type PostComment,
  type PostCommentReplyForm,
  replyFormSchema,
} from '../data/schema'
import { useReplyToPostComment } from '../hooks/use-post-comment-mutations'

type ReplyDrawerProps = {
  open: boolean
  onOpenChange: (open: boolean) => void
  currentRow: PostComment
}

export function ReplyDrawer({
  open,
  onOpenChange,
  currentRow,
}: ReplyDrawerProps) {
  const { tAction, tPlaceHolder } = useAppTranslation(Locales.SHARED_COMMON)
  const { tLabel } = useAppTranslation(Locales.POST_COMMENT)

  const form = useForm<PostCommentReplyForm>({
    resolver: zodResolver(replyFormSchema),
    defaultValues: { body: '' },
  })

  const replyToPostComment = useReplyToPostComment()

  const onSubmit = (data: PostCommentReplyForm) => {
    replyToPostComment.mutate(
      {
        data: {
          post_id: currentRow.post_id,
          parent_id: currentRow.id,
          body: data.body,
        },
      },
      {
        onSuccess: () => {
          onOpenChange(false)
          form.reset()
        },
      }
    )
  }

  const postTitle =
    pickTranslation(currentRow.post?.translations ?? [])?.title ?? '—'

  return (
    <Sheet
      open={open}
      onOpenChange={(v) => {
        onOpenChange(v)
        form.reset()
      }}
    >
      <SheetContent className='flex flex-col'>
        <SheetHeader className='text-start'>
          <SheetTitle>{tLabel('reply_title')}</SheetTitle>
          <SheetDescription>{tLabel('reply_subtitle')}</SheetDescription>
        </SheetHeader>
        <Form {...form}>
          <form
            id='post-comment-reply-form'
            onSubmit={form.handleSubmit(onSubmit)}
            className='flex-1 space-y-6 overflow-y-auto px-4'
          >
            <div className='space-y-1 rounded-md border bg-muted/40 p-4 text-sm'>
              <p className='font-medium'>
                {currentRow.user?.name ?? tLabel('anonymous')}
                <span className='text-muted-foreground'>
                  {' · '}
                  {postTitle}
                </span>
              </p>
              <p className='whitespace-pre-line text-muted-foreground'>
                {currentRow.body}
              </p>
            </div>
            <FormField
              control={form.control}
              name='body'
              render={({ field }) => (
                <FormItem>
                  <FormLabel>{tLabel('reply')}</FormLabel>
                  <FormControl>
                    <Textarea
                      rows={6}
                      {...field}
                      placeholder={tPlaceHolder('textarea')}
                    />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />
          </form>
        </Form>
        <SheetFooter className='gap-2'>
          <Button form='post-comment-reply-form' type='submit'>
            {tAction('submit')}
          </Button>
          <SheetClose asChild>
            <Button variant='outline'>{tAction('close')}</Button>
          </SheetClose>
        </SheetFooter>
      </SheetContent>
    </Sheet>
  )
}
