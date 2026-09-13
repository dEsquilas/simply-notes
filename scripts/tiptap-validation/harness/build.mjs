// Bundles tiptap.entry.js into a standalone ES module the validation harness loads into a
// headless page with Playwright. Uses the project's own Vite (resolved from the repo root's
// node_modules) so @tiptap/* and the app's own editor modules resolve exactly as they do in the
// real build. (Quill itself is no longer bundled here — see reference-editors/ and
// tests/browser/generators/: main's real Quill editor is snapshotted through the real built app
// instead, since an isolated Rolldown bundle of quill-image-resize hit an interop bug that does
// not reproduce in the app's own build.)
import { build } from 'vite'
import { fileURLToPath } from 'node:url'
import path from 'node:path'

const dir = path.dirname(fileURLToPath(import.meta.url))
const projectRoot = path.join(dir, '..', '..', '..')

async function bundle(entry, subdir) {
    await build({
        root: projectRoot,
        configFile: false,
        logLevel: 'warning',
        publicDir: false,
        build: {
            outDir: path.join(dir, 'dist', subdir),
            emptyOutDir: true,
            minify: false,
            target: 'esnext',
            modulePreload: false,
            rollupOptions: {
                input: path.join(dir, entry),
                output: {
                    entryFileNames: 'bundle.js',
                    chunkFileNames: '[name].js',
                    format: 'es',
                },
            },
        },
    })
}

await bundle('tiptap.entry.js', 'tiptap')

console.log('Built dist/tiptap/bundle.js')
