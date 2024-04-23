<template>
    <div @contextmenu="openMenu($event, notebook)" class="lg:w-1/5 md:w-1/3 sm: w-1/2">
        <Link :href="'/notebook/' + notebook.id">
            <article class="border border-main2 overflow-hidden transition shadow-lg rounded lg:hover:scale-110 lg:hover:shadow-main1">
                <header class="bg-main2 p-2">
                    <h3 class="text-sm font-semibold text-white flex items-center overflow-auto text-ellipsis whitespace-nowrap">
                        <DocumentDuplicateIcon class="w-6 inline-block mr-2" />
                        {{ notebook.name }}
                    </h3>
                </header>
                <div class="px-4 py-2 text-white h-[150px] flex flex-col w-full">
                    <p class="text-sm h-full items-center flex text-center w-full justify-center">
                        <div>
                            <span class="text-4xl mr-2 text-main4">{{ notebook.notes_count }}</span>  notes
                        </div>
                    </p>
                    <p class="text-sm text-right italic">{{ DateHelper.formatDate(notebook.created_at) }}</p>
                </div>
            </article>
        </Link>
    </div>
</template>
<script setup>
import axios from "axios"
import DateHelper from "@/Helpers/DateHelper"
import ContextMenu from "@imengyu/vue3-context-menu"
import { defineEmits } from "vue"
import { DocumentDuplicateIcon } from "@heroicons/vue/24/outline/index.js"
import { notify } from "@kyvg/vue3-notification"
const props = defineProps({
    notebook: Object,
})

const emit = defineEmits([
    'delete'
])

const openMenu = (e, notebook) => {
    e.preventDefault()

    ContextMenu.showContextMenu({
        x: e.x,
        y: e.y,
        theme: 'dark',
        items: [
            {
                label: 'Eliminar',
                onClick: () => {

                    const notebookId = notebook.id
                    emit('delete', notebookId)

                    axios
                        .post('/notebooks/trash/' + notebookId)
                        .then(() => {

                            notify({
                                type: 'success',
                                text: 'Eliminado',
                            })

                        })
                        .catch((error) => {
                            notify({
                                type: 'error',
                                text: error.message,
                            })
                        })
                },
            },
        ]
    })

}

</script>
