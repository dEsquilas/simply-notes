// Shared comparison/report-building logic used by compare.mjs (after=Tiptap vs before=main's
// real Quill editor) and compare-quill-new.mjs (after=candidate quill-new vs before=main's real
// Quill editor). Both sides are always rendered HTML strings; "raw" is the stored ground truth
// both editors are trying to reproduce.

import { createHash } from 'node:crypto'

export function sha1(value) {
    return createHash('sha1').update(value ?? '').digest('hex')
}

/** A short, readable diff: the surrounding context of the first character where two strings diverge. */
export function firstDivergence(a, b) {
    if (a === b) {
        return null
    }

    let i = 0
    const max = Math.min(a.length, b.length)
    while (i < max && a[i] === b[i]) {
        i++
    }

    const context = (s, at) => s.slice(Math.max(0, at - 30), at + 30)

    return {
        at: i,
        before: context(a, i),
        after: context(b, i),
    }
}

export function setDiff(a, b) {
    const setA = new Set(a)
    const setB = new Set(b)

    return {
        onlyInA: a.filter((x) => !setB.has(x)),
        onlyInB: b.filter((x) => !setA.has(x)),
    }
}

export function linksDiffer(a, b) {
    return JSON.stringify([...a].sort()) !== JSON.stringify([...b].sort())
}

export function sumBy(list, key) {
    return list.reduce((sum, item) => sum + item[key], 0)
}

/** Compares one count-like metric across raw/before/after and says whether "after" lost or gained content relative to "before", using "raw" as the ground truth both are trying to reproduce. */
export function compareDimension(name, rawValue, beforeValue, afterValue) {
    const beforeDelta = Math.abs(beforeValue - rawValue)
    const afterDelta = Math.abs(afterValue - rawValue)

    let verdict = 'same'
    if (beforeValue !== afterValue) {
        verdict = afterDelta > beforeDelta ? 'after_worse' : (afterDelta < beforeDelta ? 'after_better' : 'different_same_distance')
    }

    return {
        name, raw: rawValue, before: beforeValue, after: afterValue, verdict,
    }
}

export function buildRecord(note, raw, before, after, mRaw, mBefore, mAfter) {
    const textDiff = mBefore.text === mAfter.text ? null : firstDivergence(mBefore.text, mAfter.text)

    const imageHashesBefore = mBefore.images.map(sha1)
    const imageHashesAfter = mAfter.images.map(sha1)
    const imageDiffBeforeAfter = setDiff(imageHashesBefore, imageHashesAfter)

    const dimensions = [
        compareDimension('textLength', mRaw.textLength, mBefore.textLength, mAfter.textLength),
        compareDimension('images', mRaw.images.length, mBefore.images.length, mAfter.images.length),
        compareDimension('iframes', mRaw.iframes.length, mBefore.iframes.length, mAfter.iframes.length),
        compareDimension('links', mRaw.links.length, mBefore.links.length, mAfter.links.length),
        compareDimension('headings', mRaw.headings.length, mBefore.headings.length, mAfter.headings.length),
        compareDimension('tables', mRaw.tables.length, mBefore.tables.length, mAfter.tables.length),
        compareDimension('tableCells', sumBy(mRaw.tables, 'cells'), sumBy(mBefore.tables, 'cells'), sumBy(mAfter.tables, 'cells')),
        compareDimension('codeBlocks', mRaw.codeBlocks, mBefore.codeBlocks, mAfter.codeBlocks),
        compareDimension('listItems', mRaw.lists.bulletOrOrderedItems, mBefore.lists.bulletOrOrderedItems, mAfter.lists.bulletOrOrderedItems),
        compareDimension('taskItems', mRaw.lists.taskItems, mBefore.lists.taskItems, mAfter.lists.taskItems),
        compareDimension('checkedItems', mRaw.lists.checked, mBefore.lists.checked, mAfter.lists.checked),
        compareDimension('uncheckedItems', mRaw.lists.unchecked, mBefore.lists.unchecked, mAfter.lists.unchecked),
    ]

    const textVerdict = mBefore.text === mAfter.text
        ? 'same'
        : (mAfter.text === mRaw.text ? 'after_better' : (mBefore.text === mRaw.text ? 'after_worse' : 'different_both_imperfect'))

    const allDimensions = [{ name: 'text', raw: null, before: null, after: null, verdict: textVerdict }, ...dimensions]
    const worse = allDimensions.filter((d) => d.verdict === 'after_worse')
    const better = allDimensions.filter((d) => d.verdict === 'after_better')
    const different = allDimensions.filter((d) => d.verdict !== 'same')

    let classification = 'identical'
    if (different.length === 0) {
        classification = 'identical'
    } else if (worse.length > 0) {
        classification = 'after_loses_vs_before'
    } else if (better.length > 0) {
        classification = 'after_better_than_before'
    } else {
        classification = 'other_difference'
    }

    return {
        id: note.id,
        title: note.title,
        sizes: { raw: raw.length, before: before.length, after: after.length },
        classification,
        dimensions: allDimensions,
        textDiff,
        imageHashDiffBeforeAfter: (imageDiffBeforeAfter.onlyInA.length || imageDiffBeforeAfter.onlyInB.length) ? imageDiffBeforeAfter : null,
        linkHrefDiffBeforeAfter: linksDiffer(mBefore.links, mAfter.links) ? setDiff(mBefore.links, mAfter.links) : null,
    }
}

