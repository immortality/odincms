<?php

// Messages of the CMS kernel (app/Cms). Module strings live in each module's Resources/lang.
return [
    'tree' => [
        'mismatch' => 'The list has changed in the meantime. Reload the page and try again.',
        'loop' => 'An item cannot be placed below itself or one of its own items.',
        'too_deep' => 'Maximum depth: :levels levels.',
    ],

    // Admin → Settings → Images
    'images' => [
        'section' => 'Images',
        'size_help' => 'Width x height in pixels, e.g. 1200x800. Images are cropped to these proportions. Changing it re-creates the existing images.',
    ],

    // <x-admin.form.cropped-image>
    'image' => [
        'drop' => 'Drag and drop an image here',
        'or' => 'or',
        'select' => 'Select',
        'change' => 'Change',
        'recrop' => 'Edit crop',
        'remove' => 'Remove',
        'size_note' => 'Cropped to :size px. JPG, PNG, WebP or GIF, up to :max MB.',
        'crop_title' => 'Crop the image',
        'crop_hint' => 'Drag the frame or the image, and zoom with the mouse wheel.',
        'zoom_in' => 'Zoom in',
        'zoom_out' => 'Zoom out',
        'reset' => 'Reset',
        'apply' => 'Apply',
        'cancel' => 'Cancel',
        'too_small' => 'The chosen area is :area px, smaller than :size px: the image may look blurry.',
        'not_image' => 'Choose an image file (JPG, PNG, WebP or GIF).',
        'too_large' => 'The file is too large: at most :max MB.',
        'removed' => 'The image will be removed when you save.',
        'undo' => 'Undo',
    ],
];
