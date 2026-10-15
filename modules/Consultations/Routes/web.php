<?php

use Illuminate\Support\Facades\Route;
use Modules\Consultations\Http\Controllers\ConsultationController;

// The form of the "Request a free consultation" widget. At most 5 requests a minute from one address.
Route::post('/consultation-requests', [ConsultationController::class, 'store'])
    ->middleware('throttle:5,1')
    ->name('consultations.store');
