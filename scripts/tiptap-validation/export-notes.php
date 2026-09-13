<?php

/**
 * Exports every note's decrypted {id, title, content} to data/tiptap-validation/notes.jsonl.
 *
 * READ-ONLY: this only ever selects from the notes table (Eloquent `cursor()`, one row at a time).
 * It never calls save/update/insert/delete, and never touches any other table.
 *
 * Usage (from the project root): php scripts/tiptap-validation/export-notes.php
 */

require __DIR__.'/../../vendor/autoload.php';

use App\Models\Note;

$app = require __DIR__.'/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$outDir = __DIR__.'/../../data/tiptap-validation';
if (! is_dir($outDir)) {
    mkdir($outDir, 0755, true);
}

$outPath = $outDir.'/notes.jsonl';
$handle = fopen($outPath, 'w');

$count = 0;

// select()+cursor(): reads rows one at a time, decrypts via the model's casts, never writes anything back.
foreach (Note::query()->select(['id', 'title', 'content'])->orderBy('id')->cursor() as $note) {
    $line = json_encode([
        'id' => $note->id,
        'title' => $note->title,
        'content' => $note->content,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    if ($line === false) {
        fwrite(STDERR, "Failed to encode note {$note->id}: ".json_last_error_msg().PHP_EOL);

        continue;
    }

    fwrite($handle, $line.PHP_EOL);
    $count++;

    if ($count % 100 === 0) {
        fwrite(STDOUT, "Exported {$count} notes…".PHP_EOL);
    }
}

fclose($handle);

fwrite(STDOUT, "Done: exported {$count} notes to {$outPath}".PHP_EOL);
