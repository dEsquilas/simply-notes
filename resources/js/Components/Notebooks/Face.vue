<template>
    <article @contextmenu="openMenu($event, notebook)" class="w-full mx-auto gap-4">
        <Link :href="'/notebook/' + notebook.id">
            <div class="border-2 border-main2 overflow-hidden shadow-sm rounded-lg hover:bg-main2 transition-colors">
                <div class="p-6 text-white">
                    <h3 class="text-lg font-semibold">{{ notebook.name }}</h3>
                    <p class="text-sm">{{ notebook.description }}</p>
                </div>
            </div>
        </Link>
    </article>
</template>
<script setup>
import ContextMenu from "@imengyu/vue3-context-menu"
import axios from "axios"
import { defineEmits } from "vue"
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
