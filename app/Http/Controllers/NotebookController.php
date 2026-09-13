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
            ->withCount('notes')
            ->orderBy('created_at', 'DESC')
            ->get();

        return Inertia::render('notebooks/List', [
            'notebooks' => $notebooks
        ]);
    }

    public function view($notebookId){

        $notebook = Notebook::withTrashed()->find($notebookId);

        if($notebook->trashed()){
            return redirect()->route('notebooks.index');
        }

        $notes = $notebook->notes()->orderBy('updated_at', 'DESC')->get();

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

        $notebook = Notebook::withTrashed()->find($notebookId);

        if(!$notebook->trashed()){
            $notebook->delete();
        }

        return response()->json([
            'message' => 'Notebook deleted successfully'
        ], 200);

    }

    public function trashView(){

        $notebooks = Notebook::onlyTrashed()->where('owner', auth()->id())->orderBy('deleted_at', 'DESC')->get();

        // Trashed notes can be restored while their notebook is active
        $notes = Note::onlyTrashed()
            ->whereHas('notebook', fn ($query) => $query->where('owner', auth()->id()))
            ->with('notebook:id,name')
            ->orderBy('deleted_at', 'DESC')
            ->get(['id', 'title', 'notebook_id', 'deleted_at']);

        return Inertia::render('notebooks/Trash', [
            'notebooks' => $notebooks,
            'notes' => $notes,
            'retentionDays' => config('trash.retention_days'),
        ]);

    }

    public function delete($notebookId){

        $notebook = Notebook::withTrashed()->find($notebookId);

        if(!$notebook->trashed()){
            return response()->json([
                'message' => 'Only notebooks in the trash can be deleted permanently'
            ], 422);
        }

        $notebook->notes()->withTrashed()->forceDelete();
        ImportJob::where('notebook_id', $notebook->id)->delete();
        $notebook->forceDelete();

        return response()->json([
            'message' => 'Notebook deleted permanently'
        ], 200);

    }

    public function restore($notebookId){

        $notebook = Notebook::withTrashed()->find($notebookId);

        if($notebook->trashed()){
            $notebook->restore();
        }

        return response()->json([
            'message' => 'Notebook restored successfully'
        ], 200);

    }

    public function emptyTrash(){

        Notebook::onlyTrashed()->where('owner', auth()->id())->get()->each(function (Notebook $notebook) {
            $notebook->notes()->withTrashed()->forceDelete();
            ImportJob::where('notebook_id', $notebook->id)->delete();
            $notebook->forceDelete();
        });

        // Trashed notes whose notebook is still active
        Note::onlyTrashed()
            ->whereHas('notebook', fn ($query) => $query->where('owner', auth()->id()))
            ->forceDelete();

        return response()->json([
            'message' => 'Trash emptied successfully'
        ], 200);

    }

}
