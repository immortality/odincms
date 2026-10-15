<?php

return [
    'menu' => 'Settings',
    'title' => 'Settings',
    'description' => 'Site-wide settings. Installed modules can add their own sections here.',
    'save' => 'Save',
    'saved' => ':section settings have been saved.',
    'sections' => [
        'site' => 'Site',
        'locales' => 'Languages',
        'mail' => 'Email',
        'users' => 'Users',
        'maintenance' => 'Maintenance',
    ],
    'fields' => [
        'site_name' => 'Site name',
        'tagline' => 'Tagline',
        'active_locales' => 'Active languages',
        'default_locale' => 'Default language',
        'from_address' => 'Sender address',
        'from_name' => 'Sender name',
        'registration' => 'Allow public registration',
        'maintenance' => 'Maintenance mode',
        'maintenance_message' => 'Message for visitors',
    ],
    'help' => [
        'tagline' => 'One sentence under the site name on the home page; also the home page description for search engines.',
        'active_locales' => 'Content can be entered in these languages, and the site is shown in them.',
        'default_locale' => 'Used when no language is chosen, and required for every content item.',
        'from_name' => 'Leave empty to use the site name.',
        'registration' => 'Visitors can create an account at /admin/register. New accounts get no admin access.',
        'maintenance' => 'Visitors see a "back soon" page. The admin panel keeps working, and admins can still see the site.',
    ],
    'errors' => [
        'default_not_active' => 'The default language must be one of the active languages.',
    ],
];
