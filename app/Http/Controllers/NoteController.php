<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\{Note, Notebook};
use App\Services\NoteHtmlSanitizer;
use App\Services\NoteVersionService;
use Inertia\Inertia;

class NoteController extends Controller
{

    public function view($notebookId, $noteId){

        $note = Note::withTrashed()->find($noteId);
        $notebook = $note->notebook()->withTrashed()->first();

        if($notebook->id != $notebookId || $notebook->trashed()){
            return redirect()->route('notebooks.index');
        }

        if($note->trashed()){
            return redirect()->route('notebook.view', $notebook->id);
        }

        $notes = $notebook->notes()->orderBy('updated_at', 'DESC')->get();

        return Inertia::render('notebooks/View', [
            'inNotebook' => $notebook,
            'inNotes' => $notes,
            'currentNote' => $note,
        ]);

    }

    public function create($notebookId){

        if(Notebook::withTrashed()->find($notebookId)->trashed()){
            return response()->json([
                'message' => 'Notes cannot be created in a notebook in the trash',
            ], 422);
        }

        $note = new Note();
        $note->notebook_id = $notebookId;
        $note->title = "";
        $note->content = "";
        $note->save();

        return response()->json([
            'note' => $note,
        ], 200);

    }

    public function update(Request $request, $noteId, NoteHtmlSanitizer $sanitizer, NoteVersionService $versions){

        $request->validate([
            'title' => 'nullable|string',
            'content' => 'nullable|string',
        ]);

        $note = Note::withTrashed()->find($noteId);
        $newContent = $sanitizer->sanitize($request->get('content'));

        // At most one version per request: a substantial change wins over a plain session start
        if ($versions->isSubstantialChange($note->content, $newContent)) {
            $versions->snapshot($note, 'substantial_change', pinned: true);
        } elseif ($note->updated_at && $note->updated_at->lt(now()->subMinutes(config('versions.inactivity_minutes')))) {
            $versions->snapshot($note, 'session_start');
        }

        $note->title = $request->get('title');
        $note->content = $newContent;
        $note->save();

        return response()->json([
            'note' => $note,
        ], 200);

    }

    public function trash($noteId){

        $note = Note::withTrashed()->find($noteId);

        if(!$note->trashed()){
            $note->delete();
        }

        return response()->json();

    }

    public function restore($noteId){

        $note = Note::withTrashed()->find($noteId);

        if($note->trashed()){
            $note->restore();
        }

        return response()->json();

    }

    public function delete($noteId){

        $note = Note::withTrashed()->find($noteId);

        if(!$note->trashed()){
            return response()->json([
                'message' => 'Only notes in the trash can be deleted permanently',
            ], 422);
        }

        $note->forceDelete();

        return response()->json([
            'message' => 'Note deleted permanently',
        ], 200);

    }

}
