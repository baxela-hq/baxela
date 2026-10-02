import { useState } from 'react'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { type CategoryNode } from '@/features/catalog/categories/data/schema'
import { useCategoryTree } from '@/features/catalog/categories/hooks/use-categories'
import { Locales } from '../data/routes'

type CategoryPickerDialogProps = {
  open: boolean
  onOpenChange: (open: boolean) => void
  /** Ids already in the selection — picked but disabled. */
  selectedIds: number[]
  /** Called with the newly picked categories (uncommitted until confirmed). */
  onConfirm: (categories: CategoryNode[]) => void
}

/**
 * Category picker over the shared category tree: nested checkboxes,
 * indentation encodes depth, selection persists while the dialog is open.
 */
export function CategoryPickerDialog({
  open,
  onOpenChange,
  selectedIds,
  onConfirm,
}: CategoryPickerDialogProps) {
  const { tLabel, tStatus } = useAppTranslation(Locales.FEATURED)
  const { tAction } = useAppTranslation(Locales.SHARED_COMMON)

  const categories = useCategoryTree()
  const [picked, setPicked] = useState<CategoryNode[]>([])

  const existing = new Set(selectedIds)

  const handleOpenChange = (state: boolean) => {
    if (!state) {
      setPicked([])
    }
    onOpenChange(state)
  }

  const togglePick = (category: CategoryNode, checked: boolean) => {
    setPicked((prev) =>
      checked
        ? [...prev, category]
        : prev.filter((candidate) => candidate.id !== category.id)
    )
  }

  const handleConfirm = () => {
    onConfirm(picked)
    setPicked([])
    onOpenChange(false)
  }

  return (
    <Dialog open={open} onOpenChange={handleOpenChange}>
      <DialogContent className='flex max-h-[85vh] w-full flex-col gap-0 sm:max-w-md'>
        <DialogHeader className='text-start'>
          <DialogTitle>{tLabel('add-categories')}</DialogTitle>
          <DialogDescription>{tStatus('picker-tree-hint')}</DialogDescription>
        </DialogHeader>

        <div className='min-h-0 flex-1 overflow-y-auto px-4 pt-2 pb-4'>
          {categories.length === 0 ? (
            <p className='py-8 text-center text-sm text-muted-foreground'>
              {tStatus('no-results')}
            </p>
          ) : (
            <div className='space-y-2'>
              {categories.map((category) => {
                const isAlreadySelected = existing.has(category.id)
                const isPicked =
                  isAlreadySelected ||
                  picked.some((candidate) => candidate.id === category.id)

                return (
                  <label
                    key={category.id}
                    className='flex cursor-pointer items-center gap-2'
                    style={{ paddingLeft: `${category.depth * 1.25}rem` }}
                  >
                    <Checkbox
                      checked={isPicked}
                      disabled={isAlreadySelected}
                      onCheckedChange={(checked) =>
                        togglePick(category, checked === true)
                      }
                    />
                    <span className='text-sm font-normal'>
                      {category.title}
                    </span>
                  </label>
                )
              })}
            </div>
          )}
        </div>

        <DialogFooter>
          <Button
            type='button'
            variant='outline'
            onClick={() => handleOpenChange(false)}
          >
            {tAction('cancel')}
          </Button>
          <Button
            type='button'
            disabled={picked.length === 0}
            onClick={handleConfirm}
          >
            {tAction('confirm')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
