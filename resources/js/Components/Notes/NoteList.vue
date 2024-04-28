<script setup>
import ContextMenu from '@imengyu/vue3-context-menu'
import NoteExtract from '@/Components/Notes/NoteExtract.vue'
import { notify } from "@kyvg/vue3-notification"
import { onMounted, ref } from 'vue'

const emit = defineEmits(['change-note', 'delete-note'])

const props = defineProps({
    notes: {
        type: Array,
        required: true,
    },
    currentNoteId: {
        type: Number,
        required: true,
    },
    filter: {
        type: String,
        required: true,
    },
})

const scrollbar = ref(null)

onMounted(() => {
    const elementToMove = props.notes.findIndex((note) => note.id === props.currentNoteId)
    const noteHeight = 140
    scrollbar.value.scrollTop = noteHeight * elementToMove
})

const openMenu = (e, note) => {
    e.preventDefault()

    ContextMenu.showContextMenu({
        x: e.x,
        y: e.y,
        theme: 'dark',
        items: [
            {
                label: 'Eliminar',
                onClick: () => {
                    emit('delete-note', {note: note})
                    axios
                        .post('/notes/trash/' + note.id)
                        .then((response) => {

                            if(response.status !== 200)
                                throw new Error(response.data.message)

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

const applyFilter = (filter, note) => {
    if (filter === "")
        return true
    else
        return note.title.toLowerCase().includes(filter.toLowerCase()) || note.content?.toLowerCase().includes(filter.toLowerCase())
}

</script>
<template>
    <div class="overflow-auto note-list" ref="scrollbar">
        <ul>
            <li v-for="note in notes" :key="note.id" v-show="applyFilter(filter, note)" @click="$emit('change-note', note)" @contextmenu="openMenu($event, note)">
                <note-extract :current="note.id === currentNoteId" :note="note"></note-extract>
            </li>
        </ul>
    </div>
</template>
<style lang="scss">
.note-list{

    height: calc(100vh - 185px); // 65 + 120
}

</style>
