<?php

use Illuminate\Support\Facades\Route;
use Mydnic\Kanpen\Http\Controllers\SubscriberController;
use Mydnic\Kanpen\Http\Controllers\TrackingController;
use Mydnic\Kanpen\Http\Controllers\UnsubscribeController;
use Mydnic\Kanpen\Http\Controllers\VerifySubscriberController;

Route::post('subscriber', [SubscriberController::class, 'store'])->name('store');
Route::get('unsubscribe/{token}', UnsubscribeController::class)->name('unsubscribe');
Route::get('verify/{id}/{hash}', VerifySubscriberController::class)->name('verify');

// Tracking routes — must be unauthenticated
Route::get('tracking/open/{token}', [TrackingController::class, 'open'])->name('tracking.open');
Route::get('tracking/click/{token}', [TrackingController::class, 'click'])->name('tracking.click');
