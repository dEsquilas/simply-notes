<script setup>
import { ref, watch, onMounted, onBeforeUnmount } from 'vue'
import { notify } from "@kyvg/vue3-notification"
import { ClockIcon } from '@heroicons/vue/24/outline'
import QuillEditor from '@/components/quill/QuillEditor.vue'
import NoteHistoryPanel from '@/components/notes/NoteHistoryPanel.vue'

const emit = defineEmits(['update-note'])

const props = defineProps({
    note: {
        type: Object,
        required: true,
    },
})


const noteTitle = ref(props.note?.title)
const noteContent = ref(props.note?.content)
const showHistory = ref(false)

if (noteContent.value == null || noteContent.value.length === 0) {
    noteContent.value = ""
}


let autosaveInterval = null
let lastModified = -1
const autosaveTime = 1500

// Matches config('versions.inactivity_minutes'): a session_end version is snapshotted after this
// long without edits in the open note.
const sessionEndAfter = 5 * 60 * 1000
let sessionEndTimer = null

const scheduleSessionEnd = () => {
    clearTimeout(sessionEndTimer)
    sessionEndTimer = setTimeout(() => requestVersionSnapshot(props.note), sessionEndAfter)
}

watch(() => props.note, (newNote, oldNote) => {
    // Edits waiting for autosave belong to the note being left: save them before loading the new one
    if (oldNote && newNote?.id !== oldNote.id && lastModified !== -1) {
        save(oldNote, noteTitle.value, noteContent.value)
    }

    // A session of editing the previous note just ended
    if (oldNote && newNote?.id !== oldNote.id) {
        requestVersionSnapshot(oldNote)
    }

    noteTitle.value = newNote.title
    noteContent.value = newNote.content
    if(newNote.content == null || newNote.content.length === 0){
        newNote.content = ""
    }

    scheduleSessionEnd()
},{ deep: true })

const dispatchAutosave = () => {

    lastModified = Date.now()
    if(!autosaveInterval)
        autosaveInterval = setInterval(autosave, autosaveTime)

    scheduleSessionEnd()

}

let autosave = () => {

    if (lastModified !== -1 && Date.now() - lastModified > autosaveTime)
        save()
}

const save = (note = props.note, title = noteTitle.value, content = noteContent.value) => {

    clearInterval(autosaveInterval)
    autosaveInterval = null
    lastModified = -1

    // Returned so callers that must wait for the save to land (e.g. before opening the history
    // panel or restoring a version) can await it; never rejects, errors are handled below
    return axios
        .post('/notes/update/' + note.id, {
            title: title,
            content: content,
        })
        .then((response) => {
            notify({
                type: 'neutral',
                text: 'Updated',
            })

            emit('update-note', {note: response.data.note})
        })
        .catch((error) => {
            const expired = [401, 419].includes(error.response?.status)
            notify({
                type: 'error',
                text: expired
                    ? 'Your session has expired: log in again to keep your changes'
                    : 'The note could not be saved',
            })
        })

}

/**
 * Flushes a pending autosave and waits for it, so callers see the note's truly latest state.
 * Used before opening the history panel and before restoring a version.
 */
const flushPendingSave = () => {
    return lastModified !== -1 ? save() : Promise.resolve()
}

let forceSave = (event) => {
    if(event.key === "s" && (event.ctrlKey || event.metaKey)){
        event.preventDefault()
        save()
    }
}

const onTitleKeydown = (event) => {
    forceSave(event)
    if (!event.defaultPrevented)
        dispatchAutosave()
}

/**
 * A session_end snapshot is best-effort housekeeping: never surface a failure to the user.
 */
const requestVersionSnapshot = (note, reason = 'session_end') => {
    axios.post(`/notes/${note.id}/versions`, { reason }).catch(() => {})
}

/**
 * Reads the raw (still-encrypted) XSRF-TOKEN cookie value, the same one axios attaches
 * automatically as the X-XSRF-TOKEN header on every normal request.
 */
const readXsrfToken = () => {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]*)/)
    return match ? decodeURIComponent(match[1]) : ''
}

/**
 * Posts JSON with fetch's keepalive flag, which lets the browser finish the request even after
 * the page has been hidden or is being unloaded (unlike a plain axios/XHR call, which is cancelled).
 * Returns the parsed JSON body, or null on any failure.
 */
