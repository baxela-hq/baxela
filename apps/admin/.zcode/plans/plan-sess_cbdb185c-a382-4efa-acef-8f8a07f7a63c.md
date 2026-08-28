# Media Feature Plan — `src/features/media`

## Confirmed decisions
- Media item fields: `{ id, file_name, url, mime_type, size, folder_id, created_at }` (defensive: `path` fallback for url, all optional fields tolerant)
- Add **breadcrumb** (no new deps) + **context-menu** (+ `@radix-ui/react-context-menu`, the only new package) via shadcn CLI
- Picker supports **single + multiple** via `multiple` prop
- Scope: feature + reusable picker only (product integration later)

## API contract (from Bruno collection)
| Op | Request |
|---|---|
| Folders list | `GET media/admin/folders` (+ `per_page: 1000`, filter client-side) |
| Folder create | `POST media/admin/folders` — `{ name, parent_id }` |
| Folder update | `PATCH media/admin/folders/{id}` — `{ name, parent_id }` |
| Folder delete | `DELETE media/admin/folders/{id}` |
| Media list | `GET media/admin/media?filter[folder_id]=null\|{id}` |
| Media upload | `POST media/admin/media` — **multipart**: `file`, optional `folder_id` |
| Media update | `PATCH media/admin/media/{id}` — `{ file_name, folder_id }` (rename/move) |
| Media delete | `DELETE media/admin/media/{id}` |

## Data strategy (two separate queries, merged in UI — as you suggested)
- `useQuery(['media','folders'])` → fetch **all** folders once. Children of current folder + breadcrumb path derived client-side (reusing the `buildHierarchy` traversal pattern from `src/shared/lib/tree.ts`, with `getTitle = f => f.name`).
- `useQuery(['media','files', folderId])` → server-filtered by current folder (`filter[folder_id]='null' | id`) since file collections grow large.
- All mutations invalidate the `['media']` root key. Reads via TanStack Query; mutations follow your codebase convention (plain async handlers + `invalidateQueries` + sonner toasts + `parseAndToastError`).

## File structure
```
src/features/media/
├── api/media.api.ts               # fetchFolders/create/update/delete + fetchMedia/upload/update/delete
├── data/routes.ts                 # MediaRoutes.CACHE_KEY = 'media', Locales.MEDIA
├── data/schema.ts                 # zod: MediaItem, MediaFolder + folder/media form schemas + utils (getMediaUrl, isImage, formatBytes)
├── hooks/use-media-library.ts     # useFoldersQuery(), useMediaQuery(folderId) — shared by page & picker
├── components/
│   ├── provider.tsx               # dialog state context (add/edit folder, rename/move media, delete, preview)
│   ├── media-browser.tsx          # CORE reusable browser (controlled: folderId + onFolderChange, mode: 'manage' | 'picker')
│   │                              #   breadcrumbs, search (client-side), grid/list toggle, folders grid, files grid,
│   │                              #   drag&drop + button upload, context menus, selection (picker mode)
│   ├── folder-card.tsx            # folder tile + ContextMenu (open/rename/move/delete)
│   ├── media-card.tsx             # file tile (thumb or type icon, name, size) + ContextMenu (preview/rename/move/copy url/download/delete)
│   ├── media-list-view.tsx        # table list view variant
│   ├── folder-mutate-dialog.tsx   # create/edit/rename/move folder: name + parent Select (flat tree w/ depth, self+descendants excluded when editing)
│   ├── media-mutate-dialog.tsx    # rename file_name + move folder_id
│   ├── delete-dialog.tsx          # ConfirmDialog destructive (warns folder contents)
│   ├── preview-sheet.tsx          # Sheet: image preview / file icon, metadata, quick actions
│   └── media-picker-dialog.tsx    # REUSABLE PICKER exported for other features
└── index.tsx                      # /media page: Header + Main + toolbar buttons + MediaBrowser + dialogs
```

### Reusable picker API (used later in product form etc.)
```tsx
<MediaPickerDialog
  open={open} onOpenChange={setOpen}
  multiple={false}                        // single | multiple
  accept="image/*"                        // optional filter
  onSelect={(items) => ...}               // MediaItem | MediaItem[]
/>
```
Picker mode = same browser but: selection checkboxes + footer "Select" bar, upload allowed, edit/delete actions hidden.

## Wiring
- Route: `src/routes/_authenticated/media/index.tsx` with `validateSearch` zod `{ folder?: number }` (deep-linkable current folder; `routeTree.gen.ts` regenerates via the router plugin on build/dev)
- Sidebar: `sidebar-data.ts` → modules group: `{ title: t('sidebar.media'), url: '/media', icon: Images }`
- i18n: `public/locales/{en,fa}/media/media.json` (form.labels/actions/placeholders, messages, page_titles — matching `useAppTranslation` helpers) + `sidebar.media` key in `shared/layout.json` (en+fa)
- Conventions honored: RHF + zodResolver forms, `ConfirmDialog`, `SkeletonWidget` loading, sonner toasts, `parseAndToastError`, RTL logical classes (ms/me/text-start), lucide icons

## Verification
1. `pnpm shadcn add breadcrumb context-menu` (installs the one new radix package)
2. `pnpm build` — regenerates route tree + full typecheck; fix any errors
3. Quick dev-server + browser smoke test of `/media` (grids, breadcrumb navigation, dialogs) if backend is reachable

No other new packages; everything else uses installed deps only.