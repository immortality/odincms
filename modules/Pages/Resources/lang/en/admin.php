<?php

return [
    'menu' => 'Pages',
    'title' => 'Pages',
    'description' => 'Static pages. Drag them in the tree to change the order or nesting; a subpage\'s address starts with its parent\'s.',
    'linkable' => 'Page',
    'create' => 'New page',
    'edit' => 'Edit page',
    'add_child' => 'Add a subpage',
    'view' => 'View on site',
    'preview' => 'Preview',
    'save' => 'Save',
    'back' => 'Back',
    'drag' => 'Drag to move',
    'search' => 'Search pages',
    'empty' => 'No pages yet.',
    'not_found' => 'No pages found.',
    'top_level' => '— Top level —',
    'seo' => 'Search engines (SEO)',
    'image_label' => 'Page image',
    'select_hint' => 'Select a page in the tree to see its details.',
    'tree_hint' => 'Drag the pages to reorder or nest them; the order is saved right away.',
    'delete_confirm' => 'Delete the page ":title"?',
    'slug_help' => 'Part of the address. Leave empty to make it from the title.',
    'address' => 'Address: :path',
    'layout_help' => 'The page layouts of the theme (resources/views/themes/{theme}/pages).',
    'meta_title_help' => 'The browser tab and search result title. Leave empty to use the page title.',
    'meta_description_help' => 'The search result text (about 160 characters). Leave empty to use the beginning of the text.',

    'saved' => 'The page has been saved.',
    'deleted' => 'The page has been moved to the trash.',
    'order_saved' => 'The order has been saved.',

    'statuses' => [
        'draft' => 'Draft',
        'published' => 'Published',
    ],

    'layouts' => [
        'default' => 'Default',
        'full-width' => 'Full width',
        'sidebar' => 'With sidebar',
    ],

    'fields' => [
        'title' => 'Title',
        'image' => 'Image',
        'address' => 'Address',
        'body' => 'Text',
        'slug' => 'Slug',
        'parent' => 'Parent page',
        'layout' => 'Layout',
        'status' => 'Status',
        'meta_title' => 'Meta title',
        'meta_description' => 'Meta description',
    ],

    'errors' => [
        'parent_loop' => 'A page cannot be placed below itself or one of its subpages.',
        'has_children' => 'This page has subpages. Move or delete them first.',
        'slug_taken' => 'Another page at this level already has this slug.',
        'slug_reserved' => 'This address is used by the site (for example /blog or /admin). Choose another slug or a parent page.',
        'reorder_slug_taken' => 'Two pages with the same slug cannot be on the same level.',
        'reorder_slug_reserved' => 'A top-level page cannot be called ":slug": the address is used by the site.',
    ],
];
