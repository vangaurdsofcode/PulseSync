<?php

use App\Http\Controllers\IncomingEventController;
use Illuminate\Support\Facades\Route;

Route::post('/v1/events', [IncomingEventController::class, 'store'])
    ->middleware('site.token')
    ->name('api.events.store');
