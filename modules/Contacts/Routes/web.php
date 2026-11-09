<?php

use Illuminate\Support\Facades\Route;
use Modules\Contacts\Http\Controllers\ContactController;

/*
| The public contacts page, with the web middleware. Other languages get their URL prefix
| (/ru/contacts) from the CMS kernel, so these routes are defined once. At most 5 messages a
| minute from one address.
*/

Route::controller(ContactController::class)->prefix('contacts')->name('contacts.')->group(function () {
    Route::get('/', 'index')->name('index');
    Route::post('/', 'send')->middleware('throttle:5,1')->name('send');
});
