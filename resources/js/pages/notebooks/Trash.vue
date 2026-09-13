<script setup>
import axios from 'axios'
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout.vue'
import DateHelper from '@/helpers/DateHelper'
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
    retentionDays: {
        type: Number,
        required: true,
    },
})

const currentNotebooks = ref(props.notebooks)
const currentNotes = ref(props.notes)

const purgeDate = (deletedAt) => {
    const date = new Date(deletedAt)
    date.setDate(date.getDate() + props.retentionDays)
    return date
}

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

const deleteNote = (noteId) => {

    let confirmDelete = confirm('Are you sure you want to permanently delete this note?')

    if (!confirmDelete) {
        return
    }

    axios.post(`/notes/trash/delete/${noteId}`).then(() => {
        currentNotes.value = currentNotes.value.filter(note => note.id !== noteId)
        notify({ title: 'Success', text: 'Note deleted permanently', type: 'success' })
    }).catch(() => {
        notify({ title: 'Error', text: 'Failed to delete note', type: 'error' })
    })

}

const emptyTrash = () => {

    let confirmEmpty = confirm('Are you sure you want to permanently delete everything in the trash?')

    if (!confirmEmpty) {
        return
    }

    axios.post('/notebooks/trash/empty').then(() => {
        currentNotebooks.value = []
        currentNotes.value = []
        notify({ title: 'Success', text: 'Trash emptied successfully', type: 'success' })
    }).catch(() => {
        notify({ title: 'Error', text: 'Failed to empty the trash', type: 'error' })
    })

}


</script>

<template>
    <Head title="Notebook Trash" />

    <AuthenticatedLayout>
        <section class="flex flex-col max-w-[1200px] mx-auto w-full">
            <div class="flex items-center justify-between pt-8 px-4 mb-2">
                <h2 class="text-4xl font-extrabold text-white">Trash</h2>
                <button
                    data-test="empty-trash"
                    @click="emptyTrash()"
                    v-show="currentNotebooks.length > 0 || currentNotes.length > 0"
                    class="bg-main3 text-white text-sm font-semibold px-4 py-2 rounded-lg hover:bg-main4 hover:text-cblack transition-colors cursor-pointer"
                >
                    Empty trash
                </button>
            </div>

            <div class="max-w-[1200px] w-full mx-auto grid lg:grid-cols-3 sm:grid-cols-2 gap-4 p-4 mt-0">
                <article v-for="notebook in currentNotebooks" :key="notebook.id" :data-test="'trashed-notebook-' + notebook.id" class="w-full mx-auto gap-4">
                    <div class="bg-main1 overflow-hidden shadow-xs rounded-lg hover:bg-main2 transition-colors">
                        <div class="p-6 text-white flex justify-between">
                            <div class="overflow-hidden">
                                <h3 data-test="trashed-notebook-name" class="text-lg font-semibold truncate">{{ notebook.name }}</h3>
                                <p data-test="trashed-notebook-deleted-at" class="text-xs italic">Deleted {{ DateHelper.formatDate(notebook.deleted_at) }}</p>
                                <p data-test="trashed-notebook-purge-at" class="text-xs italic">Purges on {{ DateHelper.formatDate(purgeDate(notebook.deleted_at)) }}</p>
                            </div>
                            <div class="flex shrink-0">
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
                            <p data-test="trashed-note-deleted-at" class="text-xs italic">Deleted {{ DateHelper.formatDate(note.deleted_at) }}</p>
                            <p data-test="trashed-note-purge-at" class="text-xs italic">Purges on {{ DateHelper.formatDate(purgeDate(note.deleted_at)) }}</p>
                        </div>
                        <!-- Icon components only pass class through, so their test hook is a test-* class -->
                        <div class="flex shrink-0">
                            <ArrowUturnUpIcon @click="restoreNote(note.id)" class="test-restore-note w-6 h-6 mr-4 cursor-pointer" />
                            <TrashIcon @click="deleteNote(note.id)" class="test-delete-note w-6 h-6 text-red-500 cursor-pointer" />
                        </div>
                    </li>
                </ul>
            </section>
        </section>
    </AuthenticatedLayout>
</template>
