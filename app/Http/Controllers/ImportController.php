<?php

namespace App\Http\Controllers;

use App\Models\{
    ImportJob,
    Note,
    Notebook
};
use Illuminate\Http\Request;
use Inertia\Inertia;

class ImportController extends Controller
{

    public function index(){


        $notebooks = Notebook::where('owner', \Auth::user()->id)->get();
        $runningJobs = ImportJob::where('user_id', \Auth::user()->id)->whereIn('status', ['processing', 'pending'])->with('notebook')->get();
        $finishedJobs = ImportJob::where('user_id', \Auth::user()->id)->where('status', 'finished')->with('notebook')->get();

        return Inertia::render('Import', [
            'notebooks' => $notebooks,
            'runningJobs' => $runningJobs,
            'finishedJobs' => $finishedJobs
        ]);

    }

    public function store(Request $request)
    {

        $request->validate([
            'file' => 'required|file',
            'notebook' => 'required'
        ]);

        if($request->notebook != -1) {
            $notebook = Notebook::find($request->notebook);

            if (!$notebook) {
                return response()->json(['error' => 'Notebook not found'], 404);
            }

            $this->authorize('update', $notebook);
        }
        else{

            $request->validate([
                'notebookName' => 'required'
            ]);

            $notebook = new Notebook();
            $notebook->owner = \Auth::user()->id;
            $notebook->name = $request->notebookName;
            $notebook->save();
        }


        $file = $request->file('file');
        $path = $file->store('import');

        $importJob = new ImportJob();
        $importJob->user_id = \Auth::user()->id;
        $importJob->notebook_id = $notebook->id;
        $importJob->status = 'pending';
        $importJob->file_path = $path;
        $importJob->save();

        dispatch(new \App\Jobs\ImportNotes($importJob));

        return response()->json(['status' => true, 'job' => ImportJob::where('id', $importJob->id)->with('notebook')->first()]);
    }

    public function polling(Request $request)
    {
        $runningJobs = ImportJob::where('user_id', \Auth::user()->id)->whereIn('status', ['processing', 'pending'])->with('notebook')->get();
        $finishedJobs = ImportJob::where('user_id', \Auth::user()->id)->where('status', 'finished')->with('notebook')->get();

        return response()->json(['runningJobs' => $runningJobs, 'finishedJobs' => $finishedJobs]);
    }

}
