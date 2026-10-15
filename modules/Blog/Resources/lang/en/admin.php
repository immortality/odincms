<?php

return [
    'menu' => 'Blog',
    'title' => 'Blog posts',
    'description' => ':count posts in total.',
    'linkable' => 'Blog post',
    'image_label' => 'Blog cover',
    'category_type' => 'Blog',
    'categories' => 'Categories',
    'create' => 'New post',
    'edit' => 'Edit post',
    'view' => 'View on site',
    'preview' => 'Preview',
    'save' => 'Save',
    'back' => 'Back',
    'search' => 'Search posts',
    'empty' => 'No posts found.',
    'all_statuses' => 'All statuses',
    'all_categories' => 'All categories',
    'no_category' => '— No category —',
    'mine' => 'Mine',
    'seo' => 'Search engines (SEO)',
    'updated' => 'Last saved :date',
    'delete_confirm' => 'Delete the post ":title"?',

    'excerpt_help' => 'A short summary for the blog list. Leave empty to use the beginning of the text.',
    'slug_help' => 'The address: /blog/{slug}. Leave empty to make it from the title.',
    'published_at_help' => 'Empty: now. A future date schedules the post.',
    'meta_title_help' => 'The browser tab and search result title. Leave empty to use the post title.',
    'meta_description_help' => 'The search result text (about 160 characters). Leave empty to use the summary.',
    'author_help' => 'An editor publishes your post. Until then you can edit your drafts.',

    'saved' => 'The post has been saved.',
    'deleted' => 'The post has been moved to the trash.',
    'bulk_done' => '{0} No posts changed.|{1} One post changed.|[2,*] :count posts changed.',
    'bulk_skipped' => 'Skipped (not allowed): :titles',

    'statuses' => [
        'draft' => 'Draft',
        'published' => 'Published',
    ],

    'filter_statuses' => [
        'draft' => 'Draft',
        'published' => 'Published',
        'scheduled' => 'Scheduled',
    ],

    'actions' => [
        'publish' => 'Publish',
        'unpublish' => 'Unpublish',
        'delete' => 'Delete',
    ],

    'fields' => [
        'title' => 'Title',
        'excerpt' => 'Summary',
        'body' => 'Text',
        'slug' => 'Slug',
        'category' => 'Category',
        'status' => 'Status',
        'published_at' => 'Publish date',
        'author' => 'Author',
        'cover' => 'Cover image',
        'meta_title' => 'Meta title',
        'meta_description' => 'Meta description',
    ],

    'errors' => [
        'cannot_publish' => 'You may save drafts only; an editor publishes posts.',
    ],

    'settings' => [
        'section' => 'Blog',
        'per_page' => 'Posts per page',
    ],
];
