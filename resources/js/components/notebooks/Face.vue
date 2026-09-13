<template>
    <div :data-test="'notebook-' + notebook.id" @contextmenu="openMenu($event, notebook)" class="lg:w-1/5 md:w-1/3 sm: w-1/2">
        <Link :href="'/notebook/' + notebook.id">
            <article class="border border-main2 overflow-hidden transition shadow-lg rounded-sm lg:hover:scale-110 lg:hover:shadow-main1">
                <header class="bg-main2 p-2">
                    <!-- Long names are cut with an ellipsis (full name on hover) instead of showing a scrollbar -->
                    <h3 data-test="notebook-name" :title="notebook.name" class="text-sm font-semibold text-white flex items-center min-w-0">
                        <DocumentDuplicateIcon class="w-6 shrink-0 mr-2" />
                        <span class="truncate">{{ notebook.name }}</span>
                    </h3>
                </header>
                <div class="px-4 py-2 text-white h-[150px] flex flex-col w-full">
                    <p class="text-sm h-full items-center flex text-center w-full justify-center">
                        <div>
                            <span data-test="notes-count" class="text-4xl mr-2 text-main4">{{ notebook.notes_count }}</span>  notes
                        </div>
                    </p>
                    <p data-test="notebook-date" class="text-xs text-right italic whitespace-nowrap">{{ DateHelper.formatDate(notebook.created_at) }}</p>
                </div>
            </article>
        </Link>
    </div>
</template>
<script setup>
import axios from "axios"
import DateHelper from "@/helpers/DateHelper"
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

                    if (!confirm('Are you sure you want to send this notebook to the trash?')) {
                        return
                    }

                    const notebookId = notebook.id

                    axios
                        .post('/notebooks/trash/' + notebookId)
                        .then(() => {
                            emit('delete', notebookId)

                            notify({
                                type: 'success',
                                text: 'Eliminado',
                            })

                        })
                        .catch((error) => {
                            notify({
                                type: 'error',
                                text: 'The notebook could not be sent to the trash',
                            })
                        })
                },
            },
        ]
    })

}

</script>
