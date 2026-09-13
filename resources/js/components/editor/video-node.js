import { Node, mergeAttributes } from '@tiptap/core'

/**
 * YouTube refuses to be shown inside an iframe from its "watch", youtu.be or shorts URLs:
 * the video command stores the embeddable URL instead. Any other URL (e.g. Vimeo) is kept as given.
 */
const YOUTUBE_URL = /^(?:https?:\/\/)?(?:www\.|m\.)?(?:youtube\.com\/(?:watch\?(?:.*&)?v=|shorts\/)|youtu\.be\/)([\w-]{11})/

export function toEmbedUrl(url) {
    const match = String(url ?? '').match(YOUTUBE_URL)

    return match ? `https://www.youtube.com/embed/${match[1]}` : url
}

/** A generic video embed, rendered as an iframe: kept close to what the previous Quill "video" format stored. */
export const Video = Node.create({
    name: 'video',
    group: 'block',
    atom: true,
    draggable: true,

    addAttributes() {
        return {
            src: { default: null },
        }
    },

    parseHTML() {
        return [{ tag: 'iframe' }]
    },

    renderHTML({ HTMLAttributes }) {
        return ['iframe', mergeAttributes({ class: 'note-video', frameborder: '0', allowfullscreen: 'true' }, HTMLAttributes)]
    },

    addCommands() {
        return {
            setVideo: (url) => ({ commands }) => {
                if (!url) {
                    return false
                }

                return commands.insertContent({ type: this.name, attrs: { src: toEmbedUrl(url) } })
            },
        }
    },
})
