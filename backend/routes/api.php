<?php

use App\Http\Controllers\ArtifactController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\WorkflowController;
use Illuminate\Support\Facades\Route;

Route::get('/health', [HealthController::class, 'check']);

Route::apiResource('workflows', WorkflowController::class);

Route::get('/artifacts/{artifact}', [ArtifactController::class, 'show'])->name('artifacts.show');
