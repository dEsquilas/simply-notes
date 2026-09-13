<?php

namespace App\Console\Commands;

use App\Models\NoteVersion;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class PruneNoteVersions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'versions:prune';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Thins out old note versions, keeping one per day then one per ISO week; pinned versions are never removed';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dailySince = now()->subDays(config('versions.retention.keep_all_days'));
        $weeklyBefore = now()->subMonths(config('versions.retention.weekly_after_months'));

        // Between keep_all_days and weekly_after_months old: keep only the newest version per calendar day
        $this->pruneTier(
            NoteVersion::where('pinned', false)
                ->where('created_at', '<', $dailySince)
                ->where('created_at', '>=', $weeklyBefore),
            fn (NoteVersion $version) => $version->note_id.'|'.$version->created_at->format('Y-m-d')
        );

        // Older than weekly_after_months: keep only the newest version per ISO week
        $this->pruneTier(
            NoteVersion::where('pinned', false)
                ->where('created_at', '<', $weeklyBefore),
            fn (NoteVersion $version) => $version->note_id.'|'.$version->created_at->format('o-W')
        );

        return self::SUCCESS;
    }

    /**
     * Within each group (one note, one calendar day or ISO week), keeps only the newest
     * version and deletes the rest.
     *
     * Notes can carry ~7 MB of content (base64 images), and title/content are encrypted: only
     * id, note_id and created_at are selected here, and the actual deletes run as bulk queries
     * by id, in chunks, so pruning never hydrates a version's content into memory.
     */
    private function pruneTier(Builder $query, callable $groupKey): void
    {
        $idsToDelete = $query->select(['id', 'note_id', 'created_at'])
            ->orderByDesc('created_at')
            ->get()
            ->groupBy($groupKey)
            ->flatMap(fn ($versions) => $versions->slice(1)->pluck('id'));

        $idsToDelete->chunk(500)->each(
            fn ($ids) => NoteVersion::whereIn('id', $ids)->delete()
        );
    }
}
