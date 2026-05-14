<?php

use App\Http\Controllers\ManualStampController;
use Illuminate\Support\Facades\Route;

Route::get('/', [ManualStampController::class, 'index']);
Route::post('/upload', [ManualStampController::class, 'upload']);