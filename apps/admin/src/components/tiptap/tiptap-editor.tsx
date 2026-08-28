import {
  useCallback,
  useEffect,
  useRef,
  useState,
  type FormEvent,
  type ReactNode,
} from "react"
import { EditorContent, useEditor, useEditorState, type JSONContent } from "@tiptap/react"
import type { Editor } from "@tiptap/core"
import { StarterKit } from "@tiptap/starter-kit"
import { Highlight } from "@tiptap/extension-highlight"
import { Image } from "@tiptap/extension-image"
import { TaskItem, TaskList } from "@tiptap/extension-list"
import { Subscript } from "@tiptap/extension-subscript"
import { Superscript } from "@tiptap/extension-superscript"
import { TextAlign } from "@tiptap/extension-text-align"
import { Typography } from "@tiptap/extension-typography"
import {
  AlignCenter,
  AlignJustify,
  AlignLeft,
  AlignRight,
  Bold,
  Code,
  Code2,
  Highlighter,
  ImagePlus,
  Italic,
  Link,
  Link2Off,
  List,
  ListOrdered,
  ListTodo,
  Minus,
  Quote,
  Redo2,
  RemoveFormatting,
  Strikethrough,
  Subscript as SubscriptIcon,
  Superscript as SuperscriptIcon,
  Underline,
  Undo2,
  X,
} from "lucide-react"

import { MediaPickerDialog } from "@/features/media/components/media-picker-dialog"
import {
  getDisplayName,
  getMediaUrl,
  type MediaItem,
} from "@/features/media/data/schema"

import "./tiptap-editor.css"

export type TiptapEditorProps = {
  initialContent?: JSONContent | string | null
  className?: string
  onUpdate?: (content: { html: string; json: JSONContent | null }) => void
}

const DEFAULT_CONTENT: JSONContent = {
  type: "doc",
  content: [
    {
      type: "heading",
      attrs: { level: 1 },
      content: [{ type: "text", text: "Start writing…" }],
    },
  ],
}

const EMPTY_TOOLBAR_STATE = {
  isActive: () => false,
  canUndo: false,
  canRedo: false,
  headingLevel: 0,
  textAlign: null as string | null,
  linkHref: null as string | null,
  canLink: false,
}

type ToolbarButtonProps = {
  label: string
  active?: boolean
  disabled?: boolean
  onClick: () => void
  children: ReactNode
}

function ToolbarButton({ label, active, disabled, onClick, children }: ToolbarButtonProps) {
  return (
    <button
      type="button"
      title={label}
      aria-label={label}
      aria-pressed={active}
      disabled={disabled}
      onClick={onClick}
      className={`flex h-8 w-8 shrink-0 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-accent hover:text-accent-foreground disabled:pointer-events-none disabled:opacity-40 ${
        active ? "bg-accent text-accent-foreground" : ""
      }`}
    >
      {children}
    </button>
  )
}

function ToolbarDivider() {
  return <div aria-hidden className="mx-1 h-6 w-px shrink-0 bg-border" />
}

function ToolbarGroup({ children }: { children: ReactNode }) {
  return <div className="flex shrink-0 items-center gap-0.5">{children}</div>
}

