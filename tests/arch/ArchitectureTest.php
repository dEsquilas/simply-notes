<?php

// ray() only exists with dev dependencies: in production it breaks the page with a 500 (BUG-01)
arch('debugging helpers are not left in the app')
    ->expect(['dd', 'dump', 'var_dump', 'ray'])
    ->not->toBeUsed();

arch('controllers are suffixed with Controller')
    ->expect('App\Http\Controllers')
    ->toHaveSuffix('Controller');

arch('models extend Eloquent')
    ->expect('App\Models')
    ->toExtend('Illuminate\Database\Eloquent\Model');
