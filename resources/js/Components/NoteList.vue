<script setup>
import NoteExtract from '@/Components/NoteExtract.vue'
import Note from "@/Components/Note.vue"

const props = defineProps({
    notes: {
        type: Array,
        required: true,
    },
})

</script>
<template>
    <div class="overflow-auto h-full note-list">
        <ul>
            <li v-for="note in notes" :key="note.id">
                <note-extract :note="note"></note-extract>
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
