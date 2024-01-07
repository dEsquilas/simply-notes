<script setup>
import editor from '@tinymce/tinymce-vue'
import { ref, watch } from 'vue'
import { notify } from "@kyvg/vue3-notification"

const emit = defineEmits(['update-note'])

const props = defineProps({
    note: {
        type: Object,
        required: true,
    },
})

const h = ref(window.innerHeight - 65 - 103)
const noteTitle = ref(props.note?.title)
const noteContent = ref(props.note?.content)
const tinyAPIKey = import.meta.env.VITE_TINY_API_KEY

let lastModified = -1
const autosaveTime = 1500

watch(() => props.note, (newNote) => {
    noteTitle.value = newNote.title
    noteContent.value = newNote.content
}, { deep: true })

const save = () => {

    lastModified = Date.now()

}

let autosave = setInterval(() => {
    if (lastModified != -1 && Date.now() - lastModified > autosaveTime) {
        //clearInterval(autosave)
        axios
            .post('/notes/update/' + props.note.id, {
                title: noteTitle.value,
                content: noteContent.value,
            })
            .then((response) => {
                notify({
                    type: 'success',
                    text: 'Updated',
                })

                emit('update-note', {note: response.data.note})

            })
            .catch((error) => {
                console.log(error)
            })

        lastModified = -1
        //clearInterval(autosave)
    }
}, autosaveTime)

</script>
<template>
    <div class="h-full">
        <input @keydown="save()" v-model="noteTitle" placeholder="Nueva nota" type="text" class="w-full bg-cblack border-none focus:outline-none focus:border-none focus:ring-0 text-4xl text-white pl-8 py-8">
        <editor @keydown="save()" v-model="noteContent" class="h-full"
                :api-key="tinyAPIKey"
                :init="{
                    height: h,
                    menubar: false,
                    skin: 'oxide-dark',
                    body_class: 'mceBlackBody',
                    plugins: [
                        'advlist',  'autolink',  'lists',  'link', 'image', 'charmap', 'preview',  'anchor', 'checklist', 'code',
                        'searchreplace', 'visualblocks', 'code', 'fullscreen', 'table',
                        'insertdatetime', 'media', 'table', 'code', 'help', 'wordcount',
                    ],
                    toolbar: 'undo redo | fontfamily fontsize | formatselect | ' +
                        'bold italic backcolor | alignleft aligncenter ' +
                        'alignright alignjustify | bullist numlist checklist outdent indent | table | code |' +
                        'removeformat',
                    content_style: `
                                body {
                                    font-family:Helvetica,Arial,sans-serif;
                                     font-size: 18px;
                                     padding: 15px 25px;
                                     background: #212121;
                                     color: #ffffff;
                                }
                                p {
                                    margin: 0px;
                                }
                                .mce-content-body::-webkit-scrollbar {
                                    width: 10px;
                                }
                                .mce-content-body::-webkit-scrollbar-track {
                                    background-color: #1a1a1a;
                                }
                                .mce-content-body::-webkit-scrollbar-thumb {
                                    background-color: #2d2d2d;
                                    border-radius: 10px;
                                }
                    `,
                    lists_indent_on_tab: false,
                    indentation: '40px',
                    resize: false,
                    setup: function(editor) {
                                editor.on('keydown', function(e) {
                                if (e.keyCode === 9) { // código de tecla para Tab
                                    const node = editor.selection.getNode(); // Obtiene el nodo actual
                                    if (editor.dom.is(node, 'li,li *')) { // Verifica si el nodo es parte de una lista
                                        e.preventDefault(); // Previene el comportamiento predeterminado
                                        if (e.shiftKey) {
                                            editor.execCommand('Outdent'); // Si se presiona Shift + Tab, disminuye la indentación
                                        } else {
                                            console.log('detected')
                                            editor.execCommand('Indent'); // Si solo se presiona Tab, aumenta la indentación
                                        }
                                    } else {
                                        if (!e.shiftKey) {
                                            e.preventDefault(); // Previene el comportamiento predeterminado
                                            editor.execCommand('mceInsertContent', false, '&#x9;'); // Inserta un carácter de tabulación
                                        }
                                    }
                                }
                                });
                    }

                }"
        />
    </div>
</template>
