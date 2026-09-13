<script setup>
import { defineModel, defineEmits, ref, onBeforeUnmount, watch } from 'vue'
import { useEditor, EditorContent } from '@tiptap/vue-3'
import { buildExtensions } from './extensions.js'
import { preprocessLegacyHtml } from './legacy-content.js'
import { fileToBase64 } from './image-paste-drop.js'
import { icons } from './icons.js'
import { TrashIcon } from '@heroicons/vue/24/outline'
import './editor.scss'

const model = defineModel()
const emit = defineEmits([
    'updated-content',
])

const linkUrl = ref('')
const videoUrl = ref('')
const showLinkPopover = ref(false)
const showVideoPopover = ref(false)

const editor = useEditor({
    extensions: buildExtensions(),
    editorProps: {
        attributes: { class: 'tiptap-content', tabindex: '2' },
    },
    onUpdate: () => {
        model.value = editor.value.getHTML()
        emit('updated-content')
    },
})

/**
 * Loads stored HTML into the editor without emitting an update: opening a note never saves it.
 * Legacy Quill list/checklist markup is rebuilt into Tiptap's own list shapes first; everything
 * else Tiptap already parses the way Quill's clipboard did, by descending into unknown elements.
 */
const loadHtml = (html) => {
    editor.value.commands.setContent(preprocessLegacyHtml(html ?? ''), { emitUpdate: false })
    model.value = editor.value.getHTML()
}

watch(editor, (value) => {
    if (value) {
        loadHtml(model.value)
    }
}, { immediate: true })

watch(() => model.value, (value) => {
    if (editor.value && value !== editor.value.getHTML()) {
        loadHtml(value)
    }
})

onBeforeUnmount(() => {
    editor.value?.destroy()
})

const imageInput = ref(null)

const triggerImageUpload = () => {
    imageInput.value?.click()
}

const onImageChosen = async (event) => {
    const file = event.target.files[0]
    event.target.value = ''

    if (file) {
        const src = await fileToBase64(file)
        editor.value.chain().focus().setImage({ src }).run()
    }
}

const toggleLinkPopover = () => {
    showVideoPopover.value = false
    linkUrl.value = editor.value.getAttributes('link').href ?? ''
    showLinkPopover.value = !showLinkPopover.value
}

const applyLink = () => {
    if (linkUrl.value) {
        editor.value.chain().focus().extendMarkRange('link').setLink({ href: linkUrl.value }).run()
    } else {
        editor.value.chain().focus().extendMarkRange('link').unsetLink().run()
    }
    showLinkPopover.value = false
}

const toggleVideoPopover = () => {
    showLinkPopover.value = false
    videoUrl.value = ''
    showVideoPopover.value = !showVideoPopover.value
}

const applyVideo = () => {
    editor.value.chain().focus().setVideo(videoUrl.value).run()
    showVideoPopover.value = false
}

const setHeading = (event) => {
    const level = Number(event.target.value)

    if (level === 0) {
        editor.value.chain().focus().setParagraph().run()
    } else {
        editor.value.chain().focus().setHeading({ level }).run()
    }
}

const currentHeading = () => {
    for (const level of [1, 2, 3, 4, 5, 6]) {
        if (editor.value?.isActive('heading', { level })) {
            return level
        }
    }

    return 0
}

const insertTable = () => {
    editor.value.chain().focus().insertTable({ rows: 3, cols: 3, withHeaderRow: false }).run()
}

/** A single chained command fails as a whole if any step is inapplicable, so the list type must be picked first. */
const outdent = () => {
    editor.value.chain().focus().liftListItem(editor.value.isActive('taskItem') ? 'taskItem' : 'listItem').run()
}

const indent = () => {
    editor.value.chain().focus().sinkListItem(editor.value.isActive('taskItem') ? 'taskItem' : 'listItem').run()
}

const clearFormatting = () => {
    editor.value.chain().focus().unsetAllMarks().clearNodes().run()
}
</script>

