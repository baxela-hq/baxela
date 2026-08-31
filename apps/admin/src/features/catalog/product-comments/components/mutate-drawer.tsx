import { useEffect } from 'react'
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
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
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
import { Locales } from '../data/routes'
import {
  type ProductComment,
  type ProductCommentEditForm,
  editFormSchema,
} from '../data/schema'
import { useUpdateProductComment } from '../hooks/use-product-comment-mutations'

type MutateDrawerProps = {
  open: boolean
  onOpenChange: (open: boolean) => void
  currentRow: ProductComment
}

export function MutateDrawer({
  open,
  onOpenChange,
  currentRow,
}: MutateDrawerProps) {
  const { tAction, tPageTitle, tPlaceHolder } = useAppTranslation(
    Locales.SHARED_COMMON
  )
  const { tLabel, tStatus } = useAppTranslation(Locales.PRODUCT_COMMENT)

  const entityName = { singular: tLabel('comment') }

  const form = useForm<ProductCommentEditForm>({
    resolver: zodResolver(editFormSchema),
    defaultValues: {
      body: currentRow.body,
      status: currentRow.status,
    },
  })

  useEffect(() => {
    form.reset({
      body: currentRow.body,
      status: currentRow.status,
    })
  }, [currentRow]) // eslint-disable-line react-hooks/exhaustive-deps

  const updateProductComment = useUpdateProductComment()

  const onSubmit = (data: ProductCommentEditForm) => {
    updateProductComment.mutate(
      {
        id: currentRow.id.toString(),
        data: {
          product_id: currentRow.product_id,
          parent_id: currentRow.parent_id,
          body: data.body,
          status: data.status,
        },
      },
      {
        onSuccess: () => {
          onOpenChange(false)
        },
      }
    )
  }

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
          <SheetTitle>
            {tPageTitle('form.title_edit', {
              entity: entityName.singular,
              id: currentRow.id.toString(),
            })}
          </SheetTitle>
          <SheetDescription>{tPageTitle('form.subtitle')}</SheetDescription>
        </SheetHeader>
        <Form {...form}>
          <form
            id='product-comment-edit-form'
            onSubmit={form.handleSubmit(onSubmit)}
            className='flex-1 space-y-6 overflow-y-auto px-4'
          >
            <FormField
              control={form.control}
              name='body'
              render={({ field }) => (
                <FormItem>
                  <FormLabel>{tLabel('body')}</FormLabel>
                  <FormControl>
                    <Textarea
                      rows={8}
                      {...field}
                      placeholder={tPlaceHolder('textarea')}
                    />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />
            <FormField
              control={form.control}
              name='status'
              render={({ field }) => (
                <FormItem>
                  <FormLabel>{tLabel('status')}</FormLabel>
                  <Select onValueChange={field.onChange} value={field.value}>
                    <FormControl>
                      <SelectTrigger className='w-full'>
                        <SelectValue
                          placeholder={tPlaceHolder('select')}
                        />
                      </SelectTrigger>
                    </FormControl>
                    <SelectContent>
                      <SelectItem value='pending'>
                        {tStatus('status.pending')}
                      </SelectItem>
                      <SelectItem value='approved'>
                        {tStatus('status.approved')}
                      </SelectItem>
                      <SelectItem value='rejected'>
                        {tStatus('status.rejected')}
                      </SelectItem>
                    </SelectContent>
                  </Select>
                  <FormMessage />
                </FormItem>
              )}
            />
          </form>
        </Form>
        <SheetFooter className='gap-2'>
          <Button form='product-comment-edit-form' type='submit'>
            {tAction('save')}
          </Button>
          <SheetClose asChild>
            <Button variant='outline'>{tAction('close')}</Button>
          </SheetClose>
        </SheetFooter>
      </SheetContent>
    </Sheet>
  )
}
