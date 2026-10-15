<?php

use Illuminate\Support\Facades\Route;
use Modules\Blog\Http\Controllers\Admin\PostController;

/*
| Loaded under /{admin_prefix}/blog with the names "admin.blog.*" and the middleware in
| config('cms.admin_middleware'). Permissions: blog.view|create|edit|delete|publish (PostPolicy).
*/

Route::controller(PostController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('/create', 'create')->name('create');
    Route::post('/', 'store')->name('store');
    Route::post('/bulk', 'bulk')->name('bulk');
    Route::get('/{post}/edit', 'edit')->name('edit');
    Route::put('/{post}', 'update')->name('update');
    Route::delete('/{post}', 'destroy')->name('destroy');
});
