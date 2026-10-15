<?php

use Illuminate\Support\Facades\Route;
use Modules\Settings\Http\Controllers\Admin\SettingsController;

/*
| Loaded under /{admin_prefix}/settings with the names "admin.settings.*" and the middleware in
| config('cms.admin_middleware'). settings.view to look, settings.edit to save.
*/

Route::get('/', [SettingsController::class, 'index'])->name('index');
Route::get('/{section}', [SettingsController::class, 'show'])->name('show');
Route::put('/{section}', [SettingsController::class, 'update'])->name('update');
