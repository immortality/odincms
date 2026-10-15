<?php

return [
    'menu' => 'Modules',
    'title' => 'Modules',
    'description' => 'Every folder in modules/. Install a module to use it; disable it to hide it without losing its data.',
    'empty' => 'No modules yet. Put a module folder in modules/ and press Sync.',
    'sync_button' => 'Sync',
    'needs_sync' => 'The modules folder has changed. Press Sync to update this list.',
    'core' => 'Core',
    'available' => ':version available',
    'required_by' => 'Required by: :modules',
    'uninstall_confirm' => 'Uninstall :name?',
    'uninstall_text' => 'Its database tables and all of its data will be deleted. This cannot be undone.',
    'statuses' => [
        'enabled' => 'Enabled',
        'disabled' => 'Disabled',
        'not_installed' => 'Not installed',
        'missing' => 'Folder missing',
    ],
    'actions' => [
        'install' => 'Install',
        'upgrade' => 'Upgrade',
        'enable' => 'Enable',
        'disable' => 'Disable',
        'uninstall' => 'Uninstall',
    ],
    'done' => [
        'install' => ':name has been installed and enabled.',
        'upgrade' => ':name has been upgraded.',
        'enable' => ':name has been enabled.',
        'disable' => ':name has been disabled. Its data is kept.',
        'uninstall' => ':name has been uninstalled.',
    ],
    'sync' => [
        'none' => 'The list was already up to date.',
        'added' => 'New: :modules.',
        'updated' => 'Updated: :modules.',
        'restored' => 'Folder back: :modules.',
        'missing' => 'Folder missing: :modules.',
        'invalid' => ':count module.json file(s) could not be read; see the messages above.',
    ],
    'fields' => [
        'module' => 'Module',
        'version' => 'Version',
        'status' => 'Status',
        'requires' => 'Requires',
    ],
];
