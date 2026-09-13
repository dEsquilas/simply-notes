<script setup>
import { defineModel, defineEmits, ref, onMounted, onUnmounted, watch } from 'vue'
import Quill from 'quill'
import ImageResize from 'quill-image-resize'
import * as QuillTableUI from 'quill-table-ui'

import './quill.snow.scss'
import './quill-table-ui.scss'

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
            ['link', 'image', 'video', 'clean', 'table']
        ],
        imageResize: {},
        table: true,
        tableUI: true
    }
}

let editorModules = {
    'modules/imageResize': ImageResize,
    'modules/tableUI': QuillTableUI.default
}

onMounted(() => {

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
        model.value = editor.root.innerHTML
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
 */
const loadHtml = (html) => {
    editor.setContents(editor.clipboard.convert({ html: html ?? '' }), 'silent')
    model.value = editor.root.innerHTML
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
