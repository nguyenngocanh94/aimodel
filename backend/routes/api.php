<?php

use App\Http\Controllers\ArtifactController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\RunController;
use App\Http\Controllers\WorkflowController;
use Illuminate\Support\Facades\Route;

Route::get('/health', [HealthController::class, 'check']);

Route::apiResource('workflows', WorkflowController::class);

Route::post('/workflows/{workflow}/runs', [RunController::class, 'store'])->name('runs.store');
Route::get('/runs/{run}', [RunController::class, 'show'])->name('runs.show');

Route::get('/artifacts/{artifact}', [ArtifactController::class, 'show'])->name('artifacts.show');
