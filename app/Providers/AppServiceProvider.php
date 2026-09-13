<?php

namespace App\Providers;

use App\Services\NoteVersionService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(NoteVersionService::class, fn () => new NoteVersionService(
            percentLoss: (int) config('versions.substantial_change.percent_loss'),
            minCharsLoss: (int) config('versions.substantial_change.min_chars_loss'),
            minCharsForPercent: (int) config('versions.substantial_change.min_chars_for_percent'),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
