<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\{Note, Notebook};
use App\Services\NoteHtmlSanitizer;
use Inertia\Inertia;

class NoteController extends Controller
{

    public function view($notebookId, $noteId){

        $note = Note::find($noteId);
        $notebook = $note->notebook;

        if($notebook->id != $notebookId || $notebook->status == 1){
            return redirect()->route('notebooks.index');
        }

        if($note->status == 1){
            return redirect()->route('notebook.view', $notebook->id);
        }

        $notes = $notebook->notes()->where('status', 0)->orderBy('updated_at', 'DESC')->get();

        return Inertia::render('notebooks/View', [
            'inNotebook' => $notebook,
            'inNotes' => $notes,
            'currentNote' => $note,
        ]);

    }

    public function create($notebookId){

        if(Notebook::find($notebookId)->status == 1){
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

    public function update(Request $request, $noteId, NoteHtmlSanitizer $sanitizer){

        $request->validate([
            'title' => 'nullable|string',
            'content' => 'nullable|string',
        ]);

        $note = Note::find($noteId);

        $note->title = $request->get('title');
        $note->content = $sanitizer->sanitize($request->get('content'));
        $note->save();

        return response()->json([
            'note' => $note,
        ], 200);

    }

    public function trash($noteId){

        $note = Note::find($noteId);

        $note->status = 1;
        $note->save();

        return response()->json();

    }

    public function restore($noteId){

        $note = Note::find($noteId);

        $note->status = 0;
        $note->save();

        return response()->json();

    }

}
