<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\{Note, Notebook};
use Inertia\Inertia;

class NoteController extends Controller
{

    public function view($noteId){

        $note = Note::find($noteId);
        $notebook = $note->notebook;

        $notes = $notebook->notes()->where('status', 0)->orderBy('updated_at', 'DESC')->get();


        return Inertia::render('Notebook', [
            'inNotebook' => $notebook,
            'inNotes' => $notes,
            'currentNote' => $note,
        ]);

    }

    public function create($notebookId){

        $notebook = Notebook::find($notebookId);

        $note = new Note();
        $note->notebook_id = $notebookId;
        $note->title = "";
        $note->content = "";
        $note->save();

        return response()->json([
            'note' => $note,
        ], 200);

    }

    public function update($noteId){

        $note = Note::find($noteId);

        $note->title = request()->get('title');
        $note->content = request()->get('content');
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
