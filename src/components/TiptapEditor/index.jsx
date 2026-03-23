import { useEffect, useRef, useState } from 'react'
import { EditorContent, useEditor } from '@tiptap/react'
import { Node, mergeAttributes } from '@tiptap/core'
import StarterKit from '@tiptap/starter-kit'
import Image from '@tiptap/extension-image'
import Link from '@tiptap/extension-link'
import { Table, TableRow, TableHeader, TableCell } from '@tiptap/extension-table'
import { useLanguage } from '../../contexts/LanguageContext'

const Video = Node.create({
  name: 'video',
  group: 'block',
  atom: true,
  selectable: true,

  addAttributes() {
    return {
      src: { default: null },
      controls: { default: true },
    }
  },

  parseHTML() {
    return [{ tag: 'video' }]
  },

  renderHTML({ HTMLAttributes }) {
    return ['video', mergeAttributes({ controls: 'true', style: 'max-width: 100%; height: auto;' }, HTMLAttributes)]
  },
})

const Audio = Node.create({
  name: 'audio',
  group: 'block',
  atom: true,
  selectable: true,

  addAttributes() {
    return {
      src: { default: null },
      controls: { default: true },
    }
  },

  parseHTML() {
    return [{ tag: 'audio' }]
  },

  renderHTML({ HTMLAttributes }) {
    return ['audio', mergeAttributes({ controls: 'true', style: 'width: 100%;' }, HTMLAttributes)]
  },
})

function ToolbarButton({ onClick, active = false, label, icon, disabled = false }) {
  return (
    <button
      type="button"
      className={`btn btn-sm ${active ? 'btn-dark' : 'btn-outline-secondary'}`}
      onClick={onClick}
      title={label}
      disabled={disabled}
    >
      <i className={`bi ${icon}`}></i>
    </button>
  )
}