export function snippetFor(record) {
    if (record.beforeError || record.afterError) {
        return [record.beforeError && `before: ${String(record.beforeError).split('\n')[0]}`, record.afterError && `after: ${String(record.afterError).split('\n')[0]}`]
            .filter(Boolean).join(' / ')
    }

    if (record.error) {
        return String(record.error).split('\n')[0]
    }

    if (record.textDiff) {
        return `before: …${JSON.stringify(record.textDiff.before)}…  /  after: …${JSON.stringify(record.textDiff.after)}…`
    }

    const worseDims = record.dimensions.filter((d) => d.verdict === 'after_worse').map((d) => `${d.name} (raw=${d.raw}, before=${d.before}, after=${d.after})`)
    const betterDims = record.dimensions.filter((d) => d.verdict === 'after_better').map((d) => `${d.name} (raw=${d.raw}, before=${d.before}, after=${d.after})`)

    return [...worseDims, ...betterDims].join('; ')
}

/**
 * @param {object} options
 * @param {string} options.title report H1
 * @param {string} options.afterLabel human name for the "after" side, e.g. "Tiptap" or "quill-new"
 * @param {string} options.beforeLabel human name for the "before" side, e.g. "main's Quill editor"
 */
export function buildMarkdown(records, options) {
    const { title, afterLabel, beforeLabel } = options

    const byClass = {
        identical: records.filter((r) => r.classification === 'identical'),
        after_loses_vs_before: records.filter((r) => r.classification === 'after_loses_vs_before'),
        after_better_than_before: records.filter((r) => r.classification === 'after_better_than_before'),
        other_difference: records.filter((r) => r.classification === 'other_difference'),
        before_crashes_after_works: records.filter((r) => r.classification === 'before_crashes_after_works'),
        after_crashes_before_works: records.filter((r) => r.classification === 'after_crashes_before_works'),
        both_crash: records.filter((r) => r.classification === 'both_crash'),
        error: records.filter((r) => r.classification === 'error'),
    }

    const lines = []
    lines.push(`# ${title}`)
    lines.push('')
    lines.push(`Generated: ${new Date().toISOString()}`)
    lines.push(`Total notes: ${records.length}`)
    lines.push(`Comparing: **${afterLabel}** (after) vs **${beforeLabel}** (before)`)
    lines.push('')
    lines.push('## Summary')
    lines.push('')
    lines.push(`- Identical (${afterLabel} === ${beforeLabel}): ${byClass.identical.length}`)
    lines.push(`- ${afterLabel} loses something vs ${beforeLabel}: ${byClass.after_loses_vs_before.length}`)
    lines.push(`- ${afterLabel} is better than ${beforeLabel} (recovers content lost vs raw): ${byClass.after_better_than_before.length}`)
    lines.push(`- Other differences (neither strictly better nor worse): ${byClass.other_difference.length}`)
    lines.push(`- **${beforeLabel} crashes but ${afterLabel} works**: ${byClass.before_crashes_after_works.length}`)
    lines.push(`- Regression: ${afterLabel} crashes but ${beforeLabel} worked: ${byClass.after_crashes_before_works.length}`)
    lines.push(`- Both crash: ${byClass.both_crash.length}`)
    lines.push(`- Errors (tooling threw, not attributable to either side): ${byClass.error.length}`)
    lines.push('')

    for (const [key, label] of [
        ['after_loses_vs_before', `Notes where ${afterLabel} loses something vs ${beforeLabel}`],
        ['after_better_than_before', `Notes where ${afterLabel} is better than ${beforeLabel}`],
        ['other_difference', 'Notes with other differences'],
        ['before_crashes_after_works', `${beforeLabel} crashes on these notes, ${afterLabel} does not`],
        ['after_crashes_before_works', `Regression candidates: ${afterLabel} crashes where ${beforeLabel} did not`],
        ['both_crash', 'Notes where both crash'],
        ['error', 'Notes that errored in tooling (not attributable to either side)'],
    ]) {
        const list = byClass[key]
        lines.push(`## ${label} (${list.length})`)
        lines.push('')

        if (list.length === 0) {
            lines.push('_None._')
            lines.push('')
            continue
        }

        // Group by which dimension(s) differ, for a readable summary instead of 1000 one-line entries
        const groups = new Map()
        for (const record of list) {
            const key2 = record.dimensions
                ? (record.dimensions.filter((d) => d.verdict !== 'same').map((d) => d.name).sort().join(',') || 'text')
                : (record.classification === 'error' ? 'tooling error' : 'crash')
            if (!groups.has(key2)) {
                groups.set(key2, [])
            }
            groups.get(key2).push(record)
        }

        for (const [dims, group] of [...groups.entries()].sort((a, b) => b[1].length - a[1].length)) {
            lines.push(`### ${dims} (${group.length} notes)`)
            lines.push('')
            const sample = group.slice(0, 8)
            for (const record of sample) {
                const snippet = snippetFor(record)
                lines.push(`- note ${record.id}${record.title ? ` ("${String(record.title).slice(0, 40)}")` : ''}: ${snippet}`)
            }
            if (group.length > sample.length) {
                lines.push(`- … and ${group.length - sample.length} more (ids: ${group.slice(8, 58).map((r) => r.id).join(', ')}${group.length > 58 ? ', …' : ''})`)
            }
            lines.push('')
        }
    }

    return lines.join('\n')
}
