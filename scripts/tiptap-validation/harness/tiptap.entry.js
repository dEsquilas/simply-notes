/**
 * Uses the exact same extensions and legacy-content preprocessing as the live editor component
 * (resources/js/components/editor), so the validation harness's "after" view is not a
 * reimplementation of the editor's configuration but the configuration itself.
 */
import { Editor } from '@tiptap/core'
import { buildExtensions } from '../../../resources/js/components/editor/extensions.js'
import { preprocessLegacyHtml } from '../../../resources/js/components/editor/legacy-content.js'

window.tiptapConvert = function tiptapConvert(html) {
    const container = document.createElement('div')
    document.body.appendChild(container)

    try {
        const editor = new Editor({
            element: container,
            extensions: buildExtensions(),
            content: preprocessLegacyHtml(html ?? ''),
        })

        const out = editor.getHTML()
        editor.destroy()

        return out
    } finally {
        container.remove()
    }
}
