<script setup>
import { defineModel, ref, onMounted, onUnmounted, watch } from 'vue';
import Quill from 'quill';
import 'quill/dist/quill.snow.css';

const model = defineModel()

const editorRef = ref(null);

let editor = null;

onMounted(() => {

    editor = new Quill(editorRef.value, {
        theme: 'snow',
        modules: {
            toolbar: [
                [{ 'header': '1'}, {'header': '2'}, { 'font': [] }],
                [{size: []}],
                ['bold', 'italic', 'underline', 'strike', 'blockquote'],
                [{'list': 'ordered'}, {'list': 'bullet'},
                    {'indent': '-1'}, {'indent': '+1'}],
                ['link', 'image', 'video'],
                ['clean']
            ]
        }
    });

    editor.root.innerHTML = model.value

    editor.on('text-change', () => {
        model.value = editor.root.innerHTML;
    });

});

onUnmounted(() => {

    editor.value = null;
});

watch(() => model, (value) => {

    if (editor && value.value !== editor.root.innerHTML) {
        editor.root.innerHTML = value.value;
    }
}, { deep: true });


</script>

<template>
    <div>
        <div ref="editorRef" class="quill-editor"></div>
    </div>
</template>
