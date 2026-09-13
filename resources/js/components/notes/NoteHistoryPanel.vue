<script setup>
import { ref, watch } from 'vue'
import { XMarkIcon, BookmarkSquareIcon } from '@heroicons/vue/24/outline'
import { notify } from '@kyvg/vue3-notification'

const props = defineProps({
    note: {
        type: Object,
        required: true,
    },
    // Flushes any pending autosave and waits for it: called before a restore, so the snapshot of
    // the state being replaced (and the restore itself) always sees the latest edits.
    flushPendingSave: {
        type: Function,
        required: true,
    },
})

const emit = defineEmits(['close', 'restored'])

const REASON_LABELS = {
    session_start: 'Inicio de edición',
    session_end: 'Fin de edición',
    substantial_change: 'Cambio importante',
    manual: 'Guardado manual',
    restore: 'Restauración',
}

const reasonLabel = (reason) => REASON_LABELS[reason] ?? reason

const formatDate = (date) => new Intl.DateTimeFormat('es-ES', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(date))

const versions = ref([])
const loading = ref(true)
const selected = ref(null)
const preview = ref(null)
const previewLoading = ref(false)
const label = ref('')
const saving = ref(false)
const restoring = ref(false)

const loadVersions = () => {
    loading.value = true

    axios
        .get(`/notes/${props.note.id}/versions`)
        .then((response) => {
            versions.value = response.data.versions
        })
        .catch(() => {
            notify({ type: 'error', text: 'The version history could not be loaded' })
        })
        .finally(() => {
            loading.value = false
        })
}

loadVersions()

const selectVersion = (version) => {
    selected.value = version
    preview.value = null
    previewLoading.value = true

    axios
        .get(`/notes/${props.note.id}/versions/${version.id}`)
        .then((response) => {
            preview.value = response.data.version
        })
        .catch(() => {
            notify({ type: 'error', text: 'The version could not be previewed' })
        })
        .finally(() => {
            previewLoading.value = false
        })
}

/**
 * Puts a version in the list, replacing an existing row with the same id or adding a new one
 * at the top. The server pins (and relabels) the latest version instead of creating a duplicate
 * when nothing changed, so this keeps that same row's badge/label in sync rather than the panel
 * pretending nothing happened.
 */
const upsertVersion = (version) => {
    const index = versions.value.findIndex((v) => v.id === version.id)

    if (index === -1) {
        versions.value.unshift(version)
    } else {
        versions.value[index] = version
    }
}

const saveVersion = () => {
    saving.value = true

    axios
        .post(`/notes/${props.note.id}/versions`, { reason: 'manual', label: label.value || null })
        .then((response) => {
            label.value = ''
            if (response.data.version) {
                upsertVersion(response.data.version)
                notify({ type: 'success', text: 'Versión guardada' })
            }
        })
        .catch(() => {
            notify({ type: 'error', text: 'The version could not be saved' })
        })
        .finally(() => {
            saving.value = false
        })
}

const restoreVersion = async () => {
    if (!selected.value) {
        return
    }

    if (!window.confirm('¿Restaurar esta versión? El contenido actual se guardará en el historial.')) {
        return
    }

    restoring.value = true

    // Any edit still pending in the editor must be saved first: the pre-restore snapshot (and the
    // restore itself) must see it, not a stale server-side state
    await props.flushPendingSave()

    axios
        .post(`/notes/${props.note.id}/versions/${selected.value.id}/restore`)
        .then((response) => {
            notify({ type: 'success', text: 'Versión restaurada' })
            emit('restored', { note: response.data.note })
            emit('close')
        })
        .catch(() => {
            notify({ type: 'error', text: 'The version could not be restored' })
        })
        .finally(() => {
            restoring.value = false
        })
}

// Reloads the history when the user switches notes while the panel stays open
watch(() => props.note.id, () => {
    selected.value = null
    preview.value = null
    loadVersions()
})
</script>
<template>
    <div data-test="note-history-panel" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4">
        <div class="bg-cblack border border-cgray rounded-xl w-full max-w-4xl max-h-[85vh] flex flex-col text-white overflow-hidden">
            <header class="flex items-center justify-between p-4 border-b border-cgray shrink-0">
                <h3 class="text-xl font-bold">Historial de versiones</h3>
                <button data-test="note-history-close" @click="emit('close')" class="cursor-pointer hover:opacity-80">
                    <XMarkIcon class="w-6" />
                </button>
            </header>

            <div class="flex items-center gap-2 p-4 border-b border-cgray shrink-0">
                <input
                    data-test="note-history-label"
                    v-model="label"
                    type="text"
                    placeholder="Etiqueta (opcional)"
                    class="grow bg-transparent border border-cgray rounded-lg px-3 py-2 text-white focus:outline-hidden"
                >
                <button
                    data-test="note-history-save"
                    @click="saveVersion"
                    :disabled="saving"
                    class="flex items-center gap-2 bg-main4 text-gray-900 rounded-lg px-4 py-2 font-medium hover:opacity-80 disabled:opacity-50"
                >
                    <BookmarkSquareIcon class="w-5" />
                    Guardar versión
                </button>
            </div>

            <div class="grow flex overflow-hidden">
                <ul data-test="note-history-list" class="w-[280px] border-r border-cgray overflow-y-auto shrink-0">
                    <li data-test="note-history-empty" v-if="!loading && versions.length === 0" class="p-4 text-clgray">
                        Sin versiones guardadas
                    </li>
                    <li
                        v-for="version in versions"
                        :key="version.id"
                        :data-test="'note-history-item-' + version.id"
                        @click="selectVersion(version)"
                        class="p-4 border-b border-cgray cursor-pointer hover:bg-cgray transition-colors"
                        :class="{ 'bg-cgray': selected?.id === version.id }"
                    >
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium">{{ reasonLabel(version.reason) }}</span>
                            <span v-if="version.pinned" data-test="note-history-pinned" class="text-xs bg-main4 text-gray-900 rounded px-1.5 py-0.5">Fijada</span>
                        </div>
                        <div class="text-xs text-clgray mt-1">{{ formatDate(version.created_at) }}</div>
                        <div v-if="version.label" class="text-xs text-main4 mt-1 italic">{{ version.label }}</div>
                    </li>
                </ul>

                <div class="grow p-4 overflow-y-auto">
                    <div v-if="!selected" class="text-clgray">Selecciona una versión para previsualizarla</div>
                    <div v-else>
                        <div class="flex items-center justify-between mb-4">
                            <h4 class="text-lg font-bold">{{ preview?.title || 'Nueva nota' }}</h4>
                            <button
                                data-test="note-history-restore"
                                @click="restoreVersion"
                                :disabled="restoring || previewLoading"
                                class="bg-main2 text-white rounded-lg px-4 py-2 hover:opacity-80 disabled:opacity-50"
                            >
                                Restaurar
                            </button>
                        </div>
                        <!-- The server re-sanitizes this content on every request: safe to render, even from legacy notes -->
                        <div
                            data-test="note-history-preview"
                            v-if="preview"
                            v-html="preview.content"
                            class="ql-editor text-white pointer-events-none !h-auto !overflow-visible"
                        ></div>
                        <div v-else class="text-clgray">Cargando...</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
