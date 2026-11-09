<?php

use Illuminate\Support\Facades\Route;
use Mydnic\Kanpen\Http\Controllers\Api\SubscriberController;

Route::post('subscriber', SubscriberController::class)
    ->middleware('throttle:kanpen-subscribe')
    ->name('store');