<template>
    <div v-if="editor" data-test="note-body" class="tiptap-editor-container">
        <div class="tiptap-toolbar" data-test="toolbar">
            <span class="tiptap-formats">
                <select data-test="toolbar-heading" :value="currentHeading()" @change="setHeading">
                    <option value="0">Normal</option>
                    <option value="1">Heading 1</option>
                    <option value="2">Heading 2</option>
                    <option value="3">Heading 3</option>
                </select>
            </span>

            <span class="tiptap-formats">
                <button type="button" data-test="toolbar-bold" :class="{active: editor.isActive('bold')}" @click="editor.chain().focus().toggleBold().run()" title="Bold" v-html="icons.bold"></button>
                <button type="button" data-test="toolbar-italic" :class="{active: editor.isActive('italic')}" @click="editor.chain().focus().toggleItalic().run()" title="Italic" v-html="icons.italic"></button>
                <button type="button" data-test="toolbar-underline" :class="{active: editor.isActive('underline')}" @click="editor.chain().focus().toggleUnderline().run()" title="Underline" v-html="icons.underline"></button>
                <button type="button" data-test="toolbar-strike" :class="{active: editor.isActive('strike')}" @click="editor.chain().focus().toggleStrike().run()" title="Strike" v-html="icons.strike"></button>
                <button type="button" data-test="toolbar-blockquote" :class="{active: editor.isActive('blockquote')}" @click="editor.chain().focus().toggleBlockquote().run()" title="Quote" v-html="icons.blockquote"></button>
                <button type="button" data-test="toolbar-code-block" :class="{active: editor.isActive('codeBlock')}" @click="editor.chain().focus().toggleCodeBlock().run()" title="Code block" v-html="icons.codeBlock"></button>
            </span>

            <span class="tiptap-formats">
                <button type="button" data-test="toolbar-ordered-list" :class="{active: editor.isActive('orderedList')}" @click="editor.chain().focus().toggleOrderedList().run()" title="Numbered list" v-html="icons.orderedList"></button>
                <button type="button" data-test="toolbar-bullet-list" :class="{active: editor.isActive('bulletList')}" @click="editor.chain().focus().toggleBulletList().run()" title="Bullet list" v-html="icons.bulletList"></button>
                <button type="button" data-test="toolbar-task-list" :class="{active: editor.isActive('taskList')}" @click="editor.chain().focus().toggleTaskList().run()" title="Checklist" v-html="icons.taskList"></button>
                <button type="button" data-test="toolbar-outdent" @click="outdent" title="Outdent" v-html="icons.outdent"></button>
                <button type="button" data-test="toolbar-indent" @click="indent" title="Indent" v-html="icons.indent"></button>
            </span>

            <span class="tiptap-formats">
                <button type="button" data-test="toolbar-link" :class="{active: editor.isActive('link')}" @click="toggleLinkPopover" title="Link" v-html="icons.link"></button>
                <button type="button" data-test="toolbar-image" @click="triggerImageUpload" title="Image" v-html="icons.image"></button>
                <button type="button" data-test="toolbar-video" @click="toggleVideoPopover" title="Video" v-html="icons.video"></button>
                <button type="button" data-test="toolbar-table" @click="insertTable" title="Table" v-html="icons.table"></button>
                <button type="button" data-test="toolbar-clean" @click="clearFormatting" title="Clear formatting" v-html="icons.clean"></button>
            </span>

            <input ref="imageInput" type="file" accept="image/*" class="tiptap-hidden-input" @change="onImageChosen">

            <div v-if="showLinkPopover" data-test="link-popover" class="tiptap-popover">
                <input data-test="link-input" v-model="linkUrl" type="text" placeholder="https://…" @keydown.enter.prevent="applyLink" @keydown.esc="showLinkPopover = false">
                <button type="button" data-test="link-apply" @click="applyLink">OK</button>
            </div>

            <div v-if="showVideoPopover" data-test="video-popover" class="tiptap-popover">
                <input data-test="video-input" v-model="videoUrl" type="text" placeholder="https://…" @keydown.enter.prevent="applyVideo" @keydown.esc="showVideoPopover = false">
                <button type="button" data-test="video-apply" @click="applyVideo">OK</button>
            </div>

            <!-- Table actions live in the toolbar row itself, so they never cover the note -->
            <span v-if="editor.isActive('table')" data-test="table-menu" class="tiptap-formats">
                <button type="button" data-test="table-add-row" title="Add row" @click="editor.chain().focus().addRowAfter().run()" v-html="icons.addRow"></button>
                <button type="button" data-test="table-delete-row" title="Delete row" @click="editor.chain().focus().deleteRow().run()" v-html="icons.deleteRow"></button>
                <button type="button" data-test="table-add-column" title="Add column" @click="editor.chain().focus().addColumnAfter().run()" v-html="icons.addColumn"></button>
                <button type="button" data-test="table-delete-column" title="Delete column" @click="editor.chain().focus().deleteColumn().run()" v-html="icons.deleteColumn"></button>
                <button type="button" data-test="table-delete" title="Delete table" @click="editor.chain().focus().deleteTable().run()">
                    <TrashIcon class="w-[18px] h-[18px]" />
                </button>
            </span>
        </div>

        <EditorContent :editor="editor" class="tiptap-editor" />
    </div>
</template>
