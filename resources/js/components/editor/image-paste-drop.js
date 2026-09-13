import { Extension } from '@tiptap/core'
import { Plugin } from '@tiptap/pm/state'

export function fileToBase64(file) {
    return new Promise((resolve, reject) => {
        const reader = new FileReader()
        reader.onload = () => resolve(reader.result)
        reader.onerror = (error) => reject(error)
        reader.readAsDataURL(file)
    })
}

function imageFiles(fileList) {
    return Array.from(fileList ?? []).filter((file) => file.type.startsWith('image/'))
}

/** Inserts images dropped or pasted from the file system as base64 data URIs, like the old toolbar upload did. */
export const ImageDropPaste = Extension.create({
    name: 'imageDropPaste',

    addProseMirrorPlugins() {
        const { editor } = this

        const insertAt = (pos, file) => {
            fileToBase64(file).then((src) => {
                editor.chain().insertContentAt(pos, { type: 'image', attrs: { src } }).focus().run()
            })
        }

        return [
            new Plugin({
                props: {
                    handleDrop(view, event) {
                        const files = imageFiles(event.dataTransfer?.files)

                        if (!files.length) {
                            return false
                        }

                        event.preventDefault()
                        const coords = view.posAtCoords({ left: event.clientX, top: event.clientY })
                        const pos = coords ? coords.pos : view.state.selection.from
                        files.forEach((file) => insertAt(pos, file))

                        return true
                    },
                    handlePaste(view, event) {
                        const files = imageFiles(event.clipboardData?.files)

                        if (!files.length) {
                            return false
                        }

                        event.preventDefault()
                        files.forEach((file) => insertAt(view.state.selection.from, file))

                        return true
                    },
                },
            }),
        ]
    },
})
