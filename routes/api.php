<?php

use Illuminate\Support\Facades\Route;
use Mydnic\Kanpen\Http\Controllers\Api\SubscriberController;

if (config('kanpen.routes.subscribe', true)) {
    Route::post('subscriber', SubscriberController::class)
        ->middleware('throttle:kanpen-subscribe')
        ->name('store');
}
