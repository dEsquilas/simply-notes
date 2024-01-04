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

        $notes = $notebook->notes()->orderBy('updated_at', 'ASC')->get();

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

}
