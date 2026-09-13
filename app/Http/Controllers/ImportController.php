<?php

namespace App\Http\Controllers;

use App\Models\{
    ImportJob,
    Notebook
};
use Illuminate\Http\Request;
use Inertia\Inertia;

class ImportController extends Controller
{

    public function index(){

        $notebooks = Notebook::where('owner', \Auth::user()->id)->get();

        return Inertia::render('Import', [
            'notebooks' => $notebooks,
            ...$this->jobs(),
        ]);

    }

    public function store(Request $request)
    {

        $request->validate([
            'file' => 'required|file|mimes:zip|max:1048576', // KB: 1 GB
            'notebook' => ['required', function (string $attribute, mixed $value, \Closure $fail) {
                if (is_array($value)) {
                    $fail('The notebook field is invalid.');
                }
            }],
        ]);

        $createdNotebook = false;

        if($request->notebook != -1) {
            $notebook = Notebook::withTrashed()->find($request->notebook);

            if (!$notebook) {
                return response()->json(['error' => 'Notebook not found'], 404);
            }

            $this->authorize('update', $notebook);

            if ($notebook->trashed()) {
                return response()->json(['error' => 'Notes cannot be imported into a notebook in the trash'], 422);
            }
        }
        else{

            $request->validate([
                'notebookName' => 'required|string|max:255'
            ]);

            $notebook = new Notebook();
            $notebook->owner = \Auth::user()->id;
            $notebook->name = $request->notebookName;
            $notebook->save();
            $createdNotebook = true;
        }


        $file = $request->file('file');
        $path = $file->store('import');

        $importJob = new ImportJob();
        $importJob->user_id = \Auth::user()->id;
        $importJob->notebook_id = $notebook->id;
        $importJob->status = 'pending';
        $importJob->file_path = $path;
        $importJob->save();

        try {
            dispatch(new \App\Jobs\ImportNotes($importJob));
        } catch (\Throwable $e) {
            // Only reached with the sync queue; the job has already marked itself as failed
            report($e);

            // A notebook created only for this import would be left empty
            if ($createdNotebook) {
                $importJob->delete();
                $notebook->notes()->forceDelete();
                $notebook->forceDelete();
            }

            return response()->json(['error' => 'The file could not be imported'], 422);
        }

        return response()->json(['status' => true, 'job' => ImportJob::where('id', $importJob->id)->with('notebook')->first()]);
    }

    public function polling(Request $request)
    {
        return response()->json($this->jobs());
    }

    private function jobs(): array
    {
        $jobs = fn (array $statuses) => ImportJob::where('user_id', \Auth::user()->id)
            ->whereIn('status', $statuses)
            ->with('notebook')
            ->get();

        return [
            'runningJobs' => $jobs(['processing', 'pending']),
            'finishedJobs' => $jobs(['finished']),
            'failedJobs' => $jobs(['failed']),
        ];
    }

}
