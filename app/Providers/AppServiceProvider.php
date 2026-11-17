<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        // Compteurs séparés : la navigation normale ne doit pas consommer le quota de l'export.
        RateLimiter::for('radar-api', fn (Request $r) => Limit::perMinute(180)->by('api|'.$r->ip()));
        RateLimiter::for('radar-export', fn (Request $r) => Limit::perMinute(6)->by('export|'.$r->ip()));
    }
}
