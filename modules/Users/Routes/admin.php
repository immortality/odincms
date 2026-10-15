<?php

use Illuminate\Support\Facades\Route;
use Modules\Users\Http\Controllers\Admin\ProfileController;
use Modules\Users\Http\Controllers\Admin\UserController;

/*
| Loaded under /{admin_prefix}/users with the names "admin.users.*" and the middleware in
| config('cms.admin_middleware'). User actions are authorized by UserPolicy; the profile is
| available to every admin user.
*/

Route::controller(ProfileController::class)->prefix('profile')->name('profile')->group(function () {
    Route::get('/', 'show');
    Route::put('/', 'update')->name('.update');
    Route::put('/password', 'password')->name('.password');
});

Route::controller(UserController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('/create', 'create')->name('create');
    Route::post('/', 'store')->name('store');
    Route::post('/bulk', 'bulk')->name('bulk');
    Route::get('/{user}/edit', 'edit')->name('edit');
    Route::put('/{user}', 'update')->name('update');
    Route::delete('/{user}', 'destroy')->name('destroy');
    Route::post('/{user}/reset-link', 'sendResetLink')->name('reset-link');
});
