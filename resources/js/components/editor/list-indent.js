import { Extension } from '@tiptap/core'
import { Selection, TextSelection } from '@tiptap/pm/state'
import { liftListItem, sinkListItem } from '@tiptap/pm/schema-list'

/**
 * Indents and outdents list items the way the old Quill editor did, on top of ProseMirror's
 * real list nesting:
 *
 * - A selection that spills past the last item (dragging below the list, Shift+End or Shift+Down
 *   on the last item) is pulled back into the list before nesting, instead of silently failing.
 * - An item with nothing above it to nest under (the first item of a list) still indents: it
 *   gets an `indent` attribute, rendered as `data-indent="N"` and styled as a left margin, like
 *   Quill's `ql-indent-N` classes. Shift+Tab undoes that before lifting the item out of its list.
 * - Tab and Shift+Tab are always consumed inside a list, so the focus never jumps out of the editor.
 */

const ITEM_TYPES = ['listItem', 'taskItem']
export const MAX_INDENT = 8

function itemTypesIn(schema) {
    return ITEM_TYPES.map((name) => schema.nodes[name]).filter(Boolean)
}

/**
 * The list items the selection covers, as a ProseMirror range on their parent list, with the
 * selection's end moved back to the last item when it sits in a block after the list.
 */
function selectedItems(state) {
    const { $from } = state.selection
    let { $to } = state.selection

    for (;;) {
        for (const itemType of itemTypesIn(state.schema)) {
            const range = $from.blockRange($to, (node) => node.childCount > 0 && node.firstChild.type === itemType)
            if (range) {
                return { itemType, range, $from, $to }
            }
        }

        if ($to.pos <= $from.pos || $to.parentOffset !== 0 || $to.depth === 0) {
            return null
        }

        const $previous = Selection.near(state.doc.resolve($to.before()), -1).$to
        if ($previous.pos >= $to.pos || $previous.pos < $from.pos) {
            return null
        }
        $to = $previous
    }
}

/**
 * Moves the transaction's selection onto the items found by `selectedItems`. Tiptap's chainable
 * state caches its selection until `state.tr` is read again, and ProseMirror's own list commands
 * read `state.selection`, so the getter is touched afterwards to refresh it.
 */
function focusSelection(state, tr, { $from, $to }) {
    if ($to.pos !== tr.selection.to) {
        tr.setSelection(TextSelection.create(tr.doc, $from.pos, $to.pos))
        void state.tr
    }
}

/** Changes the `indent` attribute of every item in the range by `delta`; false when nothing changes. */
function shiftIndent(tr, { range }, delta, dispatch) {
    let changed = false
    let pos = range.start

    for (let index = range.startIndex; index < range.endIndex; index++) {
        const item = range.parent.child(index)
        const indent = Math.min(MAX_INDENT, Math.max(0, (item.attrs.indent ?? 0) + delta))

        if (indent !== (item.attrs.indent ?? 0)) {
            changed = true
            if (dispatch) {
                tr.setNodeMarkup(pos, null, { ...item.attrs, indent })
            }
        }
        pos += item.nodeSize
    }

    return changed
}

export const ListIndent = Extension.create({
    name: 'listIndent',

    // Before the list item extensions, whose own Tab/Shift+Tab only cover the nesting case
    priority: 1000,

    addGlobalAttributes() {
        return [
            {
                types: ITEM_TYPES,
                attributes: {
                    indent: {
                        default: 0,
                        parseHTML: (element) => parseInt(element.getAttribute('data-indent') || '0', 10) || 0,
                        renderHTML: (attributes) => (attributes.indent > 0 ? { 'data-indent': attributes.indent } : {}),
                    },
                },
            },
        ]
    },

    addCommands() {
        return {
            indentListItem: () => ({ state, tr, dispatch }) => {
                const items = selectedItems(state)
                if (!items) {
                    return false
                }

                focusSelection(state, tr, items)

                return sinkListItem(items.itemType)(state, dispatch) || shiftIndent(tr, items, 1, dispatch)
            },

            outdentListItem: () => ({ state, tr, dispatch }) => {
                const items = selectedItems(state)
                if (!items) {
                    return false
                }

                focusSelection(state, tr, items)

                return shiftIndent(tr, items, -1, dispatch) || liftListItem(items.itemType)(state, dispatch)
            },
        }
    },

    addKeyboardShortcuts() {
        const inList = () => selectedItems(this.editor.state) !== null

        return {
            Tab: () => this.editor.commands.indentListItem() || inList(),
            'Shift-Tab': () => this.editor.commands.outdentListItem() || inList(),
        }
    },
})
