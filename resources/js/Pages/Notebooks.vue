<script setup>
import axios from 'axios'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link } from '@inertiajs/vue3'
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
const notebooks = computed(() => props.notebooks)

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

</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <section class="flex flex-col max-w-[1200px] mx-auto w-full">
            <div class="w-full flex mt-8 mx-4">
                <header class="flex flex-row gap-4">
                    <div @click="createNotebook()" class="bg-main3 overflow-hidden shadow-sm rounded-lg text-white cursor-pointer hover:bg-main4 hover:text-cblack" :class="{'cursor-not-allowed': newNotebookName.length == 0}">
                        <div class="p-3 flex flex-row gap-4 items-center justify-center">
                            <h3 class="text-sm font-semibold">Create a new notebook</h3>
                            <PencilSquareIcon class="w-4" />
                        </div>
                    </div>
                    <input v-model="newNotebookName" type="text" class="bg-transparent border-0 rounded-xl text-white focus:outline-none focus:ring-0" placeholder="Notebook name...">
                </header>
            </div>
            <div class="max-w-[1200px] w-full m-auto py-12 grid lg:grid-cols-3 sm:grid-cols-2 gap-4 p-4 mt-0">
                <article v-for="notebook in notebooks" :key="notebook.id" class="w-full mx-auto gap-4">
                    <Link :href="'/notebook/' + notebook.id">
                    <div class="bg-main1 overflow-hidden shadow-sm rounded-lg hover:bg-main2 transition-colors">
                        <div class="p-6 text-white">
                            <h3 class="text-lg font-semibold">{{ notebook.name }}</h3>
                            <p class="text-sm">{{ notebook.description }}</p>
                        </div>
                    </div>
                    </Link>
                </article>
            </div>
        </section>
    </AuthenticatedLayout>
</template>
