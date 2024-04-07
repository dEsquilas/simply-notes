<script setup>
import { defineModel, ref, onMounted, onUnmounted, watch } from 'vue'
import Quill from 'quill'
import './quill.snow.css'

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
            ['link', 'image', 'video', 'clean']
        ],

    }
}



onMounted(() => {

    editor = new Quill(editorRef.value, editorOptions)

    editor.root.innerHTML = model.value

    const toolbar = editor.getModule('toolbar')
    toolbar.addHandler('image', () => {
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
    })

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
