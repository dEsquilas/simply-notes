<script setup>
import BlotFormatter from "quill-blot-formatter"
import { ImageDrop } from "quill-image-drop-module"
import { ref, onMounted, watch } from 'vue'
import { notify } from "@kyvg/vue3-notification"
import QuillEditor from '@/Components/Quill/QuillEditor.vue'

const emit = defineEmits(['update-note'])

const props = defineProps({
    note: {
        type: Object,
        required: true,
    },
})


const noteTitle = ref(props.note?.title)
const noteContent = ref(props.note?.content)

if (noteContent.value == null || noteContent.value.length === 0) {
    noteContent.value = ""
}


let lastModified = -1
const autosaveTime = 1500
let autosaveInterval = null

watch(() => props.note, (newNote) => {
    noteTitle.value = newNote.title
    noteContent.value = newNote.content
    if(newNote.content == null || newNote.content.length === 0){
        newNote.content = "<p>Start typing...</p>"
    }
},{ deep: true })

const dispatchAutosave = () => {

    lastModified = Date.now()
    if(!autosaveInterval)
        autosaveInterval = setInterval(autosave, autosaveTime)

}

let autosave = () => {

    if (lastModified !== -1 && Date.now() - lastModified > autosaveTime)
        save();
}

const save = () => {

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

let forceSave = (event) => {
    if(event.key === "s" && event.ctrlKey){
        event.preventDefault();
        save();
    }
}

</script>
<template>
    <div class="h-full">
        <input @keydown="dispatchAutosave()" v-model="noteTitle" placeholder="Nueva nota" type="text" class="w-full bg-cblack border-none focus:outline-none focus:border-none focus:ring-0 text-4xl text-white pl-8 py-8">
        <QuillEditor @updated-content="dispatchAutosave"
                     @keydown.ctrl="forceSave"
                    v-model="noteContent"
        />
    </div>
</template>
