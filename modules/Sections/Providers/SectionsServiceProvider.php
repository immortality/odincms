<?php

namespace Modules\Sections\Providers;

use App\Cms\Modules\ModuleServiceProvider;

/**
 * Landing page sections as widgets ("Why choose us", "Marquee", module.json). No models, routes or
 * admin screens: the sections are placed and edited in Admin → Widgets.
 */
class SectionsServiceProvider extends ModuleServiceProvider {}
