<?php

namespace App\Http\Controllers\Admin;

use App\Cms\Modules\Module;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Contracts\View\View;

/**
 * The admin dashboard at /{admin_prefix}. A placeholder with a few counts; modules will add
 * their own dashboard widgets later.
 */
class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('admin.dashboard', [
            'stats' => [
                'users' => User::count(),
                'activeUsers' => User::where('is_active', true)->count(),
                'modulesEnabled' => Module::active()->count(),
                'modulesInstalled' => Module::installed()->count(),
                'modulesAvailable' => Module::where('is_missing', false)->count(),
            ],
            'recentLogins' => User::whereNotNull('last_login_at')->latest('last_login_at')->limit(5)->get(),
        ]);
    }
}
