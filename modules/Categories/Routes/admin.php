<?php

use Illuminate\Support\Facades\Route;
use Modules\Categories\Http\Controllers\Admin\CategoryController;

/*
| Loaded under /{admin_prefix}/categories with the names "admin.categories.*" and the middleware in
| config('cms.admin_middleware'). Permissions: categories.view|create|edit|delete.
| {type} is a category type registered by a module (CategoryTypes), e.g. "blog".
*/

Route::controller(CategoryController::class)->group(function () {
    Route::get('/', 'index')->name('index');

    Route::prefix('/{type}')->where(['type' => '[a-z][a-z0-9-]*'])->group(function () {
        Route::get('/', 'tree')->name('tree');
        Route::get('/create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::post('/reorder', 'reorder')->name('reorder');
        Route::get('/{category}/edit', 'edit')->name('edit');
        Route::put('/{category}', 'update')->name('update');
        Route::delete('/{category}', 'destroy')->name('destroy');
    });
});
