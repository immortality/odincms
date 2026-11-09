<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Admin URL prefix
    |--------------------------------------------------------------------------
    |
    | The dashboard is served at /{admin_prefix} and every module's admin
    | routes at /{admin_prefix}/{module-slug}.
    |
    */

    'admin_prefix' => env('CMS_ADMIN_PREFIX', 'admin'),

    /*
    |--------------------------------------------------------------------------
    | Admin access
    |--------------------------------------------------------------------------
    |
    | Middleware of every admin route (the dashboard and each module's
    | Routes/admin.php). "admin" requires an active user with admin.access.
    | Members of super_admin_role pass every permission check.
    |
    */

    'admin_middleware' => ['web', 'auth', 'verified', 'admin'],

    'super_admin_role' => 'super-admin',

    // Groups seeded by the Groups module; they cannot be deleted or renamed.
    'protected_groups' => ['super-admin', 'admin', 'user'],

    /*
    |--------------------------------------------------------------------------
    | Modules
    |--------------------------------------------------------------------------
    |
    | Each module is a folder in modules_path with a module.json manifest.
    | Core modules can't be uninstalled; see ModuleManager for which ones
    | may be disabled.
    |
    */

    'modules_path' => base_path('modules'),

    'core_modules' => [
        'users', 'groups', 'permissions', 'modules', 'settings',
        'navigation', 'widgets', 'categories', 'blog', 'comments', 'pages', 'media',
    ],

    // Core modules that may still be disabled (never uninstalled).
    'disableable_core_modules' => ['blog', 'categories', 'comments', 'pages'],

    /*
    |--------------------------------------------------------------------------
    | Admin menu groups
    |--------------------------------------------------------------------------
    |
    | Headings of the admin sidebar. A module puts itself in a group with
    | "admin_menu.group" in its module.json (default: content). Unknown
    | groups are added with a headline-case label and order 50.
    |
    */

    'admin_menu_groups' => [
        'content' => ['label' => 'Content', 'order' => 10],
        'appearance' => ['label' => 'Appearance', 'order' => 20],
        'users' => ['label' => 'Users', 'order' => 30],
        'system' => ['label' => 'System', 'order' => 90],
    ],

    'admin_menu_default_group' => 'content',

    /*
    |--------------------------------------------------------------------------
    | Localization
    |--------------------------------------------------------------------------
    */

    // Languages the admin and the CMS are translated into.
    'available_locales' => ['en', 'ru', 'ky'],

    // Active content languages and the default one. Admin → Settings overrides both at boot
    // (the active ones are a subset of available_locales).
    'locales' => ['en', 'ru', 'ky'],

    'default_locale' => 'en',

    'locale_names' => ['en' => 'English', 'ru' => 'Русский', 'ky' => 'Кыргызча'],

    // Flag icon of each locale: public/flags/{flag}.svg. A locale not listed here uses its own code.
    'locale_flags' => ['en' => 'en', 'ru' => 'ru', 'ky' => 'kg'],

    // Frontend URLs of the other active locales get a prefix: /ru/blog/…, /ky/about. Generated URLs
    // (route(), url()) get the current locale's prefix automatically, except the admin panel and
    // these first path segments.
    'unlocalized_paths' => ['storage', 'build', 'odinadmin', 'themes', 'flags', 'up', 'vendor'],

    /*
    |--------------------------------------------------------------------------
    | Frontend theme
    |--------------------------------------------------------------------------
    |
    | The "default" theme uses the OdinZero site template (html/index6.html). Its images are
    | copied from html/assets/img to public/themes/default/img; bump the version after copying
    | so browsers reload them. Its CSS and JS are copied to resources/css|js/themes/default and
    | built by Vite into theme.scss / theme.js (one CSS and one JS file, run npm run build).
    |
    */

    'theme' => 'default',

    'theme_version' => '1.0.0',

    /*
    |--------------------------------------------------------------------------
    | Editor HTML
    |--------------------------------------------------------------------------
    |
    | HTML from the editor is cleaned on save by App\Cms\Support\HtmlSanitizer: the design is kept
    | (classes, data-*, styles, inline SVG…), active content is removed. Iframes are kept only when
    | their src matches this pattern (YouTube, Vimeo and Google Maps embeds); widen it for other
    | embeds, e.g. '%^https://%' to allow any https iframe.
    |
    */

    'safe_iframe' => env('CMS_SAFE_IFRAME', '%^(https:)?//(www\.youtube(-nocookie)?\.com/embed/|player\.vimeo\.com/video/|www\.google\.com/maps/embed)%i'),

    /*
    |--------------------------------------------------------------------------
    | Admin template (OdinAdmin)
    |--------------------------------------------------------------------------
    |
    | The admin uses the OdinAdmin template. Its CSS and JS are copied from
    | odinadmin/ (the template) to public/odinadmin. Bump the version after
    | copying so browsers reload them. CMS-specific admin styles and scripts:
    | resources/css/admin.scss and resources/js/admin.js (Vite).
    |
    */

    'odinadmin_version' => '1.0.5',

    /*
    |--------------------------------------------------------------------------
    | Maps (<x-admin.form.map>, Leaflet + OpenStreetMap)
    |--------------------------------------------------------------------------
    |
    | Where the map picker opens when a record has no coordinates yet, and the
    | tile server. OpenStreetMap's tiles are free for moderate use with the
    | attribution below; set CMS_MAP_TILES to another provider for heavy use.
    |
    */

    'map' => [
        'center' => [42.8746, 74.5698], // Bishkek
        'zoom' => 12,
        'tiles' => env('CMS_MAP_TILES', 'https://tile.openstreetmap.org/{z}/{x}/{y}.png'),
        'attribution' => '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
    ],

];
