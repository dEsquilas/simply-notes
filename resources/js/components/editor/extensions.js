import StarterKit from '@tiptap/starter-kit'
import TaskList from '@tiptap/extension-task-list'
import TaskItem from '@tiptap/extension-task-item'
import Image from '@tiptap/extension-image'
import { TableKit } from '@tiptap/extension-table'
import Placeholder from '@tiptap/extension-placeholder'
import { Video } from './video-node.js'
import { ImageDropPaste } from './image-paste-drop.js'

/**
 * The extensions used by the note editor, shared between the live editor component and the
 * headless validation harness (scripts/tiptap-validation) so both render notes identically.
 */
export function buildExtensions({ placeholder = 'Start typing...' } = {}) {
    return [
        StarterKit.configure({
            heading: { levels: [1, 2, 3, 4, 5, 6] },
            link: { openOnClick: false, autolink: false, HTMLAttributes: { rel: 'noopener noreferrer', target: '_blank' } },
        }),
        TaskList,
        TaskItem.configure({ nested: true }),
        Image.configure({
            inline: false,
            allowBase64: true,
            resize: { enabled: true, minWidth: 40, minHeight: 40 },
        }),
        TableKit.configure({
            table: { resizable: true },
        }),
        Video,
        ImageDropPaste,
        Placeholder.configure({ placeholder }),
    ]
}
