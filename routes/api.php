<?php

use App\Http\Controllers\Api\ApiController;
use Illuminate\Support\Facades\Route;

Route::post('/flip', [ApiController::class, 'flip'])
    ->middleware('throttle:flip')
    ->name('api.flip');
