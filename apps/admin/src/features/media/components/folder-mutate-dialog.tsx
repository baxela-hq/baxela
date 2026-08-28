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
import { Input } from '@/components/ui/input'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { createFolder, updateFolder } from '../api/media.api'
import { Locales } from '../data/routes'
import {
  buildFolderDefaultValues,
  buildFolderEditValues,
  buildFolderFormSchema,
  type FolderForm,
  type MediaFolder,
} from '../data/schema'
import { useInvalidateMedia } from '../hooks/use-media-actions'
import { useFolderTree } from '../hooks/use-media-library'

type FolderMutateDialogProps = {
  open: boolean
  onOpenChange: (open: boolean) => void
  currentRow?: MediaFolder
  /** Default parent when creating a folder (the currently browsed folder). */
  defaultParentId?: number | null
}

export function FolderMutateDialog({
  open,
  onOpenChange,
  currentRow,
  defaultParentId = null,
}: FolderMutateDialogProps) {
  const isUpdate = !!currentRow
  const invalidateMedia = useInvalidateMedia()
  // Generic texts (actions, titles, record messages) come from common;
  // only folder-specific labels/help texts live in the media namespace
  const { tAction, tPlaceHolder, tMessage, tPageTitle } = useAppTranslation(
    Locales.SHARED_COMMON
  )
  const {
    tLabel,
    tHelpText,
    tPlaceHolder: mediaTPlaceHolder,
  } = useAppTranslation(Locales.MEDIA)
  const entityName = tLabel('folder')

  // Exclude the edited folder and its descendants from parent choices
  const folderTree = useFolderTree(isUpdate ? currentRow.id : null)

  const form = useForm<FolderForm>({
    resolver: zodResolver(
      buildFolderFormSchema(tMessage('error.invalid_name'))
    ),
    defaultValues: isUpdate
      ? buildFolderEditValues(currentRow)
      : buildFolderDefaultValues(defaultParentId),
  })

  useEffect(() => {
    if (open) {
      form.reset(
        isUpdate
          ? buildFolderEditValues(currentRow)
          : buildFolderDefaultValues(defaultParentId)
      )
    }
  }, [open, currentRow, defaultParentId]) // eslint-disable-line react-hooks/exhaustive-deps

  const onSubmit = async (data: FolderForm) => {
    try {
      if (isUpdate) {
        await updateFolder(currentRow.id, data)
      } else {
        await createFolder(data)
      }
      toast.success(
        tMessage(`success.record.${isUpdate ? 'updated' : 'created'}`, {
          name: entityName,
        })
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
            {isUpdate
              ? tPageTitle('form.title_edit', {
                  entity: entityName,
                  id: currentRow?.id?.toString(),
                })
              : tPageTitle('form.title_create', { entity: entityName })}
          </DialogTitle>
          <DialogDescription>{tPageTitle('form.subtitle')}</DialogDescription>
        </DialogHeader>
        <Form {...form}>
          <form
            id='media-folder-form'
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
                    <Input {...field} placeholder={mediaTPlaceHolder('name')} />
                  </FormControl>
                  <FormMessage />
                </FormItem>
              )}
            />

            <FormField
              control={form.control}
              name='parent_id'
              render={({ field }) => (
                <FormItem>
                  <FormLabel>{tLabel('parent_id')}</FormLabel>
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
                  <FormDescription>{tHelpText('parent_id')}</FormDescription>
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
            form='media-folder-form'
            disabled={form.formState.isSubmitting}
          >
            {form.formState.isSubmitting && (
              <Loader2 className='animate-spin' />
            )}
            {tAction(isUpdate ? 'save' : 'create')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
