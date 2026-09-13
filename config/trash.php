<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Trash retention
    |--------------------------------------------------------------------------
    |
    | Number of days a trashed note or notebook is kept before the daily
    | `trash:purge` command permanently deletes it.
    |
    */

    'retention_days' => (int) env('TRASH_RETENTION_DAYS', 30),

];
