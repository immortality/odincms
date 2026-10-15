<?php

namespace Modules\Banners\Providers;

use App\Cms\Modules\ModuleServiceProvider;

/**
 * Banners: the admin (/admin/banners) and two widgets that show banners in a widget area —
 * "Hero banner" (one) and "Banner slider" (several). Views (`banners::`), translations, migrations
 * and routes are loaded by ModuleServiceProvider.
 */
class BannersServiceProvider extends ModuleServiceProvider {}
