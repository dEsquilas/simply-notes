<?php

/**
 * Generates data/tiptap-validation/quill-new-before.jsonl: what a candidate "quill-new" editor
 * (main's Quill 2.0.3 with quill-table-better@1.2.3 + @enzedonline/quill-blot-formatter2@3.2.0
 * instead of quill-table-ui + quill-image-resize) shows for every note, through the real built
 * app and real headless Chromium — the same method as GenerateQuillBeforeSnapshot.php.
 *
 * Requires the app to be TEMPORARILY wired to the quill-new reference implementation: copy
 * scripts/tiptap-validation/reference-editors/quill-new/* into resources/js/components/quill/
 * (as QuillEditor.vue), point Note.vue's editor import at it, `pnpm add quill-table-better@1.2.3
 * @enzedonline/quill-blot-formatter2@3.2.0`, then `pnpm run build`. Revert all of that (and
 * re-run `pnpm run build`) once the snapshot is written.
 *
 * Not part of the Browser test suite (see GenerateQuillBeforeSnapshot.php for why). Run:
 *   vendor/bin/pest tests/browser/generators/GenerateQuillNewSnapshot.php
 */

use App\Models\Note;
use App\Models\Notebook;
use App\Models\User;

it('DIAGNOSTIC/GENERATOR: snapshots what quill-new (table-better + blot-formatter2) shows for every note', function () {
    $notesPath = base_path('data/tiptap-validation/notes.jsonl');
    $outPath = base_path('data/tiptap-validation/quill-new-before.jsonl');

    expect(file_exists($notesPath))->toBeTrue('Run scripts/tiptap-validation/export-notes.php first.');

    $user = User::factory()->create();
    $notebook = Notebook::factory()->ownedBy($user)->create();
    $note = Note::factory()->for($notebook)->create();
    test()->actingAs($user);

    $url = "/notebook/{$notebook->id}/note/{$note->id}";
    $page = visit($url);
    $page->assertVisible('[data-test="note-body"] .ql-toolbar');

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
