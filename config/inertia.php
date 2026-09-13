<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Testing
    |--------------------------------------------------------------------------
    |
    | assertInertia() checks that the rendered page component exists. Page
    | directories are lowercase: the default js/Pages only matches on
    | case-insensitive filesystems like macOS, never on Linux CI.
    |
    */

    'testing' => [

        'ensure_pages_exist' => true,

        'page_paths' => [
            resource_path('js/pages'),
        ],

        'page_extensions' => [
            'vue',
        ],

    ],

];
