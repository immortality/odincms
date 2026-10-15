<?php

use Illuminate\Support\Facades\Route;
use Modules\Pages\Http\Controllers\Admin\PageController;

/*
| Loaded under /{admin_prefix}/pages with the names "admin.pages.*" and the middleware in
| config('cms.admin_middleware'). Permissions: pages.view|create|edit|delete.
*/

Route::controller(PageController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('/create', 'create')->name('create');
    Route::post('/', 'store')->name('store');
    Route::post('/reorder', 'reorder')->name('reorder');
    Route::get('/{page}/edit', 'edit')->name('edit');
    Route::put('/{page}', 'update')->name('update');
    Route::delete('/{page}', 'destroy')->name('destroy');
});
