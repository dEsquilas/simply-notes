<?php

use App\Models\Note;
use App\Models\NoteVersion;

beforeEach(function () {
    $this->note = Note::factory()->create();
});

function versionAt(Note $note, \Illuminate\Support\Carbon $createdAt, bool $pinned = false): NoteVersion
{
    return NoteVersion::factory()->for($note)->create(['created_at' => $createdAt, 'pinned' => $pinned]);
}

it('keeps every version younger than the retention window, however many per day', function () {
    $recent1 = versionAt($this->note, now()->subDays(2)->setTime(9, 0));
    $recent2 = versionAt($this->note, now()->subDays(2)->setTime(15, 0));
    $recent3 = versionAt($this->note, now()->subHours(1));

    $this->artisan('versions:prune');

    expect(NoteVersion::whereKey([$recent1->id, $recent2->id, $recent3->id])->count())->toBe(3);
});

it('keeps only the newest version per calendar day between the daily and weekly tiers', function () {
    $sameDayOlder = versionAt($this->note, now()->subDays(30)->setTime(9, 0));
    $sameDayNewer = versionAt($this->note, now()->subDays(30)->setTime(15, 0));
    $differentDay = versionAt($this->note, now()->subDays(31)->setTime(9, 0));

    $this->artisan('versions:prune');

    expect(NoteVersion::find($sameDayOlder->id))->toBeNull()
        ->and(NoteVersion::find($sameDayNewer->id))->not->toBeNull()
        ->and(NoteVersion::find($differentDay->id))->not->toBeNull();
});

it('keeps only the newest version per ISO week beyond the weekly tier', function () {
    $monday = now()->subMonths(5)->startOfWeek();

    $earlyInWeek = versionAt($this->note, $monday->copy()->addDay());
    $lateInWeek = versionAt($this->note, $monday->copy()->addDays(3));
    $nextWeek = versionAt($this->note, $monday->copy()->addWeek()->addDay());

    $this->artisan('versions:prune');

    expect(NoteVersion::find($earlyInWeek->id))->toBeNull()
        ->and(NoteVersion::find($lateInWeek->id))->not->toBeNull()
        ->and(NoteVersion::find($nextWeek->id))->not->toBeNull();
});

it('never deletes pinned versions, regardless of age or tier', function () {
    $pinnedDaily = versionAt($this->note, now()->subDays(30)->setTime(9, 0), pinned: true);
    versionAt($this->note, now()->subDays(30)->setTime(15, 0), pinned: true);
    $pinnedWeekly = versionAt($this->note, now()->subMonths(6), pinned: true);

    $this->artisan('versions:prune');

    // Both same-day pinned versions survive: pinned versions are never collapsed
    expect(NoteVersion::where('note_id', $this->note->id)->where('pinned', true)->count())->toBe(3)
        ->and(NoteVersion::find($pinnedDaily->id))->not->toBeNull()
        ->and(NoteVersion::find($pinnedWeekly->id))->not->toBeNull();
});

it('thins out each note independently', function () {
    $otherNote = Note::factory()->create();

    $keptForNote = versionAt($this->note, now()->subDays(30)->setTime(15, 0));
    versionAt($this->note, now()->subDays(30)->setTime(9, 0));
    $keptForOther = versionAt($otherNote, now()->subDays(30)->setTime(15, 0));
    versionAt($otherNote, now()->subDays(30)->setTime(9, 0));

    $this->artisan('versions:prune');

    expect(NoteVersion::count())->toBe(2)
        ->and(NoteVersion::find($keptForNote->id))->not->toBeNull()
        ->and(NoteVersion::find($keptForOther->id))->not->toBeNull();
});
