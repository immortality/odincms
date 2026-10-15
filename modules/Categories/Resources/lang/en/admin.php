<?php

return [
    'menu' => 'Categories',
    'title' => 'Categories',
    'description' => 'Categories of :type. Drag them to change the order or nesting.',
    'linkable' => 'Category',
    'create' => 'New category',
    'edit' => 'Edit category',
    'add_child' => 'Add a subcategory',
    'view' => 'View on site',
    'save' => 'Save',
    'back' => 'Back',
    'drag' => 'Drag to move',
    'inactive' => 'Hidden',
    'module' => 'Module',
    'top_level' => '— Top level —',
    'empty' => 'No categories yet.',
    'no_types' => 'No enabled module uses categories. Enable Blog (or another module that uses categories) in Admin → Modules.',
    'slug_help' => 'Part of the address. Leave empty to make it from the title.',
    'delete_confirm' => 'Delete the category ":title"?',

    'saved' => 'The category has been saved.',
    'deleted' => 'The category has been deleted.',
    'order_saved' => 'The order has been saved.',

    'fields' => [
        'title' => 'Title',
        'description' => 'Description',
        'slug' => 'Slug',
        'parent' => 'Parent category',
        'is_active' => 'Visible on the site',
    ],

    'errors' => [
        'parent_loop' => 'A category cannot be placed below itself or one of its subcategories.',
        'has_children' => 'This category has subcategories. Move or delete them first.',
    ],
];
