<script setup>
import { defineModel, defineEmits, ref, onMounted, onUnmounted, watch } from 'vue'
import Quill from 'quill'
import QuillTableBetter from 'quill-table-better'
import BlotFormatter, { ImageSpec } from '@enzedonline/quill-blot-formatter2'

import './quill.snow.scss'
import 'quill-table-better/dist/quill-table-better.css'
import './quill-table-better.scss'

const model = defineModel()
const emit = defineEmits([
    'updated-content'
])

const Delta = Quill.import('delta')
const Video = Quill.import('formats/video')

/**
 * YouTube refuses to be shown inside an iframe from its "watch", youtu.be or shorts URLs:
 * the video button stores the embeddable URL instead.
 */
class EmbeddableVideo extends Video {
    static sanitize(url) {
        const match = url.match(/^(?:https?:\/\/)?(?:www\.|m\.)?(?:youtube\.com\/(?:watch\?(?:.*&)?v=|shorts\/)|youtu\.be\/)([\w-]{11})/)

        return super.sanitize(match ? `https://www.youtube.com/embed/${match[1]}` : url)
    }
}

Quill.register('formats/video', EmbeddableVideo, true)

const editorRef = ref(null)

let editor = null
let editorOptions = {
    theme: 'snow',
    placeholder: 'Start typing...',
    modules: {
        toolbar: [
            [{size: []}],
            ['bold', 'italic', 'underline', 'strike', 'blockquote'],
            [{'list': 'ordered'}, {'list': 'bullet'},
                {'indent': '-1'}, {'indent': '+1'}],
            ['link', 'image', 'video', 'clean', 'table-better']
        ],
        // Only images get the resize/format overlay: videos are plain embeds, and alignment is
        // left off because it relies on a "style" attribute the sanitizer never allowed anyway.
        blotFormatter2: {
            specs: [ImageSpec],
            align: {allowAligning: false},
            image: {allowAltTitleEdit: false}
        },
        table: false,
        'table-better': {
            language: 'en_US',
            menus: ['column', 'row', 'merge', 'table', 'cell', 'wrap', 'copy', 'delete'],
            toolbarTable: true
        },
        keyboard: {
            bindings: QuillTableBetter.keyboardBindings
        }
    }
}

let editorModules = {
    'modules/blotFormatter2': BlotFormatter,
    'modules/table-better': QuillTableBetter
}

onMounted(() => {

    // register the table formats (headers, cells...) before the module itself
    QuillTableBetter.register()

    // set the modules
    Quill.register(editorModules, true)

    // define the editor
    editor = new Quill(editorRef.value, editorOptions)

    // set the default toolbar
    const toolbar = editor.getModule('toolbar')
    toolbar.addHandler('image', imageUploadHandler)

    // Evernote exports to-dos as <div><input type="checkbox">text</div>: load them as Quill checklist items
    editor.clipboard.addMatcher('DIV', checkboxToChecklist)

    // set the default content and manage the changes
    loadHtml(model.value)
    editor.on('text-change', () => {
        model.value = contentHtml()
        emit('updated-content')
    })

    // add tabindex 2 to the editor
    editorRef.value.getElementsByClassName('ql-editor')[0].setAttribute('tabindex', '2')


})

onUnmounted(() => {

    editor.value = null
})

watch(() => model, (value) => {

    if (editor && value.value !== editor.root.innerHTML) {
        loadHtml(value.value)
    }
}, { deep: true })

/**
 * Loads stored HTML through Quill's clipboard instead of innerHTML: content Quill cannot render
 * (legacy imported <div> markup) becomes visible editor content, and scripts or event handlers
 * are never inserted into the page. The model is updated silently so opening a note never saves it.
 *
 * table-better needs the replacement fed through updateContents (a full delete of the previous
 * content plus the new one) rather than setContents, or tables it did not just render itself
 * (a freshly opened note, an external model change) come up without their column handles/menu.
 */
const loadHtml = (html) => {
    const delta = editor.clipboard.convert({ html: html ?? '' })
    editor.updateContents(new Delta().delete(editor.getLength()).concat(delta), 'silent')
    model.value = contentHtml()
}

/**
 * The editor's HTML, with table-better's internal "temporary" measuring elements stripped out:
 * left in, they would pollute both the autosave and the note-switch comparison above.
 */
const contentHtml = () => {
    editor.getModule('table-better').deleteTableTemporary('silent')

    return editor.root.innerHTML
}

const checkboxToChecklist = (node, delta) => {
    const checkbox = node.querySelector(':scope > input[type="checkbox"]')
    if (!checkbox) {
        return delta
    }

    const list = checkbox.hasAttribute('checked') ? 'checked' : 'unchecked'
    const ops = []
    delta.ops.forEach((op) => {
        if (typeof op.insert !== 'string') {
            ops.push(op)
            return
        }
        op.insert.split(/(\n)/).filter((part) => part !== '').forEach((part) => {
            ops.push(part === '\n'
                ? { insert: '\n', attributes: { ...op.attributes, list } }
                : { insert: part, ...(op.attributes ? { attributes: op.attributes } : {}) })
        })
    })

    return new Delta(ops)
}

const fileToBase64 = (file) => {
    return new Promise((resolve, reject) => {
        const reader = new FileReader()
        reader.onload = () => resolve(reader.result)
        reader.onerror = error => reject(error)
        reader.readAsDataURL(file)
    })
}

const imageUploadHandler = () => {
    const input = document.createElement('input')
    input.setAttribute('type', 'file')
    input.setAttribute('accept', 'image/*')
    input.click()

    input.onchange = async () => {
        const file = input.files[0]

        if (file) {
            const base64 = await fileToBase64(file)
            editor.insertEmbed(editor.getSelection().index, 'image', base64)
        }
    }
}

</script>

<template>
    <div data-test="note-body" class="quill-editor-container">
        <div ref="editorRef" class="quill-editor"></div>
    </div>
</template>
<style lang="scss">
.quill-editor-container {
    height: calc(100vh - 171px)
}
</style>
