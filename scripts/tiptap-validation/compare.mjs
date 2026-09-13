// Compares, for every exported note, what main's REAL Quill editor shows ("before") against what
// the new Tiptap editor shows ("after"). "before" comes from data/tiptap-validation/quill-before.jsonl,
// a snapshot taken by actually opening every note through the real built app in a real browser
// (tests/browser/generators/GenerateQuillBeforeSnapshot.php) — not a reimplementation, and not
// bundled independently of the app (an earlier version of this script did that and hit a bundler
// interop bug in quill-image-resize that does not reproduce in the real app; see report.md).
// "after" is rendered live here using the exact same Tiptap extensions/config as the app
// (resources/js/components/editor), via scripts/tiptap-validation/harness.
//
// Writes data/tiptap-validation/report.json (per-note metrics and diffs) and report.md (a summary).
//
// Usage (from the project root):
//   1. php scripts/tiptap-validation/export-notes.php
//   2. vendor/bin/pest tests/browser/generators/GenerateQuillBeforeSnapshot.php
//   3. node scripts/tiptap-validation/harness/build.mjs
//   4. node scripts/tiptap-validation/compare.mjs [--limit=N] [--from-id=N]

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

const args = Object.fromEntries(process.argv.slice(2).map((arg) => {
    const [key, value] = arg.replace(/^--/, '').split('=')

    return [key, value ?? true]
}))
const limit = args.limit ? Number(args.limit) : Infinity
const fromId = args['from-id'] ? Number(args['from-id']) : 0

const RECYCLE_EVERY = 300

function loadQuillBefore() {
    const map = new Map()
    for (const line of readFileSync(quillBeforePath, 'utf8').split('\n')) {
        if (!line.trim()) {
            continue
        }
        const row = JSON.parse(line)
        map.set(row.id, row)
    }

    return map
}

async function newHarnessPage(browser) {
    const page = await browser.newPage()
    page.setDefaultTimeout(120_000)
    await page.setContent('<!doctype html><html><body></body></html>')
    await page.addScriptTag({ path: path.join(harnessDir, 'dist/tiptap/bundle.js'), type: 'module' })
    await page.addScriptTag({ path: path.join(harnessDir, 'metrics.js') })
    await page.waitForFunction(() => window.tiptapConvert && window.extractMetrics)

    return page
}

async function main() {
    mkdirSync(dataDir, { recursive: true })

    const quillBefore = loadQuillBefore()

    const browser = await chromium.launch()
    let page = await newHarnessPage(browser)

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

        let before = beforeRow ? beforeRow.before : null
        let beforeError = !beforeRow
            ? 'note missing from quill-before.jsonl: re-run GenerateQuillBeforeSnapshot.php'
            : (beforeRow.jsErrors && beforeRow.jsErrors.length ? JSON.stringify(beforeRow.jsErrors) : null)
        let after = null
        let afterError = null

        try {
            after = await page.evaluate((h) => window.tiptapConvert(h), raw)
        } catch (error) {
            afterError = String(error && error.message || error)
        }

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
                const mBefore = await page.evaluate((h) => window.extractMetrics(h), before)
                const mAfter = await page.evaluate((h) => window.extractMetrics(h), after)

                records.push(buildRecord(note, raw, before, after, mRaw, mBefore, mAfter))
            } catch (error) {
                errors++
                records.push({
                    id: note.id, title: note.title, classification: 'error', error: String(error && error.message || error),
                })
            }
        }

        processed++
        if (processed % 50 === 0) {
            console.log(`Processed ${processed} notes (${errors} errors)…`)
        }

        if (processed % RECYCLE_EVERY === 0) {
            await page.close()
            page = await newHarnessPage(browser)
        }
    }

    await browser.close()

    writeFileSync(path.join(dataDir, 'report.json'), JSON.stringify({ generatedAt: new Date().toISOString(), total: records.length, records }, null, 2))
    writeFileSync(path.join(dataDir, 'report.md'), buildMarkdown(records, {
        title: 'Tiptap migration validation report',
        afterLabel: 'Tiptap',
        beforeLabel: "main's Quill editor",
    }))

    console.log(`Done: ${records.length} notes processed, ${errors} errors. Reports written to ${dataDir}`)
}

main().catch((error) => {
    console.error(error)
    process.exit(1)
})
