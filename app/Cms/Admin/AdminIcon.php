<?php

namespace App\Cms\Admin;

/**
 * Admin icons are Font Awesome classes ("fas fa-newspaper"): module manifests ("admin_menu.icon"),
 * settings sections and widget types give them, and they are returned as they are.
 *
 * Modules written for the old DashLite admin used icon names like "ni-article"; those are still
 * mapped (MAP), and any other "ni-…" name gets the default icon, so older modules keep an icon.
 *
 *   AdminIcon::fa('fas fa-newspaper')   // 'fas fa-newspaper'
 *   AdminIcon::fa('ni-article')         // 'fas fa-newspaper'
 */
class AdminIcon
{
    public const DEFAULT = 'fas fa-puzzle-piece';

    /** @var array<string, string> old DashLite icon name → Font Awesome classes */
    public const MAP = [
        'ni-dashboard' => 'fas fa-th-large',
        'ni-article' => 'fas fa-newspaper',
        'ni-folders' => 'fas fa-folder-tree',
        'ni-file-docs' => 'fas fa-file-alt',
        'ni-briefcase' => 'fas fa-concierge-bell',
        'ni-menu-alt-left' => 'fas fa-compass',
        'ni-grid-add-c' => 'fas fa-puzzle-piece',
        'ni-user-list' => 'fas fa-user-friends',
        'ni-users' => 'fas fa-users-cog',
        'ni-shield-check' => 'fas fa-shield-alt',
        'ni-puzzle' => 'fas fa-cubes',
        'ni-setting' => 'fas fa-sliders-h',
        'ni-globe' => 'fas fa-globe',
        'ni-mail' => 'far fa-envelope',
        'ni-img' => 'far fa-image',
        'ni-alert' => 'fas fa-exclamation-triangle',
        // Widget types (Widget::$icon)
        'ni-template' => 'fas fa-puzzle-piece',
        'ni-text-rich' => 'fas fa-code',
    ];

    public static function fa(?string $icon, string $default = self::DEFAULT): string
    {
        if ($icon === null || $icon === '') {
            return $default;
        }

        return str_starts_with($icon, 'ni-') ? (self::MAP[$icon] ?? $default) : $icon;
    }
}
