<script setup>
import axios from 'axios'
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout.vue'
import { notify } from "@kyvg/vue3-notification"
import { defineProps, ref } from 'vue'
import { TrashIcon, ArrowUturnUpIcon } from '@heroicons/vue/24/outline'



const props = defineProps({
    notebooks: {
        type: Array,
        required: true,
    },
    notes: {
        type: Array,
        default: () => [],
    },
})

const currentNotebooks = ref(props.notebooks)
const currentNotes = ref(props.notes)


const deleteNotebook = (notebookId) => {

    let confirmDelete = confirm('Are you sure you want to delete this notebook?')

    if (!confirmDelete) {
        return
    }

    // The notebook leaves the list only once the server has deleted it
    axios.post(`/notebooks/trash/delete/${notebookId}`).then(() => {
        currentNotebooks.value = currentNotebooks.value.filter(notebook => notebook.id !== notebookId)
        notify({ title: 'Success', text: 'Notebook deleted successfully', type: 'success' })
    }).catch(() => {
        notify({ title: 'Error', text: 'Failed to delete notebook', type: 'error' })
    })
}

const restoreNotebook = (notebookId) => {

    let confirmRestore = confirm('Are you sure you want to restore this notebook?')

    if (!confirmRestore) {
        return
    }

    axios.post(`/notebooks/trash/restore/${notebookId}`).then(() => {
        currentNotebooks.value = currentNotebooks.value.filter(notebook => notebook.id !== notebookId)
        notify({ title: 'Success', text: 'Notebook restored successfully', type: 'success' })
    }).catch(() => {
        notify({ title: 'Error', text: 'Failed to restore notebook', type: 'error' })
    })

}

const restoreNote = (noteId) => {

    axios.post(`/notes/trash/restore/${noteId}`).then(() => {
        currentNotes.value = currentNotes.value.filter(note => note.id !== noteId)
        notify({ title: 'Success', text: 'Note restored successfully', type: 'success' })
    }).catch(() => {
        notify({ title: 'Error', text: 'Failed to restore note', type: 'error' })
    })

}


</script>

<template>
    <Head title="Notebook Trash" />

    <AuthenticatedLayout>
        <section class="flex flex-col max-w-[1200px] mx-auto w-full">
            <h2 class="pt-8 px-4 text-4xl font-extrabold text-white mb-2">Trash</h2>

            <div class="max-w-[1200px] w-full m-auto grid lg:grid-cols-3 sm:grid-cols-2 gap-4 p-4 mt-0">
                <article v-for="notebook in currentNotebooks" :key="notebook.id" :data-test="'trashed-notebook-' + notebook.id" class="w-full mx-auto gap-4">
                    <div class="bg-main1 overflow-hidden shadow-sm rounded-lg hover:bg-main2 transition-colors">
                        <div class="p-6 text-white flex justify-between">
                            <h3 data-test="trashed-notebook-name" class="text-lg font-semibold">{{ notebook.name }}</h3>
                            <p class="text-sm">{{ notebook.description }}</p>
                            <div class="flex">
                                <!-- Icon components only pass class through, so their test hook is a test-* class -->
                                <ArrowUturnUpIcon @click=restoreNotebook(notebook.id) class="test-restore-notebook w-6 h-6 bg-blue mr-4 cursor-pointer" />
                                <TrashIcon @click=deleteNotebook(notebook.id) class="test-delete-notebook w-6 h-6 text-red-500 cursor-pointer" />
                            </div>
                        </div>
                    </div>
                </article>
                <div data-test="trash-empty" class="text-white" v-show="currentNotebooks.length == 0 && currentNotes.length == 0">
                    Nothing to show here
                </div>
            </div>

            <section v-show="currentNotes.length > 0" class="p-4">
                <h3 class="text-2xl font-bold text-white mb-4">Notes</h3>
                <ul class="grid lg:grid-cols-3 sm:grid-cols-2 gap-4">
                    <li v-for="note in currentNotes" :key="note.id" :data-test="'trashed-note-' + note.id" class="bg-main1 rounded-lg p-6 text-white flex justify-between gap-4">
                        <div class="overflow-hidden">
                            <h4 data-test="trashed-note-title" class="font-semibold truncate">{{ note.title || 'Nueva nota' }}</h4>
                            <p class="text-sm italic truncate">{{ note.notebook.name }}</p>
                        </div>
                        <!-- Icon components only pass class through, so their test hook is a test-* class -->
                        <ArrowUturnUpIcon @click="restoreNote(note.id)" class="test-restore-note w-6 h-6 shrink-0 cursor-pointer" />
                    </li>
                </ul>
            </section>
        </section>
    </AuthenticatedLayout>
</template>
