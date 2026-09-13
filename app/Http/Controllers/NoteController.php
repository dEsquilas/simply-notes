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

        if($notebook->id != $notebookId){
            return redirect()->route('notebooks.index');
        }

        $notes = $notebook->notes()->where('status', 0)->orderBy('updated_at', 'DESC')->get();

        return Inertia::render('notebooks/View', [
            'inNotebook' => $notebook,
            'inNotes' => $notes,
            'currentNote' => $note,
        ]);

    }

    public function create($notebookId){

        $note = new Note();
        $note->notebook_id = $notebookId;
        $note->title = "";
        $note->content = "";
        $note->save();

        return response()->json([
            'note' => $note,
        ], 200);

    }

    public function update($noteId, NoteHtmlSanitizer $sanitizer){

        $note = Note::find($noteId);

        $note->title = request()->get('title');
        $note->content = $sanitizer->sanitize(request()->get('content'));
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

}
