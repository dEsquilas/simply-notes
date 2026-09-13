<script setup>
import { ref, watch } from 'vue'
import { notify } from "@kyvg/vue3-notification"
import QuillEditor from '@/components/quill/QuillEditor.vue'

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


let autosaveInterval = null
let recentNoteSwap = Date.now()
let lastModified = -1
const autosaveTime = 1500


watch(() => props.note, (newNote, oldNote) => {
    // Edits waiting for autosave belong to the note being left: save them before loading the new one
    if (oldNote && newNote?.id !== oldNote.id && lastModified !== -1) {
        save(oldNote, noteTitle.value, noteContent.value)
    }

    recentNoteSwap = Date.now()
    noteTitle.value = newNote.title
    noteContent.value = newNote.content
    if(newNote.content == null || newNote.content.length === 0){
        newNote.content = ""
    }
},{ deep: true })

const dispatchAutosave = () => {

    if(recentNoteSwap !== -1 && Date.now() - recentNoteSwap < 1000)
        return

    lastModified = Date.now()
    if(!autosaveInterval)
        autosaveInterval = setInterval(autosave, autosaveTime)

}

let autosave = () => {

    if (lastModified !== -1 && Date.now() - lastModified > autosaveTime)
        save()
}

const save = (note = props.note, title = noteTitle.value, content = noteContent.value) => {

    clearInterval(autosaveInterval)
    autosaveInterval = null
    lastModified = -1

    axios
        .post('/notes/update/' + note.id, {
            title: title,
            content: content,
        })
        .then((response) => {
            notify({
                type: 'neutral',
                text: 'Updated',
            })

            emit('update-note', {note: response.data.note})
        })
        .catch((error) => {
            const expired = [401, 419].includes(error.response?.status)
            notify({
                type: 'error',
                text: expired
                    ? 'Your session has expired: log in again to keep your changes'
                    : 'The note could not be saved',
            })
        })

}

let forceSave = (event) => {
    if(event.key === "s" && (event.ctrlKey || event.metaKey)){
        event.preventDefault()
        save()
    }
}

const onTitleKeydown = (event) => {
    forceSave(event)
    if (!event.defaultPrevented)
        dispatchAutosave()
}

</script>
<template>
    <div class="h-full">
        <input dusk="note-title" tabindex="1"
               @keydown="onTitleKeydown"
               v-model="noteTitle"
               placeholder="Nueva nota"
               type="text"
               class="text-ellipsis w-full bg-transparent border-none focus:outline-none focus:border-none focus:ring-0 text-4xl text-white px-8 py-8
               text-2xl
               md:text-4xl
               "
        >
        <QuillEditor @updated-content="dispatchAutosave"
                     @keydown.ctrl="forceSave"
                    v-model="noteContent"
        />
    </div>
</template>
