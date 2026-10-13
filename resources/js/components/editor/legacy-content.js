/**
 * Converts markup produced by the old Quill-based editor (and raw Evernote imports) into the
 * markup Tiptap's own extensions already know how to parse, before it reaches `setContent`.
 *
 * Quill's list model is flat: every list, whatever its visual nesting, is a single <ol> whose
 * <li> elements carry a `data-list` attribute ("bullet" | "ordered" | "checked" | "unchecked")
 * and a `ql-indent-N` class for visual indentation. Tiptap/ProseMirror's list model is a real
 * tree of <ul>/<ol> elements. `rebuildQuillList` replays the flat list as a tree using a
 * depth stack, matching Quill's own indent/outdent behaviour. Indentation that has no item
 * above it to nest under (a list whose first item is already indented, or an item two levels
 * deeper than the previous one) is kept as the editor's own `data-indent` margin (list-indent.js)
 * instead of being flattened away.
 *
 * Evernote exports to-dos as `<div><input type="checkbox">text</div>`; `convertCheckboxDivs`
 * turns runs of these into a Tiptap task list, the same way the old editor's clipboard matcher
 * turned them into a Quill checklist on load.
 *
 * This module only touches the shapes above. Everything else (paragraphs, headings, images,
 * tables, links, iframes, stray divs and Evernote/SVG wrapper elements) is left untouched:
 * Tiptap's own DOMParser-based parsing already renders it the same way Quill's clipboard did,
 * by parsing into the children of an element it does not recognise instead of dropping them.
 */

function stripQlUiMarkers(el) {
    el.querySelectorAll(':scope > span.ql-ui').forEach((marker) => marker.remove());
}

/** Wraps loose inline/text children of `el` in a single <p>, unless it already holds block content. */
function ensureBlockContent(doc, el) {
    const hasBlockChild = Array.from(el.children).some((child) => !['SPAN', 'A', 'STRONG', 'EM', 'U', 'S', 'BR', 'CODE'].includes(child.tagName));

    if (hasBlockChild || el.childNodes.length === 0) {
        return;
    }

    const p = doc.createElement('p');
    while (el.firstChild) {
        p.appendChild(el.firstChild);
    }
    el.appendChild(p);
}

function indentDepth(li) {
    const match = (li.className || '').match(/ql-indent-(\d+)/);

    return match ? parseInt(match[1], 10) : 0;
}

function listKind(dataList) {
    return dataList === 'checked' || dataList === 'unchecked' ? 'task' : (dataList || 'bullet');
}

function makeListContainer(doc, kind) {
    if (kind === 'ordered') {
        return doc.createElement('ol');
    }

    const ul = doc.createElement('ul');
    if (kind === 'task') {
        ul.setAttribute('data-type', 'taskList');
    }

    return ul;
}

function makeListItem(doc, li, kind) {
    stripQlUiMarkers(li);

    if (kind !== 'task') {
        const newLi = doc.createElement('li');
        while (li.firstChild) {
            newLi.appendChild(li.firstChild);
        }
        ensureBlockContent(doc, newLi);

        return newLi;
    }

    const newLi = doc.createElement('li');
    newLi.setAttribute('data-type', 'taskItem');
    newLi.setAttribute('data-checked', li.getAttribute('data-list') === 'checked' ? 'true' : 'false');

    const content = doc.createElement('div');
    while (li.firstChild) {
        content.appendChild(li.firstChild);
    }
    ensureBlockContent(doc, content);
    newLi.appendChild(content);

    return newLi;
}

