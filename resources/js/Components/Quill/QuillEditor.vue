<script setup>
import { defineModel, ref, onMounted, onUnmounted, watch } from 'vue'
import Quill from 'quill'
import ImageResize from 'quill-image-resize'
import * as QuillTableUI from 'quill-table-ui'

import './quill.snow.css'
import './quill-table-ui.scss'

const model = defineModel()

const editorRef = ref(null)

let editor = null
let editorOptions = {
    theme: 'snow',
    placeholder: 'Start typing...',
    modules: {
        toolbar: [
            [{ 'header': '1'}, {'header': '2'}, { 'font': [] }],
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

    // set the default content and manage the changes
    editor.root.innerHTML = model.value
    editor.on('text-change', () => {
        model.value = editor.root.innerHTML
    })


})

onUnmounted(() => {

    editor.value = null
})

watch(() => model, (value) => {

    if (editor && value.value !== editor.root.innerHTML) {
        editor.root.innerHTML = value.value
    }
}, { deep: true })

const fileToBase64 = (file) => {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = () => resolve(reader.result);
        reader.onerror = error => reject(error);
        reader.readAsDataURL(file);
    });
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

const tbl = () => {
    const table = editor.getModule('table');
    table.insertTable(3, 3);
    console.log('Inserted')
}

const findTableParent = (element) => {
    if (element.tagName === 'TABLE') {
        return element
    } else {
        return findTableParent(element.parentElement)
    }
}

</script>

<template>
    <div class="quill-editor-container">
        <div ref="editorRef" class="quill-editor"></div>
    </div>
</template>
<style lang="scss">
.quill-editor-container {
    height: calc(100vh - 171px)
}
</style>
