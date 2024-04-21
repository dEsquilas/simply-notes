<?php

use App\Http\Controllers\{
    GoogleLoginController,
    ImportController,
    NoteController,
    NotebookController,
    ProfileController
};

use App\Http\Middleware\NotebookVerifyOwnership;
use App\Http\Middleware\NoteVerifyOwnership;

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

Route::get('/google/redirect', [GoogleLoginController::class, 'redirectToGoogle'])->name('google.redirect');
Route::get('/google/callback', [GoogleLoginController::class, 'handleGoogleCallback'])->name('google.callback');


Route::middleware('auth')->group(function () {

    Route::get('/notebooks', [NotebookController::class, 'index'])->name('notebooks.index');
    Route::post('/notebooks/create', [NotebookController::class, 'create'])->name('notebooks.create');
    Route::get('/notebooks/trash', [NotebookController::class, 'trashView'])->name('notebooks.trash.view');
    Route::get('/import', [ImportController::class, 'index'])->name('import');
    Route::post('/import', [ImportController::class, 'store'])->name('import.store');
    Route::get('/import/polling', [ImportController::class, 'polling'])->name('import.polling');


    Route::group(['middleware' => [NotebookVerifyOwnership::class]], function () {
        Route::post('/notebooks/trash/delete/{notebookId}', [NotebookController::class, 'delete'])->name('notebooks.trash.delete');
        Route::post('/notebooks/trash/restore/{notebookId}', [NotebookController::class, 'restore'])->name('notebooks.trash.restore');
        Route::post('/notebooks/trash/{notebookId}', [NotebookController::class, 'trash'])->name('notebooks.trash.send');
        Route::post('/notes/create/{notebookId}', [NoteController::class, 'create'])->name('note.create');
        Route::get('/notebook/{notebookId}', [NotebookController::class, 'view'])->name('notebook.view');
    });

    Route::group(['middleware' => [NoteVerifyOwnership::class]], function () {
        Route::post('/notes/update/{noteId}', [NoteController::class, 'update'])->name('note.update');
        Route::post('/notes/trash/{noteId}', [NoteController::class, 'trash'])->name('note.trash');
        Route::get('/notes/{noteId}', [NoteController::class, 'view'])->name('note.view');
    });

    //Route::get('/import', [ImportController::class, 'import'])->name('import');

});

require __DIR__.'/auth.php';
