<?php

namespace App\Http\Controllers;

use App\Models\Notebook;
use Illuminate\Http\Request;
use Inertia\Inertia;

class NotebookController extends Controller
{
    public function index(){

        $notebooks = Notebook::where('owner', auth()->id())->where('status', 0)->get();

        return Inertia::render('Notebooks', [
            'notebooks' => $notebooks
        ]);
    }

    public function view($notebookId){

        $notebook = Notebook::find($notebookId);

        /*if($notebook->owner != auth()->id()){
            return redirect()->route('notebooks.index');
        }*/

        if($notebook->status == 1){
            return redirect()->route('notebooks.index');
        }

        $notes = $notebook->notes()->where('status', 0)->orderBy('updated_at', 'DESC')->get();

        return Inertia::render('Notebook', [
            'inNotebook' => $notebook,
            'inNotes' => $notes
        ]);
    }

    public function create(Request $request){

        if(!$request->name){
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

        if(!$notebook){
            return response()->json([
                'message' => 'Notebook not found'
            ], 404);
        }

        if($notebook->owner != auth()->id()){
            return response()->json([
                'message' => 'Not allowed'
            ], 403);
        }

        $notebook->status = 1;
        $notebook->save();

        return response()->json([
            'message' => 'Notebook deleted successfully'
        ], 200);

    }

    public function trashView(){

        $notebooks = Notebook::where('owner', auth()->id())->where('status', 1)->get();

        return Inertia::render('Notebooks/Trash', [
            'notebooks' => $notebooks
        ]);

    }

    public function delete($notebookId){

        $notebook = Notebook::find($notebookId);

        if(!$notebook){
            return response()->json([
                'message' => 'Notebook not found'
            ], 404);
        }

        if($notebook->owner != auth()->id()){
            return response()->json([
                'message' => 'Not allowed'
            ], 403);
        }

        $notebook->notes()->delete();
        $notebook->delete();

        return response()->json([
            'message' => 'Notebook deleted permanently'
        ], 200);

    }

    public function restore($notebookId){

        $notebook = Notebook::find($notebookId);

        if(!$notebook){
            return response()->json([
                'message' => 'Notebook not found'
            ], 404);
        }

        if($notebook->owner != auth()->id()){
            return response()->json([
                'message' => 'Not allowed'
            ], 403);
        }

        $notebook->status = 0;
        $notebook->save();

        return response()->json([
            'message' => 'Notebook restored successfully'
        ], 200);

    }

}
