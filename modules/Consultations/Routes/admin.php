<?php

use Illuminate\Support\Facades\Route;
use Modules\Consultations\Http\Controllers\Admin\ConsultationController;

/*
| Loaded under /{admin_prefix}/consultations with the names "admin.consultations.*" and the middleware in
| config('cms.admin_middleware') (web, auth, verified, admin). Each action is authorized by ConsultationRequestPolicy.
*/

Route::controller(ConsultationController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::post('/bulk', 'bulk')->name('bulk');
    Route::get('/{consultation}', 'show')->name('show');
    Route::patch('/{consultation}/status', 'status')->name('status');
    Route::delete('/{consultation}', 'destroy')->name('destroy');
});
