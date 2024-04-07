<script setup>
import { Head } from '@inertiajs/vue3'
import {
    ArrowPathIcon,
    NewspaperIcon,
    PlusCircleIcon,
    XCircleIcon
} from '@heroicons/vue/24/outline'
import { ref, computed } from 'vue'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import Note from '@/Components/Notes/Note.vue'
import NoteList from '@/Components/Notes/NoteList.vue'


const props = defineProps({
    inNotebook: {
        type: Object,
        required: true,
    },
    inNotes: {
        type: Array,
        required: true,
    },
    currentNote: {
        type: Object,
        required: false,
    },
})

const notebook = ref(props.inNotebook)
const notes = computed(() => props.inNotes)
const currentNote = ref(props.currentNote || notes.value[0])
const filter = ref("")
const isCreating = ref(false)

const newNote = () => {

    isCreating.value = true

    axios
        .post('/notes/create/' + notebook.value.id)
        .then((response) => {
            notes.value.unshift(response.data.note)
            currentNote.value = []
            currentNote.value = response.data.note
            isCreating.value = false
        })
        .catch((error) => {
            console.log(error)
            isCreating.value = true
        })
}

const changeNote = (note) => {
    currentNote.value = []
    currentNote.value = note
}

const updateNote = (data) =>{

    const newNoteId = data.note.id
    const index = notes.value.findIndex((note) => note.id === newNoteId)
    notes.value[index] = data.note

}

const deleteNote = (data) => {

    const newNoteId = data.note.id
    const index = notes.value.findIndex((note) => note.id === newNoteId)

    notes.value.splice(index, 1)

    if (newNoteId === currentNote.value.id) {
        currentNote.value = []
        currentNote.value = notes.value[0]
        console.log("changing")
    }

}

</script>

<template>
    <Head title="Notebook" />

    <AuthenticatedLayout>
        <section class="flex flex-row w-full">
            <aside class="bg-cblack w-[350px] overflow-hidden border-r border-1 border-cgray">
                <header class="p-4  border-1 border-b border-cgray">
                    <h3 class="text-xl font-bold text-white mb-4"><NewspaperIcon class="w-6 inline-block mr-4" />Notas</h3>
                    <div class="flex flex-row relative">
                        <input v-model="filter" type="text" class="w-[250px] bg-transparent border-1 rounded-xl text-white focus:outline-none" placeholder="Buscar...">
                        <XCircleIcon v-show="filter.length !== 0" class="w-6 ml-4 text-main2 cursor-pointer hover:opacity-80 absolute right-[80px] top-[9px]" @click="filter = ''"></XCircleIcon>
                        <PlusCircleIcon v-show="!isCreating" class="w-10 ml-4 text-main4 cursor-pointer hover:opacity-80" @click="newNote()"></PlusCircleIcon>
                        <ArrowPathIcon v-show="isCreating" class="animate-spin w-10 ml-4 text-main4 "></ArrowPathIcon>
                    </div>
                </header>
                <div v-if="!notes || notes.length === 0" class="text-white p-4">No hay notas</div>
                <note-list v-if="notes && notes.length > 0" @delete-note="deleteNote" @change-note="changeNote" :current-note-id="currentNote.id" :notes="notes" :filter="filter"></note-list>
            </aside>
            <article class="flex-grow max-w-[calc(100%-350px)]">
                <note v-if="currentNote" @update-note="updateNote" :note="currentNote"></note>
            </article>
        </section>
    </AuthenticatedLayout>
</template>
