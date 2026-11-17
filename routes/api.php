<?php

use App\Http\Controllers\ApiController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:radar-api')->group(function () {
    Route::get('/catalog', [ApiController::class, 'catalog']);
    Route::post('/rules/search', [ApiController::class, 'rulesSearch']);
    Route::get('/rules/{id}', [ApiController::class, 'rule'])->whereNumber('id');
    Route::get('/plan/{token}', [ApiController::class, 'plan']);
    Route::get('/changelog', [ApiController::class, 'changelog']);
    Route::post('/hit', [ApiController::class, 'hit']);
});
Route::post('/export', [ApiController::class, 'export'])->middleware('throttle:radar-export');
