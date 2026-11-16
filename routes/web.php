<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/desinscription/{token}', function (string $token) {
    $done = preg_match('/^[A-Za-z0-9]{40}$/', $token) === 1
        && DB::table('export_requests')->where('unsubscribe_token', $token)
            ->update(['marketing_consent' => false, 'unsubscribed_at' => now()]) > 0;

    return view('unsubscribed', ['done' => $done]);
});

Route::get('/export/{token}/{file}', [\App\Http\Controllers\ApiController::class, 'download'])
    ->where('file', 'classeur|calque')->middleware('throttle:radar-export');

Route::prefix('admin')->group(function () {
    Route::get('/connexion', [\App\Http\Controllers\AdminController::class, 'loginForm']);
    Route::post('/connexion', [\App\Http\Controllers\AdminController::class, 'sendLink'])->middleware('throttle:radar-export');
    Route::get('/entrer/{token}', [\App\Http\Controllers\AdminController::class, 'enter'])->middleware('throttle:radar-export');
    Route::middleware(\App\Http\Middleware\RadarAdmin::class)->group(function () {
        Route::get('/', [\App\Http\Controllers\AdminController::class, 'dashboard']);
        Route::get('/exports.csv', [\App\Http\Controllers\AdminController::class, 'csv']);
        Route::get('/referentiel', [\App\Http\Controllers\AdminController::class, 'referential']);
        Route::post('/referentiel/import', [\App\Http\Controllers\AdminController::class, 'importNow']);
        Route::post('/exports/{id}/renvoyer', [\App\Http\Controllers\AdminController::class, 'resend'])->whereNumber('id');
        Route::post('/purge', [\App\Http\Controllers\AdminController::class, 'purge']);
        Route::get('/sources', [\App\Http\Controllers\AdminController::class, 'sources']);
        Route::post('/sources/{id}', [\App\Http\Controllers\AdminController::class, 'sourceSave'])->where('id', '[a-z0-9_]+');
        Route::post('/sources/{id}/retablir', [\App\Http\Controllers\AdminController::class, 'sourceReset'])->where('id', '[a-z0-9_]+');
        Route::get('/scenarios', [\App\Http\Controllers\AdminController::class, 'playbooks']);
        Route::post('/scenarios', [\App\Http\Controllers\AdminController::class, 'playbookSave']);
        Route::post('/scenarios/{id}/retirer', [\App\Http\Controllers\AdminController::class, 'playbookHide'])->where('id', '[a-z0-9_]+');
        Route::post('/scenarios/{id}/retablir', [\App\Http\Controllers\AdminController::class, 'playbookReset'])->where('id', '[a-z0-9_]+');
        Route::get('/regles', [\App\Http\Controllers\AdminController::class, 'rules']);
        Route::post('/regles', [\App\Http\Controllers\AdminController::class, 'ruleSave']);
        Route::post('/regles/{id}/publier', [\App\Http\Controllers\AdminController::class, 'ruleToggle'])->whereNumber('id');
        Route::post('/regles/{id}/supprimer', [\App\Http\Controllers\AdminController::class, 'ruleDelete'])->whereNumber('id');
        Route::get('/statistiques', [\App\Http\Controllers\AdminController::class, 'stats']);
        Route::post('/deconnexion', [\App\Http\Controllers\AdminController::class, 'logout']);
    });
});

// Application monopage : toutes les autres adresses renvoient la même coquille.
Route::view('/{any?}', 'app')->where('any', '^(?!api/|up$|build/).*$');
