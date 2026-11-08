<?php

use Illuminate\Support\Facades\Route;
use Mydnic\Kanpen\Http\Controllers\SubscriberController;
use Mydnic\Kanpen\Http\Controllers\TrackingController;
use Mydnic\Kanpen\Http\Controllers\UnsubscribeController;
use Mydnic\Kanpen\Http\Controllers\VerifySubscriberController;

Route::post('subscriber', [SubscriberController::class, 'store'])
    ->middleware('throttle:kanpen-subscribe')
    ->name('store');

// GET only confirms: mail clients and corporate link scanners follow every
// link in a message, so the deletion itself has to be behind a form post.
Route::get('unsubscribe/{token}', [UnsubscribeController::class, 'show'])->name('unsubscribe');
Route::delete('unsubscribe/{token}', [UnsubscribeController::class, 'destroy'])->name('unsubscribe.destroy');

// 'signed' is what actually validates the link built by SubscriberVerifyEmail.
Route::get('verify/{id}/{hash}', VerifySubscriberController::class)
    ->middleware('signed')
    ->name('verify');

// Tracking routes — must be unauthenticated
Route::get('tracking/open/{token}', [TrackingController::class, 'open'])->name('tracking.open');
Route::get('tracking/click/{token}', [TrackingController::class, 'click'])->name('tracking.click');
