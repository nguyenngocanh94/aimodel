<?php

use App\Http\Controllers\ArtifactController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\RunController;
use App\Http\Controllers\RunStreamController;
use App\Http\Controllers\TelegramWebhookController;
use App\Http\Controllers\WorkflowController;
use Illuminate\Support\Facades\Route;

Route::get('/health', [HealthController::class, 'check']);

Route::apiResource('workflows', WorkflowController::class);

Route::get('/workflows/{workflow}/runs', [RunController::class, 'index'])->name('runs.index');
Route::post('/workflows/{workflow}/runs', [RunController::class, 'store'])->name('runs.store');
Route::get('/runs/{run}', [RunController::class, 'show'])->name('runs.show');
Route::get('/runs/{run}/stream', [RunStreamController::class, 'stream'])->name('runs.stream');
Route::post('/runs/{run}/cancel', [RunController::class, 'cancel'])->name('runs.cancel');
Route::post('/runs/{run}/review', [RunController::class, 'review'])->name('runs.review');

Route::get('/artifacts/{artifact}', [ArtifactController::class, 'show'])->name('artifacts.show');

Route::post('/telegram/webhook/{botToken}', [TelegramWebhookController::class, 'handle']);
