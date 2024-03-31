<script setup>
import BlotFormatter from "quill-blot-formatter"
import { ImageDrop } from "quill-image-drop-module"
import { ref, watch } from 'vue'
import { notify } from "@kyvg/vue3-notification"
import { QuillEditor } from '@vueup/vue-quill'
import '../../css/vue-quill.snow.scss'

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

console.log(noteContent)

if (noteContent.value == null || noteContent.value.length === 0) {
    noteContent.value = "<p>Start typing...</p>"
}


let lastModified = -1
const autosaveTime = 1500
let autosaveInterval = null

/* Quill modules */

const modules = ref([
    {
        name: 'blobFormatter',
        module: BlotFormatter
    },
    {
        name: 'imageDrop',
        module: ImageDrop
    }
])

watch(() => props.note, (newNote) => {
    noteTitle.value = newNote.title
    noteContent.value = newNote.content
    if(newNote.content == null || newNote.content.length === 0){
        newNote.content = "<p>Start typing...</p>"
    }
}, { deep: true })

const save = () => {

    lastModified = Date.now()
    if(!autosaveInterval)
        autosaveInterval = setInterval(autosave, autosaveTime)

}

let autosave = () => {
    if (lastModified !== -1 && Date.now() - lastModified > autosaveTime) {
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
                clearInterval(autosaveInterval);
                autosaveInterval = null

            })
            .catch((error) => {
                console.log(error)
            })

        lastModified = -1
    }
}

</script>
<template>
    <div class="h-full">
        <input @keydown="save()" v-model="noteTitle" placeholder="Nueva nota" type="text" class="w-full bg-cblack border-none focus:outline-none focus:border-none focus:ring-0 text-4xl text-white pl-8 py-8">
        <QuillEditor @keydown="save()" class="h-full overflow-auto"
                     :toolbar="[
                            [{ header: [1, 2, 3, false] }],
                            ['bold', 'italic', 'underline', 'strike'],
                            ['blockquote', 'code-block'],
                            [{ header: 1 }, { header: 2 }],
                            [{ list: 'ordered' }, { list: 'bullet' }],
                            [{ indent: '-1' }, { indent: '+1' }],
                            [{ color: [] }, { background: [] }],
                            [{ align: [] }],
                            ['link', 'video', 'image'],
                            ['clean'], // remove formatting button
                    ]"
                    v-model:content="noteContent"
                    content-type="html"
                    theme="snow"
                    :modules="modules"
        />
    </div>
</template>