const beaconSafePost = (url, body) => {
    return fetch(url, {
        method: 'POST',
        keepalive: true,
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': readXsrfToken(),
        },
        body: JSON.stringify(body),
    })
        .then((response) => response.json())
        .catch(() => null)
}

// Chrome, Firefox and Safari all cap a keepalive fetch's request body around 64 KB; leave some
// margin, since notes carry base64-encoded images and can far exceed that
const KEEPALIVE_BODY_LIMIT = 60_000

/**
 * Runs when the note editor is about to disappear from the screen (tab hidden or page unloading):
 * flushes any pending edit first, then snapshots the now-saved state as a session_end version.
 */
// visibilitychange (hidden) and pagehide both fire when the page is left: flush only once per hide
let leaveFlushed = false

const flushOnLeave = () => {
    if (leaveFlushed) {
        return
    }
    leaveFlushed = true

    const note = props.note
    const snapshot = () => beaconSafePost(`/notes/${note.id}/versions`, { reason: 'session_end' })

    if (lastModified === -1) {
        snapshot()
        return
    }

    clearInterval(autosaveInterval)
    autosaveInterval = null
    lastModified = -1

    const body = { title: noteTitle.value, content: noteContent.value }
    const tooLargeForKeepalive = new Blob([JSON.stringify(body)]).size > KEEPALIVE_BODY_LIMIT

    // A large note (embedded images) would silently fail as a keepalive request: fall back to a
    // normal request instead, best effort - the tab may still close before it completes
    const flush = tooLargeForKeepalive
        ? axios.post('/notes/update/' + note.id, body).then((response) => response.data).catch(() => null)
        : beaconSafePost('/notes/update/' + note.id, body)

    flush
        .then((data) => {
            if (data?.note) {
                emit('update-note', { note: data.note })
            }
        })
        .finally(snapshot)
}

const onVisibilityChange = () => {
    if (document.visibilityState === 'hidden')
        flushOnLeave()
    else
        leaveFlushed = false
}

onMounted(() => {
    scheduleSessionEnd()
    document.addEventListener('visibilitychange', onVisibilityChange)
    window.addEventListener('pagehide', flushOnLeave)
})

onBeforeUnmount(() => {
    clearTimeout(sessionEndTimer)
    document.removeEventListener('visibilitychange', onVisibilityChange)
    window.removeEventListener('pagehide', flushOnLeave)
})

/**
 * Any pending edit must be saved before the history panel opens: otherwise a version snapshotted
 * or previewed in the panel (or the pre-restore snapshot) could miss it.
 */
const openHistory = () => {
    flushPendingSave().then(() => {
        showHistory.value = true
    })
}

const onRestored = ({ note }) => {
    // A late autosave (scheduled before the restore's flush completed) must never overwrite the
    // restored content with the pre-restore text
    clearInterval(autosaveInterval)
    autosaveInterval = null
    lastModified = -1

    noteTitle.value = note.title
    noteContent.value = note.content ?? ""
    showHistory.value = false
    emit('update-note', { note })
}

</script>
<template>
    <!-- Fills the viewport below the 65px top bar, so the editor takes whatever height the title leaves -->
    <div class="flex flex-col h-[calc(100vh-65px)]">
        <div class="shrink-0 flex items-center gap-4 pr-8">
            <input data-test="note-title" tabindex="1"
                   @keydown="onTitleKeydown"
                   v-model="noteTitle"
                   placeholder="Nueva nota"
                   type="text"
                   class="text-ellipsis flex-1 min-w-0 bg-transparent border-none focus:outline-hidden focus:border-none focus:ring-0 text-4xl text-white px-8 py-6
                   text-2xl
                   md:text-4xl
                   "
            >
            <button
                data-test="note-history-button"
                @click="openHistory"
                title="Historial de versiones"
                class="shrink-0 cursor-pointer hover:opacity-80"
            >
                <ClockIcon class="w-7 text-main4" />
            </button>
        </div>
        <QuillEditor @updated-content="dispatchAutosave"
                     @keydown.ctrl="forceSave"
                    v-model="noteContent"
        />
        <NoteHistoryPanel
            v-if="showHistory"
            :note="note"
            :flush-pending-save="flushPendingSave"
            @close="showHistory = false"
            @restored="onRestored"
        />
    </div>
</template>