/** Rebuilds one Quill flat `<ol>` as a real nested list tree, replacing it in place. */
function rebuildQuillList(doc, ol) {
    const items = Array.from(ol.children).filter((el) => el.tagName === 'LI');

    if (items.length === 0 || !items.some((li) => li.hasAttribute('data-list'))) {
        return;
    }

    const root = doc.createDocumentFragment();
    // Each entry is one real list: `base` is the Quill depth its un-indented items sit at and
    // `lastDepth` the depth of the item last added to it
    const stack = [];

    const addItem = (listEntry, newLi, depth) => {
        if (depth > listEntry.base) {
            newLi.setAttribute('data-indent', String(depth - listEntry.base));
        }
        listEntry.listEl.appendChild(newLi);
        listEntry.lastLi = newLi;
        listEntry.lastDepth = depth;
    };

    items.forEach((li) => {
        const kind = listKind(li.getAttribute('data-list'));
        const depth = indentDepth(li);
        const newLi = makeListItem(doc, li, kind);

        while (stack.length && (stack[stack.length - 1].base > depth
            || (stack[stack.length - 1].lastDepth >= depth && stack[stack.length - 1].kind !== kind))) {
            stack.pop();
        }

        if (stack.length === 0) {
            const entry = { base: 0, kind, listEl: makeListContainer(doc, kind) };
            root.appendChild(entry.listEl);
            stack.push(entry);
            addItem(entry, newLi, depth);

            return;
        }

        const top = stack[stack.length - 1];

        if (depth <= top.lastDepth) {
            addItem(top, newLi, depth);

            return;
        }

        // depth > top.lastDepth: nest one level inside the last item added to the current list
        const entry = { base: top.lastDepth + 1, kind, listEl: makeListContainer(doc, kind) };
        top.lastLi.appendChild(entry.listEl);
        stack.push(entry);
        addItem(entry, newLi, depth);
    });

    ol.replaceWith(root);
}

/** Groups of sibling `<div><input type="checkbox">…</div>` become one Tiptap task list. */
function convertCheckboxDivs(doc, root) {
    const candidates = Array.from(root.querySelectorAll('div')).filter((div) => div.querySelector(':scope > input[type="checkbox"]'));

    const groups = [];
    candidates.forEach((div) => {
        const last = groups[groups.length - 1];
        const previous = candidates[candidates.indexOf(div) - 1];

        if (last && previous && div.parentElement === previous.parentElement && div.previousElementSibling === previous) {
            last.push(div);
        } else {
            groups.push([div]);
        }
    });

    groups.forEach((group) => {
        const ul = doc.createElement('ul');
        ul.setAttribute('data-type', 'taskList');

        group.forEach((div) => {
            const checkbox = div.querySelector(':scope > input[type="checkbox"]');
            const checked = checkbox.hasAttribute('checked');
            checkbox.remove();

            const li = doc.createElement('li');
            li.setAttribute('data-type', 'taskItem');
            li.setAttribute('data-checked', checked ? 'true' : 'false');

            const content = doc.createElement('div');
            while (div.firstChild) {
                content.appendChild(div.firstChild);
            }
            ensureBlockContent(doc, content);
            li.appendChild(content);
            ul.appendChild(li);
        });

        group[0].replaceWith(ul);
        group.slice(1).forEach((div) => div.remove());
    });
}

/** Quill 2's multi-line code block container becomes a plain <pre><code>. */
function convertQuillCodeBlocks(doc, root) {
    root.querySelectorAll('.ql-code-block-container').forEach((container) => {
        const lines = container.querySelectorAll('.ql-code-block');
        const text = lines.length
            ? Array.from(lines).map((line) => line.textContent).join('\n')
            : container.textContent;

        const pre = doc.createElement('pre');
        const code = doc.createElement('code');
        code.textContent = text;
        pre.appendChild(code);
        container.replaceWith(pre);
    });
}

export function preprocessLegacyHtml(html) {
    if (!html) {
        return html ?? '';
    }

    const doc = new DOMParser().parseFromString(html, 'text/html');
    const { body } = doc;

    convertQuillCodeBlocks(doc, body);
    convertCheckboxDivs(doc, body);
    Array.from(body.querySelectorAll('ol')).forEach((ol) => rebuildQuillList(doc, ol));

    return body.innerHTML;
}