export function TiptapEditor({ initialContent, className, onUpdate }: TiptapEditorProps) {
  const [linkFormOpen, setLinkFormOpen] = useState(false)
  const [linkUrl, setLinkUrl] = useState("")
  const [mediaPickerOpen, setMediaPickerOpen] = useState(false)
  const linkInputRef = useRef<HTMLInputElement>(null)
  const [initialEditorContent] = useState<JSONContent | string>(() => initialContent ?? DEFAULT_CONTENT)
  const lastContentRef = useRef<JSONContent | string>(initialEditorContent)

  const handleEditorUpdate = useCallback(
    ({ editor: currentEditor }: { editor: Editor }) => {
      const html = currentEditor.getHTML()
      lastContentRef.current = html
      onUpdate?.({ html, json: currentEditor.getJSON() })
    },
    [onUpdate]
  )

  const editor = useEditor({
    immediatelyRender: false,
    editorProps: {
      attributes: {
        class: "tiptap-editor-content focus:outline-none",
        autocomplete: "off",
        autocorrect: "off",
        autocapitalize: "off",
      },
    },
    extensions: [
      StarterKit.configure({
        heading: { levels: [1, 2, 3, 4] },
        link: {
          openOnClick: false,
          HTMLAttributes: { rel: "noopener noreferrer", target: "_blank" },
        },
      }),
      TextAlign.configure({ types: ["heading", "paragraph"] }),
      TaskList,
      TaskItem.configure({ nested: true }),
      Highlight.configure({ multicolor: true }),
      Image,
      Typography,
      Subscript,
      Superscript,
    ],
    content: initialEditorContent,
    onUpdate: handleEditorUpdate,
  })

  const resolvedContent = initialContent ?? DEFAULT_CONTENT

  useEffect(() => {
    if (!editor) return

    const current = lastContentRef.current
    const next = resolvedContent

    const same =
      current === next ||
      (typeof current === "object" &&
        typeof next === "object" &&
        JSON.stringify(current) === JSON.stringify(next))

    if (same) return

    lastContentRef.current = next
    editor.commands.setContent(next, { emitUpdate: false })
  }, [editor, resolvedContent])

  const toolbarState = useEditorState({
    editor,
    selector: ({ editor: currentEditor }) => {
      if (!currentEditor) {
        return EMPTY_TOOLBAR_STATE
      }

      const selectionNode = currentEditor.state.selection.$anchor.parent

      return {
        isActive: (name: string) => currentEditor.isActive(name),
        canUndo: currentEditor.can().undo(),
        canRedo: currentEditor.can().redo(),
        headingLevel:
          [1, 2, 3, 4].find((level) => currentEditor.isActive("heading", { level })) ?? 0,
        textAlign: (selectionNode.attrs.textAlign as string | null) ?? null,
        linkHref: (currentEditor.getAttributes("link").href as string | null) ?? null,
        canLink: currentEditor.isActive("link"),
      }
    },
  })

  const toolbar = toolbarState ?? EMPTY_TOOLBAR_STATE

  if (!editor) {
    return null
  }

  const toggleMark = (type: string) => {
    editor.chain().focus().toggleMark(type).run()
  }

  const setHeading = (level: number) => {
    if (level === 0) {
      editor.chain().focus().setParagraph().run()
    } else {
      editor.chain().focus().toggleHeading({ level: level as 1 | 2 | 3 | 4 }).run()
    }
  }

  const openLinkForm = () => {
    setLinkUrl(editor.getAttributes("link").href ?? "")
    setLinkFormOpen(true)
    requestAnimationFrame(() => linkInputRef.current?.select())
  }

  const applyLink = (event: FormEvent) => {
    event.preventDefault()
    const url = linkUrl.trim()

    if (!url) {
      editor.chain().focus().unsetLink().run()
    } else if (editor.isActive("link")) {
      editor.chain().focus().updateAttributes("link", { href: url }).run()
    } else {
      editor.chain().focus().setLink({ href: url }).run()
    }

    setLinkFormOpen(false)
  }

  const removeLink = () => {
    editor.chain().focus().unsetLink().run()
    setLinkFormOpen(false)
  }

  const handleSelectMediaImage = (item: MediaItem) => {
    const src = getMediaUrl(item)

    if (!src) return

    editor.chain().focus().setImage({ src, alt: getDisplayName(item) }).run()
  }

  const setAlign = (align: "left" | "center" | "right" | "justify") => {
    editor.chain().focus().setTextAlign(align).run()
  }

  return (
    <div className={`tiptap-editor flex w-full flex-col overflow-hidden rounded-lg border bg-background ${className ?? ""}`}>
      <div className="relative flex flex-wrap items-center gap-1 border-b bg-muted/50 px-2 py-1.5">
        <ToolbarGroup>
          <ToolbarButton
            label="Undo"
            disabled={!toolbar.canUndo}
            onClick={() => editor.chain().focus().undo().run()}
          >
            <Undo2 className="h-4 w-4" />
          </ToolbarButton>
          <ToolbarButton
            label="Redo"
            disabled={!toolbar.canRedo}
            onClick={() => editor.chain().focus().redo().run()}
          >
            <Redo2 className="h-4 w-4" />
          </ToolbarButton>
        </ToolbarGroup>

        <ToolbarDivider />

        <ToolbarGroup>
          <select
            aria-label="Heading level"
            value={toolbar.headingLevel}
            onChange={(event) => setHeading(Number(event.target.value))}
            className="h-8 rounded-md border-0 bg-transparent px-1.5 text-sm text-muted-foreground hover:bg-accent hover:text-accent-foreground focus:outline-none focus:ring-1 focus:ring-ring"
          >
            <option value={0}>Paragraph</option>
            <option value={1}>Heading 1</option>
            <option value={2}>Heading 2</option>
            <option value={3}>Heading 3</option>
            <option value={4}>Heading 4</option>
          </select>
          <ToolbarButton
            label="Bullet list"
            active={toolbar.isActive("bulletList")}
            onClick={() => editor.chain().focus().toggleBulletList().run()}
          >
            <List className="h-4 w-4" />
          </ToolbarButton>
          <ToolbarButton
            label="Ordered list"
            active={toolbar.isActive("orderedList")}
            onClick={() => editor.chain().focus().toggleOrderedList().run()}
          >
            <ListOrdered className="h-4 w-4" />
          </ToolbarButton>
          <ToolbarButton
            label="Task list"
            active={toolbar.isActive("taskList")}
            onClick={() => editor.chain().focus().toggleTaskList().run()}
          >
            <ListTodo className="h-4 w-4" />
          </ToolbarButton>
          <ToolbarButton
            label="Blockquote"
            active={toolbar.isActive("blockquote")}
            onClick={() => editor.chain().focus().toggleBlockquote().run()}
          >
            <Quote className="h-4 w-4" />
          </ToolbarButton>
          <ToolbarButton
            label="Code block"
            active={toolbar.isActive("codeBlock")}
            onClick={() => editor.chain().focus().toggleCodeBlock().run()}
          >
            <Code2 className="h-4 w-4" />
          </ToolbarButton>
        </ToolbarGroup>

        <ToolbarDivider />

        <ToolbarGroup>
          <ToolbarButton
            label="Bold"
            active={toolbar.isActive("bold")}
            onClick={() => toggleMark("bold")}
          >
            <Bold className="h-4 w-4" />
          </ToolbarButton>
          <ToolbarButton
            label="Italic"
            active={toolbar.isActive("italic")}
            onClick={() => toggleMark("italic")}
          >
            <Italic className="h-4 w-4" />
          </ToolbarButton>
          <ToolbarButton
            label="Underline"
            active={toolbar.isActive("underline")}
            onClick={() => toggleMark("underline")}
          >
            <Underline className="h-4 w-4" />
          </ToolbarButton>
          <ToolbarButton
            label="Strikethrough"
            active={toolbar.isActive("strike")}
            onClick={() => toggleMark("strike")}
          >
            <Strikethrough className="h-4 w-4" />
          </ToolbarButton>
          <ToolbarButton
            label="Inline code"
            active={toolbar.isActive("code")}
            onClick={() => toggleMark("code")}
          >
            <Code className="h-4 w-4" />
          </ToolbarButton>
          <ToolbarButton
            label="Subscript"
            active={toolbar.isActive("subscript")}
            onClick={() => editor.chain().focus().toggleSubscript().run()}
          >
            <SubscriptIcon className="h-4 w-4" />
          </ToolbarButton>
          <ToolbarButton
            label="Superscript"
            active={toolbar.isActive("superscript")}
            onClick={() => editor.chain().focus().toggleSuperscript().run()}
          >
            <SuperscriptIcon className="h-4 w-4" />
          </ToolbarButton>
        </ToolbarGroup>

        <ToolbarDivider />

        <ToolbarGroup>
          <ToolbarButton
            label="Highlight"
            active={toolbar.isActive("highlight")}
            onClick={() => editor.chain().focus().toggleHighlight().run()}
          >
            <Highlighter className="h-4 w-4" />
          </ToolbarButton>
          <input
            type="color"
            aria-label="Highlight color"
            defaultValue="#FDE047"
            onChange={(event) =>
              editor.chain().focus().setHighlight({ color: event.target.value }).run()
            }
            className="h-8 w-6 cursor-pointer rounded-md border-0 bg-transparent p-1 hover:bg-accent"
          />
        </ToolbarGroup>

        <ToolbarDivider />

        <ToolbarGroup>
          <ToolbarButton
            label="Align left"
            active={toolbar.textAlign === "left"}
            onClick={() => setAlign("left")}
          >
            <AlignLeft className="h-4 w-4" />
          </ToolbarButton>
          <ToolbarButton
            label="Align center"
            active={toolbar.textAlign === "center"}
            onClick={() => setAlign("center")}
          >
            <AlignCenter className="h-4 w-4" />
          </ToolbarButton>
          <ToolbarButton
            label="Align right"
            active={toolbar.textAlign === "right"}
            onClick={() => setAlign("right")}
          >
            <AlignRight className="h-4 w-4" />
          </ToolbarButton>
          <ToolbarButton
            label="Align justify"
            active={toolbar.textAlign === "justify"}
            onClick={() => setAlign("justify")}
          >
            <AlignJustify className="h-4 w-4" />
          </ToolbarButton>
        </ToolbarGroup>

        <ToolbarDivider />

        <ToolbarGroup>
          <div className="relative">
            <ToolbarButton
              label={toolbar.canLink ? "Edit link" : "Add link"}
              active={toolbar.canLink || linkFormOpen}
              onClick={openLinkForm}
            >
              <Link className="h-4 w-4" />
            </ToolbarButton>

            {linkFormOpen && (
              <>
                <button
                  type="button"
                  aria-label="Close link form"
                  className="fixed inset-0 z-40 cursor-default"
                  onClick={() => setLinkFormOpen(false)}
                />
                <form
                  onSubmit={applyLink}
                  className="absolute right-0 top-full z-50 mt-1 flex w-64 items-center gap-1 rounded-md border bg-popover p-1.5 shadow-md"
                >
                  <input
                    ref={linkInputRef}
                    type="url"
                    value={linkUrl}
                    placeholder="https://example.com"
                    onChange={(event) => setLinkUrl(event.target.value)}
                    className="h-7 w-full rounded-sm bg-transparent px-2 text-sm text-foreground placeholder:text-muted-foreground focus:outline-none"
                  />
                  <button
                    type="submit"
                    title="Apply link"
                    aria-label="Apply link"
                    className="flex h-7 w-7 shrink-0 items-center justify-center rounded-sm text-muted-foreground hover:bg-accent hover:text-accent-foreground"
                  >
                    <Link className="h-4 w-4" />
                  </button>
                  <button
                    type="button"
                    title="Remove link"
                    aria-label="Remove link"
                    disabled={!toolbar.canLink}
                    onClick={removeLink}
                    className="flex h-7 w-7 shrink-0 items-center justify-center rounded-sm text-muted-foreground hover:bg-accent hover:text-accent-foreground disabled:opacity-40"
                  >
                    <Link2Off className="h-4 w-4" />
                  </button>
                  <button
                    type="button"
                    title="Cancel"
                    aria-label="Cancel"
                    onClick={() => setLinkFormOpen(false)}
                    className="flex h-7 w-7 shrink-0 items-center justify-center rounded-sm text-muted-foreground hover:bg-accent hover:text-accent-foreground"
                  >
                    <X className="h-4 w-4" />
                  </button>
                </form>
              </>
            )}
          </div>

          <ToolbarButton label="Insert image" onClick={() => setMediaPickerOpen(true)}>
            <ImagePlus className="h-4 w-4" />
          </ToolbarButton>

          <ToolbarButton
            label="Horizontal rule"
            onClick={() => editor.chain().focus().setHorizontalRule().run()}
          >
            <Minus className="h-4 w-4" />
          </ToolbarButton>

          <ToolbarButton
            label="Clear formatting"
            onClick={() => {
              editor.chain().focus().unsetAllMarks().clearNodes().run()
            }}
          >
            <RemoveFormatting className="h-4 w-4" />
          </ToolbarButton>
        </ToolbarGroup>
      </div>

      <EditorContent editor={editor} className="tiptap-editor-scroll flex-1 overflow-y-auto" />

      <MediaPickerDialog
        open={mediaPickerOpen}
        onOpenChange={setMediaPickerOpen}
        accept="image/*"
        onSelect={handleSelectMediaImage}
      />
    </div>
  )
}
