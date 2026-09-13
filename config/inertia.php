<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Pages
    |--------------------------------------------------------------------------
    |
    | Where the page components live. Page directories are lowercase: the
    | default js/Pages only matches on case-insensitive filesystems like
    | macOS, never on Linux CI.
    |
    */

    'pages' => [

        'ensure_pages_exist' => false,

        'paths' => [
            resource_path('js/pages'),
        ],

        'extensions' => [
            'vue',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Testing
    |--------------------------------------------------------------------------
    |
    | assertInertia() checks that the rendered page component exists.
    |
    */

    'testing' => [

        'ensure_pages_exist' => true,

    ],

];
