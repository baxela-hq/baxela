import { useMemo, useState } from 'react'
import {
  DndContext,
  KeyboardSensor,
  PointerSensor,
  closestCenter,
  useSensor,
  useSensors,
  type DragEndEvent,
} from '@dnd-kit/core'
import {
  SortableContext,
  arrayMove,
  sortableKeyboardCoordinates,
  useSortable,
  verticalListSortingStrategy,
} from '@dnd-kit/sortable'
import { CSS } from '@dnd-kit/utilities'
import { GripVertical } from 'lucide-react'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { cn } from '@/lib/utils'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import { pickTranslation } from '@/shared/lib/locale.ts'
import { useMenuLinksAll } from '../hooks/use-menu-links'
import { useReorderMenuLinks } from '../hooks/use-menu-link-mutations'
import { type MenuLink } from '../data/schema'
import { Locales } from '../data/routes'

type SortNode = {
  link: MenuLink
  depth: number
  children: SortNode[]
}

type SortDialogProps = {
  open: boolean
  onOpenChange: (open: boolean) => void
  menuId: string
}

/** Group links by parent (API order = position order) and nest them. */
function buildTree(links: MenuLink[]): SortNode[] {
  const byParent = new Map<number | null, MenuLink[]>()
  for (const link of links) {
    const key = link.parent_id ?? null
    const group = byParent.get(key)
    if (group) group.push(link)
    else byParent.set(key, [link])
  }
  const make = (parentId: number | null, depth: number): SortNode[] =>
    (byParent.get(parentId) ?? []).map((link) => ({
      link,
      depth,
      children: make(link.id, depth + 1),
    }))
  return make(null, 0)
}

function flatten(nodes: SortNode[]): MenuLink[] {
  return nodes.flatMap((node) => [node.link, ...flatten(node.children)])
}

/** Move a link inside its own sibling group and renumber that group. */
function reorderGroup(
  nodes: SortNode[],
  parentId: number | null,
  activeId: number,
  overId: number
): SortNode[] {
  const moveAndRenumber = (group: SortNode[]): SortNode[] => {
    const from = group.findIndex((n) => n.link.id === activeId)
    const to = group.findIndex((n) => n.link.id === overId)
    if (from === -1 || to === -1) return group
    return arrayMove(group, from, to).map((node, index) => ({
      ...node,
      link: { ...node.link, position: index + 1 },
    }))
  }

  if (parentId === null) return moveAndRenumber(nodes)
  return nodes.map((node) =>
    node.link.id === parentId
      ? { ...node, children: moveAndRenumber(node.children) }
      : { ...node, children: reorderGroup(node.children, parentId, activeId, overId) }
  )
}

function SortableItem({ node }: { node: SortNode }) {
  const { attributes, listeners, setNodeRef, transform, transition, isDragging } =
    useSortable({ id: node.link.id })

  const title = pickTranslation(node.link.translations)?.title || `#${node.link.id}`

  return (
    <div
      ref={setNodeRef}
      style={{ transform: CSS.Translate.toString(transform), transition }}
      className={cn(
        'flex items-center gap-2 rounded-md border bg-background px-2 py-1.5',
        isDragging && 'z-10 shadow-md'
      )}
    >
      <button
        type='button'
        className='flex size-6 shrink-0 cursor-grab items-center justify-center text-muted-foreground hover:text-foreground'
        {...attributes}
        {...listeners}
      >
        <GripVertical size={16} />
      </button>
      <span className='truncate text-sm font-medium'>{title}</span>
      <span className='truncate text-xs text-muted-foreground'>{node.link.url}</span>
    </div>
  )
}

function SortableGroup({ nodes }: { nodes: SortNode[] }) {
  return (
    <SortableContext
      items={nodes.map((node) => node.link.id)}
      strategy={verticalListSortingStrategy}
    >
      <div className='space-y-1'>
        {nodes.map((node) => (
          <div key={node.link.id} className='space-y-1'>
            <SortableItem node={node} />
            {node.children.length > 0 && (
              <div className='ms-6 border-s ps-2'>
                <SortableGroup nodes={node.children} />
              </div>
            )}
          </div>
        ))}
      </div>
    </SortableContext>
  )
}

/**
 * Drag-and-drop reordering of one menu's links. Dragging reorders a link
 * within its sibling group only; re-parenting is done from the edit drawer.
 * Saving PATCHes each link whose position changed.
 */
export function SortDialog({ open, onOpenChange, menuId }: SortDialogProps) {
  const { tAction, tMessage } = useAppTranslation(Locales.SHARED_COMMON)
  const { tLabel } = useAppTranslation(Locales.MENU_LINK)

  const links = useMenuLinksAll(menuId)
  const [tree, setTree] = useState<SortNode[]>(() => buildTree(links))

  const reorder = useReorderMenuLinks()

  const sensors = useSensors(
    useSensor(PointerSensor),
    useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates })
  )

  // Rebuild the local tree when fresh data arrives or the dialog reopens
  // (adjusting state during render instead of in an effect).
  const [prevLinks, setPrevLinks] = useState(links)
  const [prevOpen, setPrevOpen] = useState(open)
  if (links !== prevLinks) {
    setPrevLinks(links)
    setTree(buildTree(links))
  }
  if (open !== prevOpen) {
    setPrevOpen(open)
    if (open) setTree(buildTree(links))
  }

  const originalPositions = useMemo(() => {
    const map = new Map<number, number | null>()
    links.forEach((link) => map.set(link.id, link.position))
    return map
  }, [links])

  const changedLinks = flatten(tree).filter(
    (link) => link.position !== originalPositions.get(link.id)
  )

  const onDragEnd = (event: DragEndEvent) => {
    const { active, over } = event
    if (!over || active.id === over.id) return

    const activeLink = flatten(tree).find((link) => link.id === active.id)
    const overLink = flatten(tree).find((link) => link.id === over.id)
    if (!activeLink || !overLink) return
    // Dragging across sibling groups is not supported on purpose.
    if ((activeLink.parent_id ?? null) !== (overLink.parent_id ?? null)) return

    setTree((current) =>
      reorderGroup(current, activeLink.parent_id ?? null, Number(active.id), Number(over.id))
    )
  }

  const handleSave = () => {
    if (changedLinks.length === 0) return

    toast.promise(reorder.mutateAsync({ menuId, links: changedLinks }), {
      loading: tLabel('sorting'),
      success: () => {
        onOpenChange(false)
        return tMessage('success.record.updated', { name: tLabel('links') })
      },
      error: tMessage('error.general'),
    })
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className='sm:max-w-md'>
        <DialogHeader>
          <DialogTitle>{tLabel('sort_links')}</DialogTitle>
          <DialogDescription>{tLabel('sort_help')}</DialogDescription>
        </DialogHeader>

        <div className='max-h-[60vh] overflow-y-auto pe-1'>
          {links.length === 0 ? (
            <p className='py-8 text-center text-sm text-muted-foreground'>
              {tLabel('no_links')}
            </p>
          ) : (
            <DndContext
              sensors={sensors}
              collisionDetection={closestCenter}
              onDragEnd={onDragEnd}
            >
              <SortableGroup nodes={tree} />
            </DndContext>
          )}
        </div>

        <DialogFooter className='gap-2'>
          <Button
            onClick={handleSave}
            disabled={changedLinks.length === 0 || reorder.isPending}
          >
            {tAction('save')}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
