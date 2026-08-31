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
  type ProductComment,
  type ProductCommentReplyForm,
  replyFormSchema,
} from '../data/schema'
import { useReplyToProductComment } from '../hooks/use-product-comment-mutations'

type ReplyDrawerProps = {
  open: boolean
  onOpenChange: (open: boolean) => void
  currentRow: ProductComment
}

export function ReplyDrawer({
  open,
  onOpenChange,
  currentRow,
}: ReplyDrawerProps) {
  const { tAction, tPlaceHolder } = useAppTranslation(Locales.SHARED_COMMON)
  const { tLabel } = useAppTranslation(Locales.PRODUCT_COMMENT)

  const form = useForm<ProductCommentReplyForm>({
    resolver: zodResolver(replyFormSchema),
    defaultValues: { body: '' },
  })

  const replyToProductComment = useReplyToProductComment()

  const onSubmit = (data: ProductCommentReplyForm) => {
    replyToProductComment.mutate(
      {
        data: {
          product_id: currentRow.product_id,
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

  const productTitle =
    pickTranslation(currentRow.product?.translations ?? [])?.title ?? '—'

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
            id='product-comment-reply-form'
            onSubmit={form.handleSubmit(onSubmit)}
            className='flex-1 space-y-6 overflow-y-auto px-4'
          >
            <div className='space-y-1 rounded-md border bg-muted/40 p-4 text-sm'>
              <p className='font-medium'>
                {currentRow.user?.name ?? tLabel('anonymous')}
                <span className='text-muted-foreground'>
                  {' · '}
                  {productTitle}
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
          <Button form='product-comment-reply-form' type='submit'>
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
