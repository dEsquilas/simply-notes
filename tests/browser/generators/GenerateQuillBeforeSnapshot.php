<?php

/**
 * Generates data/tiptap-validation/quill-before.jsonl: for every exported note, what main's
 * ACTUAL Quill editor shows when the note is opened through the real built app (real Vite/Rolldown
 * bundle, real Laravel app, real headless Chromium via Pest Browser) — not a reimplementation.
 *
 * This exists because an earlier, hand-rolled Node/Playwright harness that loaded Quill,
 * quill-image-resize and quill-table-ui from their own prebuilt npm UMD files (bypassing the
 * project's own bundler) hit a class-inheritance interop crash for every note containing an
 * <img> that does NOT reproduce in the real app: this file (driven through the real app instead)
 * is the authoritative source for the "before" comparison.
 *
 * Not part of the Browser test suite (no "Test.php" suffix: PHPUnit's default directory suite
 * only picks up *Test.php, and tests/arch/BrowserTestsTest.php only scans *Test.php too), but it
 * still lives under tests/browser so Pest.php's ->in('browser') binding (RefreshDatabase, the
 * visit()/navigate() browser plugin) applies to it. Run explicitly:
 *
 *   vendor/bin/pest tests/browser/generators/GenerateQuillBeforeSnapshot.php
 *
 * Requires the app to be TEMPORARILY wired back to main's original Quill editor: copy
 * scripts/tiptap-validation/reference-editors/quill-original/* into
 * resources/js/components/quill/, point Note.vue's editor import at it, `pnpm add quill@2.0.3
 * quill-table-ui@^1.0.7` and `pnpm add -D quill-image-resize@^3.0.9`, then `pnpm run build`.
 * Revert all of that (and re-run `pnpm run build`) once the snapshot is written, so the app goes
 * back to using Tiptap and Quill is not a dependency of the shipped app.
 */

use App\Models\Note;
use App\Models\Notebook;
use App\Models\User;

it('DIAGNOSTIC/GENERATOR: snapshots what the real built Quill editor shows for every note', function () {
    $notesPath = base_path('data/tiptap-validation/notes.jsonl');
    $outPath = base_path('data/tiptap-validation/quill-before.jsonl');

    expect(file_exists($notesPath))->toBeTrue('Run scripts/tiptap-validation/export-notes.php first.');

    $user = User::factory()->create();
    $notebook = Notebook::factory()->ownedBy($user)->create();
    // A single reusable note: its content is overwritten and the SAME URL reloaded for every
    // exported note, so only one page navigation per note is needed instead of a fresh visit().
    $note = Note::factory()->for($notebook)->create();
    test()->actingAs($user);

    $url = "/notebook/{$notebook->id}/note/{$note->id}";
    $page = visit($url);
    $page->assertVisible('[data-test="toolbar"], [data-test="note-body"] .ql-toolbar');

    $out = fopen($outPath, 'w');
    $handle = fopen($notesPath, 'r');
    $count = 0;
    $imageCount = 0;
    $jsErrorNotes = [];
    $limit = getenv('SNAPSHOT_LIMIT') !== false ? (int) getenv('SNAPSHOT_LIMIT') : PHP_INT_MAX;

    while ($count < $limit && ($line = fgets($handle)) !== false) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }

        $decoded = json_decode($line, true);
        $id = $decoded['id'];
        $content = $decoded['content'] ?? '';

        // Written directly to the SQLite testing DB (never MySQL), bypassing the encrypted cast
        // round-trip cost: forceFill + save() through Eloquent still works, this is just faster.
        $note->forceFill(['content' => $content])->save();

        $page->navigate($url);
        $page->wait(0.05);

        $editorHtml = $page->script('document.querySelector(\'[data-test="note-body"] .ql-editor\')?.innerHTML ?? null');
        $jsErrors = $page->script('window.__pestBrowser?.jsErrors ?? []');

        if (! empty($jsErrors)) {
            $jsErrorNotes[$id] = $jsErrors;
        }
        if (str_contains($content, '<img')) {
            $imageCount++;
        }

        fwrite($out, json_encode(['id' => $id, 'before' => $editorHtml, 'jsErrors' => $jsErrors], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).PHP_EOL);

        $count++;
        if ($count % 100 === 0) {
            fwrite(STDOUT, "Snapshotted {$count} notes ({$imageCount} with images so far, ".count($jsErrorNotes)." JS-error notes)...".PHP_EOL);
        }
    }

    fclose($handle);
    fclose($out);

    fwrite(STDOUT, 'Done: '.$count.' notes, '.count($jsErrorNotes).' had a JS error while open. IDs: '.implode(',', array_keys($jsErrorNotes)).PHP_EOL);

    expect($count)->toBeGreaterThan(0);
});
