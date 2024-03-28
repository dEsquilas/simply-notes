<script setup>
import { QuillEditor, Delta } from '@vueup/vue-quill'
import '../../css/vue-quill.snow.scss'
import { ref, watch } from 'vue'
import { notify } from "@kyvg/vue3-notification"

const emit = defineEmits(['update-note'])

const props = defineProps({
    note: {
        type: Object,
        required: true,
    },
})

const h = ref(window.innerHeight - 65 - 103)
const noteTitle = ref(props.note?.title)
const noteContent = ref(props.note?.content)

console.log(noteContent.value)

let lastModified = -1
const autosaveTime = 1500

watch(() => props.note, (newNote) => {
    noteTitle.value = newNote.title
    noteContent.value = newNote.content
}, { deep: true })

const save = () => {

    lastModified = Date.now()

}

let autosave = setInterval(() => {
    if (lastModified != -1 && Date.now() - lastModified > autosaveTime) {
        //clearInterval(autosave)
        axios
            .post('/notes/update/' + props.note.id, {
                title: noteTitle.value,
                content: noteContent.value,
            })
            .then((response) => {
                notify({
                    type: 'success',
                    text: 'Updated',
                })

                emit('update-note', {note: response.data.note})

            })
            .catch((error) => {
                console.log(error)
            })

        lastModified = -1
        //clearInterval(autosave)
    }
}, autosaveTime)

</script>
<template>
    <div class="h-full">
        <input @keydown="save()" v-model="noteTitle" placeholder="Nueva nota" type="text" class="w-full bg-cblack border-none focus:outline-none focus:border-none focus:ring-0 text-4xl text-white pl-8 py-8">
        <QuillEditor @keydown="save()" class="h-full"
                     :toolbar="[
                            [{ header: [1, 2, 3, false] }],
                            ['bold', 'italic', 'underline', 'strike'],
                            ['blockquote', 'code-block'],
                            [{ header: 1 }, { header: 2 }],
                            [{ list: 'ordered' }, { list: 'bullet' }],
                            [{ indent: '-1' }, { indent: '+1' }],
                            [{ color: [] }, { background: [] }],
                            [{ align: [] }],
                            ['link', 'video', 'image'],
                            ['clean'], // remove formatting button
                    ]"
                    v-model:content="noteContent"
                    content-type="html"
                    theme="snow"
        />
    </div>
</template>
