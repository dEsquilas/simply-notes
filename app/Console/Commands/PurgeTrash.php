<?php

namespace App\Console\Commands;

use App\Models\ImportJob;
use App\Models\Note;
use App\Models\Notebook;
use Illuminate\Console\Command;

class PurgeTrash extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'trash:purge';

    /**
     * The console command description.
     */
    protected $description = 'Permanently delete notes and notebooks that have been in the trash longer than the configured retention period';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $cutoff = now()->subDays(config('trash.retention_days'));

        Notebook::onlyTrashed()->where('deleted_at', '<', $cutoff)->get()->each(function (Notebook $notebook) {
            $notebook->notes()->withTrashed()->forceDelete();
            ImportJob::where('notebook_id', $notebook->id)->delete();
            $notebook->forceDelete();
        });

        // Notes trashed on their own, past retention, whose notebook is still active
        Note::onlyTrashed()->where('deleted_at', '<', $cutoff)->forceDelete();

        $this->info('Trash purged.');

        return self::SUCCESS;
    }
}
