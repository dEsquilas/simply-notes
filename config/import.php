<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Evernote import limits
    |--------------------------------------------------------------------------
    |
    | Zip bomb guards checked before an uploaded export is extracted.
    |
    */

    'max_files' => (int) env('IMPORT_MAX_FILES', 20000),

    'max_uncompressed_bytes' => (int) env('IMPORT_MAX_UNCOMPRESSED_BYTES', 2 * 1024 * 1024 * 1024),

];
