<?php

namespace App\Http\Controllers;

use App\Models\Notebook;
use Illuminate\Http\Request;
use Inertia\Inertia;

class NotebookController extends Controller
{
    public function index(){

        $notebooks = Notebook::where('owner', auth()->id())->get();

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

}
