<?php

use App\Services\Watchdog;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// Hébergement mutualisé : pas de worker permanent, et proc_open est désactivé.
// Les tâches sont donc exécutées dans le processus du planificateur (Schedule::call),
// jamais dans un sous-processus (Schedule::command).
Schedule::call(function () {
    Artisan::call('queue:work', ['--stop-when-empty' => true, '--max-time' => 50, '--tries' => 3]);
})->name('radar-queue')->everyMinute()->withoutOverlapping(5);

Schedule::call(fn () => Artisan::call('queue:prune-failed', ['--hours' => 168]))->name('radar-prune')->daily();

// Veille : nouvelle version de MITRE ATT&CK, et purge des demandes d'export trop anciennes.
Schedule::call(fn () => app(Watchdog::class)->checkAttackVersion())->name('radar-attack-watch')->dailyAt('06:23');
Schedule::call(fn () => app(Watchdog::class)->purgeOld())->name('radar-retention')->dailyAt('03:41');

// Mise à jour hebdomadaire du référentiel (le paquet est reconstruit le lundi à 02:17 UTC par GitHub Actions).
if (config('radar.pack_url')) {
    Schedule::call(fn () => app(Watchdog::class)->importPack())
        ->name('radar-import')->weeklyOn(1, '04:17')->withoutOverlapping();
}
