<?php

use Illuminate\Support\Facades\Route;
use Modules\Services\Http\Controllers\ServiceController;

/*
| The public services, with the web middleware. Other languages get their URL prefix
| (/ru/services/…) from the CMS kernel, so these routes are defined once.
*/

Route::controller(ServiceController::class)->prefix('services')->name('services.')->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('/{slug}', 'show')->name('show');
});
