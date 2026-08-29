import { useEffect } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { ApiError } from '@/shared/lib/api-error'
import { parseAndToastError } from '@/shared/lib/utils'
import { Loader2 } from 'lucide-react'
import { toast } from 'sonner'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import {
  Form,
  FormControl,
  FormDescription,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from '@/components/ui/form'
import {
  InputGroup,
  InputGroupAddon,
  InputGroupInput,
  InputGroupText,
} from '@/components/ui/input-group'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { updateMedia } from '../api/media.api'
import { Locales } from '../data/routes'
import {
  buildMediaEditValues,
  buildMediaFormSchema,
  buildMediaUpdatePayload,
  getDisplayName,
  type MediaForm,
  type MediaItem,
} from '../data/schema'
import { useInvalidateMedia } from '../hooks/use-media-actions'
import { useFolderTree } from '../hooks/use-media-library'

type MediaMutateDialogProps = {
  open: boolean
  onOpenChange: (open: boolean) => void
  currentRow: MediaItem
}

export function MediaMutateDialog({
  open,
  onOpenChange,
  currentRow,
}: MediaMutateDialogProps) {
  const invalidateMedia = useInvalidateMedia()
  // Generic texts (actions, titles, record messages) come from common;
  // only media-specific labels/placeholders/help texts live in media
  const { tAction, tPlaceHolder, tMessage, tPageTitle } = useAppTranslation(
    Locales.SHARED_COMMON
  )
  const {
    tLabel,
    tHelpText,
    tPlaceHolder: mediaTPlaceHolder,
  } = useAppTranslation(Locales.MEDIA)
  const entityName = tLabel('file')

  const folderTree = useFolderTree(null)

  // Fixed extension shown outside the input ('' when the file has none)
  const extSuffix = currentRow.extension?.trim()
    ? `.${currentRow.extension.trim()}`
    : ''

  const form = useForm<MediaForm>({
    resolver: zodResolver(buildMediaFormSchema(tMessage('error.invalid_name'))),
    defaultValues: buildMediaEditValues(currentRow),
  })

  useEffect(() => {
    if (open) {
      form.reset(buildMediaEditValues(currentRow))
    }
  }, [open, currentRow]) // eslint-disable-line react-hooks/exhaustive-deps

  const onSubmit = async (data: MediaForm) => {
    // Send only changed fields (see buildMediaUpdatePayload)
    const original = buildMediaEditValues(currentRow)
    const payload = buildMediaUpdatePayload(data, original, extSuffix)

    if (Object.keys(payload).length === 0) {
      onOpenChange(false)
      return
    }

    try {
      await updateMedia(currentRow.id, payload)
      toast.success(
        tMessage('success.record.updated', { name: getDisplayName(currentRow) })
      )
      await invalidateMedia()
      onOpenChange(false)
      form.reset()
    } catch (err: unknown) {
      if (err instanceof ApiError) {
        parseAndToastError(err)
      } else {
        toast.error(tMessage('error.general'))
      }
    }
  }

  return (
    <Dialog
      open={open}
      onOpenChange={(state) => {
        onOpenChange(state)
        form.reset()
      }}
    >
      <DialogContent className='sm:max-w-md'>
        <DialogHeader className='text-start'>
          <DialogTitle>
            {tPageTitle('form.title_edit', {
              entity: entityName,
              id: currentRow.id.toString(),
            })}
          </DialogTitle>
          <DialogDescription>{tPageTitle('form.subtitle')}</DialogDescription>
        </DialogHeader>
        <Form {...form}>
          <form
            id='media-edit-form'
            onSubmit={form.handleSubmit(onSubmit)}
            className='space-y-4'
          >
            <FormField
              control={form.control}
              name='name'
              render={({ field }) => (
                <FormItem>
                  <FormLabel>{tLabel('name')}</FormLabel>
                  <FormControl>
                    <InputGroup>
                      <InputGroupInput
                        {...field}
                        placeholder={mediaTPlaceHolder('media_name')}
                      />
                      {extSuffix && (
                        <InputGroupAddon align='inline-end'>
                          <InputGroupText>{extSuffix}</InputGroupText>
                        </InputGroupAddon>
                      )}
                    </InputGroup>
                  </FormControl>
                  <FormMessage />
                  <FormDescription>
                    {tHelpText('media_name', { filename: currentRow.filename })}
                  </FormDescription>
                </FormItem>
              )}
            />

            <FormField
              control={form.control}
              name='folder_id'
              render={({ field }) => (
                <FormItem>
                  <FormLabel>{tLabel('folder')}</FormLabel>
                  <Select
                    onValueChange={(value) =>
                      field.onChange(value === 'none' ? null : Number(value))
                    }
                    value={field.value !== null ? String(field.value) : 'none'}
                  >
                    <FormControl>
                      <SelectTrigger className='w-full'>
                        <SelectValue placeholder={tPlaceHolder('select')} />
                      </SelectTrigger>
                    </FormControl>
                    <SelectContent>
                      <SelectItem value='none'>{tLabel('none')}</SelectItem>
                      {folderTree.map((folder) => (
                        <SelectItem
                          key={folder.id}
                          value={String(folder.id)}
                          style={{
                            paddingInlineStart: `${folder.depth * 1.25 + 0.5}rem`,
                          }}
                        >
                          {folder.name}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                  <FormMessage />
                  <FormDescription>{tHelpText('folder_id')}</FormDescription>
                </FormItem>
              )}
            />
          </form>
        </Form>
        <DialogFooter>
          <Button
            type='button'
            variant='outline'
            onClick={() => onOpenChange(false)}
          >
            {tAction('cancel')}
          </Button>
          <Button
            type='submit'
            form='media-edit-form'
            disabled={form.formState.isSubmitting}
          >
            {form.formState.isSubmitting && (
              <Loader2 className='animate-spin' />
            )}
            {tAction('save')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
