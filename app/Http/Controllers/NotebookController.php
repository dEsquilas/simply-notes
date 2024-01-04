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
}
