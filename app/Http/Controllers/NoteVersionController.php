<?php

namespace App\Http\Controllers;

use App\Models\Note;
use App\Models\NoteVersion;
use App\Services\NoteHtmlSanitizer;
use App\Services\NoteVersionService;
use Illuminate\Http\Request;

class NoteVersionController extends Controller
{

    public function index($noteId){

        $versions = NoteVersion::where('note_id', $noteId)
            ->orderByDesc('created_at')
            ->get(['id', 'reason', 'label', 'pinned', 'created_at']);

        return response()->json([
            'versions' => $versions,
        ]);

    }

    public function show($noteId, $versionId, NoteHtmlSanitizer $sanitizer){

        $version = $this->findVersion($noteId, $versionId);

        return response()->json([
            'version' => [
                'id' => $version->id,
                'title' => $version->title,
                'content' => $sanitizer->sanitize($version->content),
            ],
        ]);

    }

    public function store(Request $request, $noteId, NoteVersionService $versions){

        $data = $request->validate([
            'reason' => 'required|in:manual,session_end',
            'label' => 'nullable|string|max:255',
        ]);

        $note = Note::withTrashed()->findOrFail($noteId);
        $label = $data['label'] ?? null;
        $isManual = $data['reason'] === 'manual';

        $version = $versions->snapshot($note, $data['reason'], $label, pinned: $isManual);

        // Manual save with nothing changed since the latest version: that version is kept (the
        // snapshot already pinned it). A label is applied to it only if it has none; a different
        // existing label is never overwritten - the new one gets its own version instead.
        if (! $version && $isManual) {
            $version = $note->versions()->latest('id')->first();

            if ($version && $label && $version->label !== $label) {
                if (blank($version->label)) {
                    $version->label = $label;
                    $version->save();
                } else {
                    $version = $versions->snapshot($note, 'manual', $label, pinned: true, allowDuplicate: true);
                }
            }
        }

        return response()->json([
            'version' => $version,
        ], $version && $version->wasRecentlyCreated ? 201 : 200);

    }

    public function restore($noteId, $versionId, NoteVersionService $versions, NoteHtmlSanitizer $sanitizer){

        $version = $this->findVersion($noteId, $versionId);
        $note = $version->note()->withTrashed()->first();

        // The state about to be overwritten precedes a destructive change: never let it be pruned
        $versions->snapshot($note, 'restore', pinned: true);

        $note->title = $version->title;
        $note->content = $sanitizer->sanitize($version->content);
        $note->save();

        return response()->json([
            'note' => $note,
        ]);

    }

    private function findVersion($noteId, $versionId): NoteVersion{

        return NoteVersion::where('note_id', $noteId)->findOrFail($versionId);

    }

}
