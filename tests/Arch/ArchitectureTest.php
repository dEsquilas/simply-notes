<?php

arch('debugging helpers are not left in the app')
    ->expect(['dd', 'dump', 'var_dump'])
    ->not->toBeUsed();

// BUG-01: NoteController::view calls ray(), which does not exist in production (no dev dependencies) and returns a 500
it('does not call ray() in app code')->todo();

arch('controllers are suffixed with Controller')
    ->expect('App\Http\Controllers')
    ->toHaveSuffix('Controller');

arch('models extend Eloquent')
    ->expect('App\Models')
    ->toExtend('Illuminate\Database\Eloquent\Model');
