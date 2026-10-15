<?php

use Illuminate\Support\Facades\Route;
use Modules\Permissions\Http\Controllers\Admin\PermissionController;

/*
| Loaded under /{admin_prefix}/permissions with the names "admin.permissions.*" and the middleware
| in config('cms.admin_middleware'). Needs permissions.view to look, permissions.edit to save.
*/

Route::get('/', [PermissionController::class, 'index'])->name('index');
Route::put('/', [PermissionController::class, 'update'])->name('update');
