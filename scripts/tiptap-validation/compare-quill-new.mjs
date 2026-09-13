// Compares "quill-new" (main's Quill 2.0.3 with quill-table-better@1.2.3 +
// @enzedonline/quill-blot-formatter2@3.2.0 instead of quill-table-ui + quill-image-resize)
// against main's REAL current Quill editor ("before"), for every exported note. Both sides come
// from real-app snapshots (tests/browser/generators/GenerateQuillBeforeSnapshot.php and
// GenerateQuillNewSnapshot.php) — this script only extracts metrics and diffs them.
//
// Writes data/tiptap-validation/report-quill-new.json and report-quill-new.md.
//
// Usage (from the project root), after both snapshot files exist:
//   node scripts/tiptap-validation/compare-quill-new.mjs [--limit=N] [--from-id=N]

import { chromium } from 'playwright'
import { createReadStream, mkdirSync, readFileSync, writeFileSync } from 'node:fs'
import { createInterface } from 'node:readline'
import path from 'node:path'
import { fileURLToPath } from 'node:url'
import { buildRecord, buildMarkdown } from './lib.mjs'

const dir = path.dirname(fileURLToPath(import.meta.url))
const projectRoot = path.join(dir, '..', '..')
const harnessDir = path.join(dir, 'harness')
const dataDir = path.join(projectRoot, 'data', 'tiptap-validation')
const notesPath = path.join(dataDir, 'notes.jsonl')
const quillBeforePath = path.join(dataDir, 'quill-before.jsonl')
const quillNewPath = path.join(dataDir, 'quill-new-before.jsonl')

const args = Object.fromEntries(process.argv.slice(2).map((arg) => {
    const [key, value] = arg.replace(/^--/, '').split('=')

    return [key, value ?? true]
}))
const limit = args.limit ? Number(args.limit) : Infinity
const fromId = args['from-id'] ? Number(args['from-id']) : 0

function loadSnapshot(filePath) {
    const map = new Map()
    for (const line of readFileSync(filePath, 'utf8').split('\n')) {
        if (!line.trim()) {
            continue
        }
        const row = JSON.parse(line)
        map.set(row.id, row)
    }

    return map
}

async function main() {
    mkdirSync(dataDir, { recursive: true })

    const quillBefore = loadSnapshot(quillBeforePath)
    const quillNew = loadSnapshot(quillNewPath)

    const browser = await chromium.launch()
    const page = await browser.newPage()
    page.setDefaultTimeout(120_000)
    await page.setContent('<!doctype html><html><body></body></html>')
    await page.addScriptTag({ path: path.join(harnessDir, 'metrics.js') })
    await page.waitForFunction(() => window.extractMetrics)

    const rl = createInterface({ input: createReadStream(notesPath, { encoding: 'utf8' }) })

    const records = []
    let processed = 0
    let errors = 0

    for await (const line of rl) {
        if (!line.trim()) {
            continue
        }

        const note = JSON.parse(line)
        if (note.id < fromId) {
            continue
        }
        if (processed >= limit) {
            break
        }

        const raw = note.content ?? ''
        const beforeRow = quillBefore.get(note.id)
        const afterRow = quillNew.get(note.id)

        const beforeError = !beforeRow
            ? 'note missing from quill-before.jsonl'
            : (beforeRow.jsErrors && beforeRow.jsErrors.length ? JSON.stringify(beforeRow.jsErrors) : null)
        const afterError = !afterRow
            ? 'note missing from quill-new-before.jsonl'
            : (afterRow.jsErrors && afterRow.jsErrors.length ? JSON.stringify(afterRow.jsErrors) : null)

        if (beforeError || afterError) {
            errors++
            records.push({
                id: note.id,
                title: note.title,
                classification: beforeError && afterError
                    ? 'both_crash'
                    : (beforeError ? 'before_crashes_after_works' : 'after_crashes_before_works'),
                beforeError,
                afterError,
            })
        } else {
            try {
                const mRaw = await page.evaluate((h) => window.extractMetrics(h), raw)
                const mBefore = await page.evaluate((h) => window.extractMetrics(h), beforeRow.before)
                const mAfter = await page.evaluate((h) => window.extractMetrics(h), afterRow.before)

                records.push(buildRecord(note, raw, beforeRow.before, afterRow.before, mRaw, mBefore, mAfter))
            } catch (error) {
                errors++
                records.push({
                    id: note.id, title: note.title, classification: 'error', error: String(error && error.message || error),
                })
            }
        }

        processed++
        if (processed % 100 === 0) {
            console.log(`Processed ${processed} notes (${errors} errors)…`)
        }
    }

    await browser.close()

    writeFileSync(path.join(dataDir, 'report-quill-new.json'), JSON.stringify({ generatedAt: new Date().toISOString(), total: records.length, records }, null, 2))
    writeFileSync(path.join(dataDir, 'report-quill-new.md'), buildMarkdown(records, {
        title: 'quill-new (table-better + blot-formatter2) validation report',
        afterLabel: 'quill-new',
        beforeLabel: "main's current Quill editor",
    }))

    console.log(`Done: ${records.length} notes processed, ${errors} errors. Reports written to ${dataDir}`)
}

main().catch((error) => {
    console.error(error)
    process.exit(1)
})
