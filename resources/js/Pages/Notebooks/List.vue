<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <section class="flex flex-col max-w-[1200px] mx-auto w-full">
            <div class="w-full flex mt-8 mx-4">
                <header class="flex flex-row gap-4">
                    <div @click="createNotebook()" class="bg-main3 overflow-hidden shadow-sm rounded-lg text-white cursor-pointer hover:bg-main4 hover:text-cblack" :class="{'cursor-not-allowed': newNotebookName.length === 0}">
                        <div class="p-3 flex flex-row gap-4 items-center justify-center">
                            <h3 class="text-sm font-semibold">Create a new notebook</h3>
                            <PencilSquareIcon class="w-4" />
                        </div>
                    </div>
                    <input v-model="newNotebookName" type="text" class="bg-transparent border-0 rounded-xl text-white focus:outline-none focus:ring-0" placeholder="New notebook name...">
                </header>
            </div>
            <div class="max-w-[1200px] w-full m-auto py-12 grid lg:grid-cols-3 sm:grid-cols-2 gap-4 p-4 mt-0">
                <notebook-face @delete="deleteNotebook" v-for="notebook in notebooks" :key="notebook.id" :notebook="notebook"  />
            </div>
        </section>
    </AuthenticatedLayout>
</template>
<script setup>
import axios from 'axios'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import ContextMenu from '@imengyu/vue3-context-menu'
import NotebookFace from '@/Components/Notebooks/Face.vue'
import { notify } from "@kyvg/vue3-notification"
import { PencilSquareIcon } from '@heroicons/vue/24/outline'
import { computed, ref } from 'vue'

const props = defineProps({
    notebooks: {
        type: Array,
        required: true,
    },
})

const newNotebookName = ref("")
const notebooksIn = ref(props.notebooks)
const notebooks = computed(() => notebooksIn.value)

const createNotebook = () => {
    if (newNotebookName.value.length > 0) {
        axios
            .post('/notebooks/create', {
                name: newNotebookName.value,
            })
            .then((response) => {
                notebooks.value.push(response.data.notebook)
                newNotebookName.value = ""
                notify({
                    type: 'success',
                    text: 'Created',
                })
            })
            .catch((error) => {
                notify({
                    type: 'error',
                    text: error.message,
                })
            })
    }
}

const deleteNotebook = (notebookId) => {
    notebooksIn.value = notebooksIn.value.filter((notebook) => notebook.id !== notebookId)
}

</script>
