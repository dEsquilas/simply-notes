<?php

namespace App\Http\Controllers;

use App\Models\ImportJob;
use App\Models\Note;
use App\Models\Notebook;
use Illuminate\Http\Request;
use Inertia\Inertia;

class NotebookController extends Controller
{
    public function index(){

        $notebooks = Notebook::where('owner', auth()->id())
            ->where('status', 0)
            ->withCount(['notes' => fn ($query) => $query->where('status', 0)])
            ->orderBy('created_at', 'DESC')
            ->get();

        return Inertia::render('notebooks/List', [
            'notebooks' => $notebooks
        ]);
    }

    public function view($notebookId){

        $notebook = Notebook::find($notebookId);

        if($notebook->status == 1){
            return redirect()->route('notebooks.index');
        }

        $notes = $notebook->notes()->where('status', 0)->orderBy('updated_at', 'DESC')->get();

        return Inertia::render('notebooks/View', [
            'inNotebook' => $notebook,
            'inNotes' => $notes
        ]);
    }

    public function create(Request $request){

        $request->validate([
            'name' => 'nullable|string|max:255',
        ]);

        if(blank($request->name)){
            return response()->json([
                'message' => 'Please provide a name for the notebook'
            ], 422);
        }

        $notebook = new Notebook();
        $notebook->owner = auth()->id();
        $notebook->name = $request->name;
        $notebook->save();

        return response()->json([
            'notebook' => $notebook
        ], 200);

    }

    public function trash($notebookId){

        $notebook = Notebook::find($notebookId);

        $notebook->status = 1;
        $notebook->save();

        return response()->json([
            'message' => 'Notebook deleted successfully'
        ], 200);

    }

    public function trashView(){

        $notebooks = Notebook::where('owner', auth()->id())->where('status', 1)->get();

        // Trashed notes can be restored while their notebook is active
        $notes = Note::where('status', 1)
            ->whereHas('notebook', fn ($query) => $query->where('owner', auth()->id())->where('status', 0))
            ->with('notebook:id,name')
            ->orderBy('updated_at', 'DESC')
            ->get(['id', 'title', 'notebook_id', 'updated_at']);

        return Inertia::render('notebooks/Trash', [
            'notebooks' => $notebooks,
            'notes' => $notes,
        ]);

    }

    public function delete($notebookId){

        $notebook = Notebook::find($notebookId);

        if($notebook->status != 1){
            return response()->json([
                'message' => 'Only notebooks in the trash can be deleted permanently'
            ], 422);
        }

        $notebook->notes()->delete();
        ImportJob::where('notebook_id', $notebook->id)->delete();
        $notebook->delete();

        return response()->json([
            'message' => 'Notebook deleted permanently'
        ], 200);

    }

    public function restore($notebookId){

        $notebook = Notebook::find($notebookId);

        $notebook->status = 0;
        $notebook->save();

        return response()->json([
            'message' => 'Notebook restored successfully'
        ], 200);

    }

}
