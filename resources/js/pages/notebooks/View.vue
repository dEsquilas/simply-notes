<script setup>
import {
    ArrowPathIcon,
    ChevronDoubleLeftIcon,
    ChevronDoubleRightIcon,
    NewspaperIcon,
    PlusCircleIcon,
    XCircleIcon
} from '@heroicons/vue/24/outline'
import { ref, computed, onMounted } from 'vue'
import AuthenticatedLayout from '@/layouts/AuthenticatedLayout.vue'
import Note from '@/components/notes/Note.vue'
import NoteList from '@/components/notes/NoteList.vue'
import { notify } from '@kyvg/vue3-notification'


const props = defineProps({
    inNotebook: {
        type: Object,
        required: true,
    },
    inNotes: {
        type: Array,
        required: true,
    },
    currentNote: {
        type: Object,
        required: false,
    },
})

const filter = ref("")
const isCreating = ref(false)
const isMobile = ref(false)
const isSidebarVisible = ref(true)
const notebook = ref(props.inNotebook)
const notes = computed(() => props.inNotes)
const currentNote = ref(props.currentNote || notes.value[0])

const desktopSidebarStorageKey = 'notebooks.desktopSidebarVisible'

const readDesktopSidebarVisible = () => {
    // The SSR bundle renders without window: fall back to the default there
    if (typeof window === 'undefined')
        return true
    try {
        const stored = window.localStorage.getItem(desktopSidebarStorageKey)
        return stored === null ? true : stored === 'true'
    } catch (e) {
        return true
    }
}

const isDesktopSidebarVisible = ref(readDesktopSidebarVisible())

const toggleDesktopSidebar = () => {
    isDesktopSidebarVisible.value = !isDesktopSidebarVisible.value
    try {
        window.localStorage.setItem(desktopSidebarStorageKey, isDesktopSidebarVisible.value ? 'true' : 'false')
    } catch (e) {
        // localStorage unavailable (e.g. private browsing): state just won't persist
    }
}

const maxMobileWidth = 768

onMounted(() => {

    // innerWidth is already in CSS pixels: dividing by devicePixelRatio put Retina screens in mobile mode
    if (window.innerWidth < maxMobileWidth) {
        isMobile.value = true
    }else
        isMobile.value = false
    window.addEventListener('resize', () => {
        if (window.innerWidth < maxMobileWidth)
            isMobile.value = true
        else
            isMobile.value = false
    })
})

const newNote = () => {

    isCreating.value = true

    axios
        .post('/notes/create/' + notebook.value.id)
        .then((response) => {
            notes.value.unshift(response.data.note)
            currentNote.value = []
            currentNote.value = response.data.note
            isCreating.value = false
        })
        .catch(() => {
            isCreating.value = false
            notify({
                type: 'error',
                text: 'The note could not be created',
            })
        })
}

const changeNote = (note) => {
    currentNote.value = []
    currentNote.value = note
    if(isMobile.value)
        isSidebarVisible.value = false
    window.history.replaceState({}, '', '/notebook/' + notebook.value.id + '/note/' + note.id)
}

const updateNote = (data) =>{

    const newNoteId = data.note.id
    const index = notes.value.findIndex((note) => note.id === newNoteId)
    notes.value[index] = data.note

}

const deleteNote = (data) => {

    const newNoteId = data.note.id
    const index = notes.value.findIndex((note) => note.id === newNoteId)

    notes.value.splice(index, 1)

    if (newNoteId === currentNote.value.id) {
        currentNote.value = []
        currentNote.value = notes.value[0]
        console.log("changing")
    }

}
</script>
<template>
    <Head title="Notebook" />
    <AuthenticatedLayout>
        <div class="w-full">
            <section class="flex flex-row w-full h-full">
                <aside
                    data-test="notes-sidebar"
                    class="overflow-hidden border-r border-cgray
                            w-full
                            md:w-[350px]
                            "
                    :class="{
                        'hidden': !isSidebarVisible && isMobile,
                         'w-full': isSidebarVisible && isMobile,
                         'md:hidden': !isDesktopSidebarVisible,
                    }"
                >
                    <header class="p-4 border-b border-cgray">
                        <h3 class="text-xl font-bold text-white mb-4 flex flex-row items-center justify-between">
                            <span>
                                <NewspaperIcon class="w-6 inline-block mr-4" />
                                Notas
                            </span>
                            <button
                                data-test="hide-notes-sidebar"
                                @click="toggleDesktopSidebar()"
                                title="Ocultar listado"
                                class="hidden md:inline-block text-main4 cursor-pointer hover:opacity-80"
                            >
                                <ChevronDoubleLeftIcon class="test-hide-sidebar w-7" />
                            </button>
                        </h3>
                        <div class="flex flex-row relative">
                            <input
                                data-test="note-search"
                                v-model="filter"
                                type="text"
                                class="w-[250px] bg-transparent rounded-xl text-white focus:outline-hidden"
                                placeholder="Buscar...">
                            <!-- Icon components only pass class through, so their test hooks are test-* classes -->
                            <XCircleIcon
                                v-show="filter.length !== 0"
                                @click="filter = ''"
                                class="test-clear-search w-6 ml-4 text-main2 cursor-pointer hover:opacity-80 absolute right-[80px] top-[9px]"
                                />
                            <PlusCircleIcon
                                v-show="!isCreating"
                                @click="newNote()"
                                class="test-new-note w-10 ml-4 text-main4 cursor-pointer hover:opacity-80"
                            />
                            <ArrowPathIcon
                                v-show="isCreating"
                                class="test-creating-note animate-spin w-10 ml-4 text-main4 "
                            />
                        </div>
                    </header>
                    <div data-test="no-notes" v-if="!notes || notes.length === 0" class="text-white p-4">No hay notas</div>
                    <note-list
                        v-if="notes && notes.length > 0"
                        @delete-note="deleteNote"
                        @change-note="changeNote"
                        :current-note-id="currentNote.id"
                        :notes="notes" :filter="filter" />
                </aside>
                <!-- With the list hidden on desktop, the title makes room for the button that brings it back -->
                <article data-test="editor-pane" class="
                                grow
                                relative
                                md:block
                                "
                        :class="[
                            isDesktopSidebarVisible ? 'md:max-w-[calc(100%-350px)]' : 'md:max-w-full md:[&_[data-test=note-title]]:pl-16',
                            {
                                'hidden': isSidebarVisible && isMobile,
                                'w-full': !isSidebarVisible && isMobile,
                            },
                        ]">
                    <button
                        data-test="show-notes-sidebar"
                        @click="toggleDesktopSidebar()"
                        v-show="!isDesktopSidebarVisible"
                        title="Ver listado"
                        class="hidden md:inline-block absolute top-[30px] left-5 z-10 text-main4 cursor-pointer hover:opacity-80"
                    >
                        <ChevronDoubleRightIcon class="test-show-sidebar w-7" />
                    </button>
                    <note
                        v-if="currentNote"
                        @update-note="updateNote"
                        :note="currentNote"
                    />
                </article>
            </section>
            <button
                data-test="show-note-list"
                @click="isSidebarVisible = !isSidebarVisible"
                v-show="isMobile && !isSidebarVisible"
                class="
                    text-gray-900
                    bg-main4
                    w-full
                    fixed
                    bottom-0
                    md:hidden
                    ">
                Ver listado
            </button>
        </div>
    </AuthenticatedLayout>
</template>
