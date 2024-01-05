<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\{Note, Notebook};
use Inertia\Inertia;

class NoteController extends Controller
{

    public function index($notebookId){

        $notebook = Notebook::find($notebookId);

        if($notebook->owner != auth()->id()){
            return redirect()->route('notebooks');
        }

        $notes = $notebook->notes()->where('status', 0)->orderBy('updated_at', 'DESC')->get();

        return Inertia::render('Notebook', [
            'inNotebook' => $notebook,
            'inNotes' => $notes
        ]);
    }

    public function create($notebookId){

        $notebook = Notebook::find($notebookId);

        if($notebook->owner != auth()->id()){
            return redirect()->route('notebooks');
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

    public function update($noteId){

        $note = Note::find($noteId);

        if(!$note){
            return response()->json([
                'message' => 'Note not found',
            ], 404);
        }

        if($note->notebook->owner != auth()->id()){
            return redirect()->route('notebooks');
        }

        $note->title = request()->title;
        $note->content = request()->content;
        $note->save();

        return response()->json([
            'note' => $note,
        ], 200);

    }

    public function trash($noteId){

        $note = Note::find($noteId);

        if(!$note){
            return response()->json([
                'message' => 'Note not found',
            ], 404);
        }

        if($note->notebook->owner != auth()->id()){
            return redirect()->route('notebooks');
        }

        $note->status = 1;
        $note->save();

        return response()->json([], 200);

    }

}
