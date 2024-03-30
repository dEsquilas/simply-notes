<script setup>
import axios from 'axios'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link } from '@inertiajs/vue3'
import { notify } from "@kyvg/vue3-notification"
import { defineProps, ref } from 'vue'
import { TrashIcon } from '@heroicons/vue/24/outline'



const props = defineProps({
    notebooks: {
        type: Array,
        required: true,
    },
})

const currentNotebooks = ref(props.notebooks)


const deleteNotebook = async (notebookId) => {

    let confirmDelete = confirm('Are you sure you want to delete this notebook?')

    if (!confirmDelete) {
        return
    }

    try {
        currentNotebooks.value = currentNotebooks.value.filter(notebook => notebook.id !== notebookId)
        axios.post(`/notebooks/trash/delete/${notebookId}`).then(() => {
            notify({ title: 'Success', text: 'Notebook deleted successfully', type: 'success' })
        })
    } catch (error) {
        notify({ title: 'Error', text: 'Failed to delete notebook', type: 'error' })
    }
}


</script>

<template>
    <Head title="Notebook Trash" />

    <AuthenticatedLayout>
        <section class="flex flex-col max-w-[1200px] mx-auto w-full">
            <h2 class="pt-8 px-4 text-4xl font-extrabold text-white mb-2">Trash</h2>

            <div class="max-w-[1200px] w-full m-auto grid lg:grid-cols-3 sm:grid-cols-2 gap-4 p-4 mt-0">
                <article v-for="notebook in currentNotebooks" :key="notebook.id" class="w-full mx-auto gap-4">
                    <div class="bg-main1 overflow-hidden shadow-sm rounded-lg hover:bg-main2 transition-colors">
                        <div class="p-6 text-white flex justify-between">
                            <h3 class="text-lg font-semibold">{{ notebook.name }}</h3>
                            <p class="text-sm">{{ notebook.description }}</p>
                            <TrashIcon @click=deleteNotebook(notebook.id) class="w-6 h-6 bg-blue" />
                        </div>
                    </div>
                </article>
                <div class="text-white" v-show="currentNotebooks.length == 0">
                    Nothing to show here
                </div>
            </div>
        </section>
    </AuthenticatedLayout>
</template>
