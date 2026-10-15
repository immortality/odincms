<?php

use Illuminate\Support\Facades\Route;
use Modules\Banners\Http\Controllers\Admin\BannerController;

/*
| Loaded under /{admin_prefix}/banners with the names "admin.banners.*" and the middleware in
| config('cms.admin_middleware') (web, auth, verified, admin). Each action is authorized by BannerPolicy.
*/

Route::controller(BannerController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('/create', 'create')->name('create');
    Route::post('/', 'store')->name('store');
    Route::post('/bulk', 'bulk')->name('bulk');
    Route::get('/{item}/edit', 'edit')->name('edit');
    Route::put('/{item}', 'update')->name('update');
    Route::delete('/{item}', 'destroy')->name('destroy');
});
