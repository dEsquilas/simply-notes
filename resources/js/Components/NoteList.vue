<script setup>
import ContextMenu from '@imengyu/vue3-context-menu'
import NoteExtract from '@/Components/NoteExtract.vue'
import Note from "@/Components/Note.vue"
import {notify} from "@kyvg/vue3-notification";

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

                            notify({
                                type: 'success',
                                text: 'Eliminado',
                            })

                        })
                        .catch((error) => {
                            console.log(error)
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
    <div class="overflow-auto h-full note-list">
        <ul>
            <li v-for="note in notes" :key="note.id" v-show="applyFilter(filter, note)" @click="$emit('change-note', note)" @contextmenu="openMenu($event, note)">
                <note-extract :current="note.id == currentNoteId" :note="note"></note-extract>
            </li>
        </ul>
    </div>
</template>
<style lang="scss">
.note-list{

    height: calc(100vh - 185px); // 65 + 120

    &::-webkit-scrollbar {
        width: 10px;
    }

    &::-webkit-scrollbar-track {
        background-color: #1a1a1a;
        border-radius: 10px;
    }

    &::-webkit-scrollbar-thumb {
        background-color: #2d2d2d;
        border-radius: 10px;
    }

}

</style>
