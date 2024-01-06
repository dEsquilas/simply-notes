<?php

use App\Http\Controllers\{NoteController, NotebookController, ProfileController};
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return redirect()->route('notebooks.index');
});


Route::middleware('auth')->group(function () {

    Route::get('/notebooks', [NotebookController::class, 'index'])->name('notebooks.index');
    Route::post('/notebooks/create', [NotebookController::class, 'create'])->name('notebooks.create');
    Route::get('/notebook/{notebookId}', [NoteController::class, 'index'])->name('notebook.index');

    Route::post('/notes/create/{notebookId}', [NoteController::class, 'create'])->name('note.create');
    Route::post('/notes/update/{noteId}', [NoteController::class, 'update'])->name('note.update');
    Route::post('/notes/trash/{noteId}', [NoteController::class, 'trash'])->name('note.trash');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