export default function TiptapEditor({ value, onChange, onUploadImage, onUploadVideo, onUploadAudio }) {
  const imageInputRef = useRef(null)
  const videoInputRef = useRef(null)
  const audioInputRef = useRef(null)
  const [uploadingMedia, setUploadingMedia] = useState(false)
  const { lang } = useLanguage()

  const editor = useEditor({
    extensions: [
      StarterKit.configure({
        link: false
      }),
      Link.configure({
        openOnClick: false,
        HTMLAttributes: {
          rel: 'noopener noreferrer',
          target: '_blank',
        },
      }),
      Image,
      Video,
      Audio,
      Table.configure({
        resizable: true
      }),
      TableRow,
      TableHeader,
      TableCell
    ],
    content: value || '',
    editorProps: {
      attributes: {
        class: 'tiptap-editor-content'
      }
    },
    onUpdate: ({ editor: currentEditor }) => {
      onChange(currentEditor.getHTML())
    }
  })

  useEffect(() => {
    if (!editor) return
    const nextValue = value || ''
    if (editor.getHTML() !== nextValue) {
      editor.commands.setContent(nextValue, false)
    }
  }, [editor, value])

  if (!editor) return null

  const handleImageSelect = () => {
    imageInputRef.current?.click()
  }

  const handleUpload = async (event, onUpload, insertMedia) => {
    const file = event.target.files?.[0]
    event.target.value = ''
    if (!file) return
    if (!onUpload) return

    setUploadingMedia(true)
    try {
      const mediaUrl = await onUpload(file)
      if (mediaUrl) {
        insertMedia(mediaUrl)
      }
    } catch (err) {
      console.error('Failed to upload media:', err)
      alert(err.message || lang('uploadFailed'))
    } finally {
      setUploadingMedia(false)
    }
  }

  const handleSetLink = () => {
    const previousUrl = editor.getAttributes('link').href || ''
    const input = window.prompt(lang('enterLinkUrl'), previousUrl)

    if (input === null) return

    const url = input.trim()
    if (!url) {
      editor.chain().focus().unsetLink().run()
      return
    }

    editor
      .chain()
      .focus()
      .extendMarkRange('link')
      .setLink({ href: url, target: '_blank', rel: 'noopener noreferrer' })
      .run()
  }

  return (
    <div className="tiptap-editor card border-0 shadow-sm">
      <div className="card-header bg-white d-flex flex-wrap gap-2">
        <ToolbarButton
          icon="bi-type-bold"
          label={lang('editorBold')}
          active={editor.isActive('bold')}
          onClick={() => editor.chain().focus().toggleBold().run()}
        />
        <ToolbarButton
          icon="bi-type-italic"
          label={lang('editorItalic')}
          active={editor.isActive('italic')}
          onClick={() => editor.chain().focus().toggleItalic().run()}
        />
        <ToolbarButton
          icon="bi-type-underline"
          label={lang('editorStrike')}
          active={editor.isActive('strike')}
          onClick={() => editor.chain().focus().toggleStrike().run()}
        />
        <ToolbarButton
          icon="bi-list-ul"
          label={lang('editorBulletList')}
          active={editor.isActive('bulletList')}
          onClick={() => editor.chain().focus().toggleBulletList().run()}
        />
        <ToolbarButton
          icon="bi-list-ol"
          label={lang('editorOrderedList')}
          active={editor.isActive('orderedList')}
          onClick={() => editor.chain().focus().toggleOrderedList().run()}
        />
        <ToolbarButton
          icon="bi-code-slash"
          label={lang('editorCodeBlock')}
          active={editor.isActive('codeBlock')}
          onClick={() => editor.chain().focus().toggleCodeBlock().run()}
        />
        <ToolbarButton
          icon="bi-quote"
          label={lang('editorBlockquote')}
          active={editor.isActive('blockquote')}
          onClick={() => editor.chain().focus().toggleBlockquote().run()}
        />
        <ToolbarButton
          icon="bi-link-45deg"
          label={lang('editorLink')}
          active={editor.isActive('link')}
          onClick={handleSetLink}
        />
        <ToolbarButton
          icon="bi-link"
          label={lang('editorUnlink')}
          onClick={() => editor.chain().focus().unsetLink().run()}
          disabled={!editor.isActive('link')}
        />
        <ToolbarButton
          icon="bi-table"
          label={lang('editorInsertTable')}
          onClick={() => editor.chain().focus().insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run()}
        />
        <ToolbarButton
          icon="bi-plus-square"
          label={lang('editorAddRow')}
          onClick={() => editor.chain().focus().addRowAfter().run()}
          disabled={!editor.isActive('table')}
        />
        <ToolbarButton
          icon="bi-plus-square-dotted"
          label={lang('editorAddColumn')}
          onClick={() => editor.chain().focus().addColumnAfter().run()}
          disabled={!editor.isActive('table')}
        />
        <ToolbarButton
          icon="bi-trash"
          label={lang('editorDeleteTable')}
          onClick={() => editor.chain().focus().deleteTable().run()}
          disabled={!editor.isActive('table')}
        />
        <ToolbarButton
          icon="bi-image"
          label={lang('editorAddImage')}
          onClick={handleImageSelect}
          disabled={uploadingMedia || !onUploadImage}
        />
        <ToolbarButton
          icon="bi-film"
          label={lang('editorAddVideo')}
          onClick={() => videoInputRef.current?.click()}
          disabled={uploadingMedia || !onUploadVideo}
        />
        <ToolbarButton
          icon="bi-music-note-beamed"
          label={lang('editorAddAudio')}
          onClick={() => audioInputRef.current?.click()}
          disabled={uploadingMedia || !onUploadAudio}
        />
        <ToolbarButton
          icon="bi-arrow-counterclockwise"
          label={lang('editorUndo')}
          onClick={() => editor.chain().focus().undo().run()}
        />
        <ToolbarButton
          icon="bi-arrow-clockwise"
          label={lang('editorRedo')}
          onClick={() => editor.chain().focus().redo().run()}
        />
      </div>
      <div className="card-body p-0">
        <input
          ref={imageInputRef}
          type="file"
          accept="image/jpeg,image/png,image/webp,image/gif"
          style={{ display: 'none' }}
          onChange={(event) => handleUpload(event, onUploadImage, (url) => editor.chain().focus().setImage({ src: url }).run())}
        />
        <input
          ref={videoInputRef}
          type="file"
          accept="video/mp4"
          style={{ display: 'none' }}
          onChange={(event) => handleUpload(event, onUploadVideo, (url) => editor.chain().focus().insertContent({ type: 'video', attrs: { src: url } }).run())}
        />
        <input
          ref={audioInputRef}
          type="file"
          accept="audio/mpeg,audio/mp3,audio/wav,audio/x-wav,audio/ogg,audio/mp4,audio/aac"
          style={{ display: 'none' }}
          onChange={(event) => handleUpload(event, onUploadAudio, (url) => editor.chain().focus().insertContent({ type: 'audio', attrs: { src: url } }).run())}
        />
        <EditorContent editor={editor} />
      </div>
    </div>
  )
}
