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

}
