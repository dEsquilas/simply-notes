<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Session start snapshots
    |--------------------------------------------------------------------------
    |
    | When a note is edited after this many minutes of inactivity (its last
    | save is older than this), the state before the edit is snapshotted.
    |
    */
    'inactivity_minutes' => (int) env('NOTE_VERSIONS_INACTIVITY_MINUTES', 5),

    /*
    |--------------------------------------------------------------------------
    | Substantial change snapshots
    |--------------------------------------------------------------------------
    |
    | An edit is "substantial" (and its previous state is pinned) when the
    | plain text loses more than percent_loss% of its length (but only once
    | at least min_chars_for_percent characters are actually lost, so a tiny
    | edit to a short note never counts just because the percentage is high),
    | or more than min_chars_loss characters, or the note loses images or
    | tables.
    |
    */
    'substantial_change' => [
        'percent_loss' => (int) env('NOTE_VERSIONS_PERCENT_LOSS', 20),
        'min_chars_for_percent' => (int) env('NOTE_VERSIONS_MIN_CHARS_FOR_PERCENT', 100),
        'min_chars_loss' => (int) env('NOTE_VERSIONS_MIN_CHARS_LOSS', 500),
    ],

    /*
    |--------------------------------------------------------------------------
    | Retention (versions:prune)
    |--------------------------------------------------------------------------
    |
    | Versions younger than keep_all_days are never pruned. Between
    | keep_all_days and weekly_after_months, only the newest version per
    | calendar day (per note) is kept. Beyond weekly_after_months, only the
    | newest version per ISO week (per note) is kept. Pinned versions are
    | never deleted, regardless of age.
    |
    */
    'retention' => [
        'keep_all_days' => (int) env('NOTE_VERSIONS_KEEP_ALL_DAYS', 7),
        'weekly_after_months' => (int) env('NOTE_VERSIONS_WEEKLY_AFTER_MONTHS', 3),
    ],

];
