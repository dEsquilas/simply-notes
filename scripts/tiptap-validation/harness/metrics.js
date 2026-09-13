/**
 * Extracts comparable structural metrics from a note's HTML. Used unchanged on the raw stored
 * HTML and on both editors' output, so "before" and "after" are compared on equal terms even
 * though Quill's list markup is flat (<ol><li data-list>) and Tiptap's is a real tree
 * (<ul>/<ol>/<li>): both still end up as plain <li> elements, counted the same way here.
 */
window.extractMetrics = function extractMetrics(html) {
    const doc = new DOMParser().parseFromString(html ?? '', 'text/html')
    const body = doc.body

    // Block-level elements render on their own line: a boundary like "</p><p>" is at least a
    // word break even with no whitespace in the markup. Without this, comparing plain textContent
    // across editors falsely reports lost content whenever one of them trims an insignificant
    // trailing space before a block boundary that the other preserved (both look the same on screen).
    body.querySelectorAll('p, div, li, h1, h2, h3, h4, h5, h6, blockquote, tr, td, th, pre').forEach((el) => {
        el.insertAdjacentText('beforeend', ' ')
    })

    const text = (body.textContent ?? '').replace(/\s+/g, ' ').trim()

    const isTaskItem = (li) => li.matches('li[data-type="taskItem"]') || li.hasAttribute('data-checked')
        || ['checked', 'unchecked'].includes(li.getAttribute('data-list'))

    const allLis = Array.from(body.querySelectorAll('li'))
    const taskItems = allLis.filter(isTaskItem)
    const plainItems = allLis.filter((li) => !isTaskItem(li))

    const isChecked = (li) => li.getAttribute('data-checked') === 'true' || li.getAttribute('data-list') === 'checked'
        || !!li.querySelector(':scope input[type="checkbox"][checked]')

    // Raw Evernote to-dos are not <li> at all yet: <div><input type="checkbox">text</div>
    const rawCheckboxDivs = Array.from(body.querySelectorAll('div')).filter((div) => div.querySelector(':scope > input[type="checkbox"]'))

    const checkedCount = taskItems.filter(isChecked).length
        + rawCheckboxDivs.filter((div) => div.querySelector(':scope > input[type="checkbox"]').hasAttribute('checked')).length
    const uncheckedCount = (taskItems.length - taskItems.filter(isChecked).length)
        + rawCheckboxDivs.filter((div) => !div.querySelector(':scope > input[type="checkbox"]').hasAttribute('checked')).length

    const images = Array.from(body.querySelectorAll('img')).map((img) => img.getAttribute('src') ?? '')
    const iframes = Array.from(body.querySelectorAll('iframe')).map((iframe) => iframe.getAttribute('src') ?? '')
    const links = Array.from(body.querySelectorAll('a[href]')).map((a) => a.getAttribute('href') ?? '')
    const headings = Array.from(body.querySelectorAll('h1, h2, h3, h4, h5, h6')).map((h) => ({
        level: Number(h.tagName[1]),
        text: (h.textContent ?? '').trim(),
    }))

    const tables = Array.from(body.querySelectorAll('table')).map((table) => ({
        rows: table.querySelectorAll('tr').length,
        cells: table.querySelectorAll('td, th').length,
    }))

    // Tiptap renders a code block as <pre><code>; Quill 2's own (toolbar-less) code block format
    // renders as a <div class="ql-code-block-container"> of per-line divs. Both count here so a
    // note that already had one in storage compares as a code block on all three views.
    const codeBlocks = body.querySelectorAll('pre, .ql-code-block-container').length

    return {
        text,
        textLength: text.length,
        images,
        iframes,
        links,
        headings,
        tables,
        codeBlocks,
        lists: {
            bulletOrOrderedItems: plainItems.length,
            taskItems: taskItems.length,
            checked: checkedCount,
            unchecked: uncheckedCount,
        },
    }
}
