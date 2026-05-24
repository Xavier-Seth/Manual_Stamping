<?php

use App\Http\Controllers\ManualStampController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\StampPresetController;

Route::get('/', [ManualStampController::class, 'index']);
Route::post('/upload', [ManualStampController::class, 'upload']);

Route::get('/manual-stamping/presets', [StampPresetController::class, 'index'])
    ->name('manual.stamping.presets.index');

Route::post('/manual-stamping/presets', [StampPresetController::class, 'store'])
    ->name('manual.stamping.presets.store');

Route::put('/manual-stamping/presets/{stampPreset}', [StampPresetController::class, 'update'])
    ->name('manual.stamping.presets.update');

Route::patch('/manual-stamping/presets/{stampPreset}/set-default', [StampPresetController::class, 'setDefault'])
    ->name('manual.stamping.presets.setDefault');

Route::delete('/manual-stamping/presets/{stampPreset}', [StampPresetController::class, 'destroy'])
    ->name('manual.stamping.presets.destroy');