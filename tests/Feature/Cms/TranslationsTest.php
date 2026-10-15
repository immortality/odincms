<?php

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;

/**
 * Step 6.5: every UI string is translated into the CMS languages.
 */
const TRANSLATED_LOCALES = ['ru', 'ky'];

/**
 * The core's plain-text UI strings: __('Dashboard'), not module keys (blog::admin.title) or files (cms.tree.loop).
 *
 * @return list<string>
 */
function coreUiStrings(): array
{
    $files = [...File::allFiles(resource_path('views')), ...File::allFiles(app_path())];
    $strings = [];

    foreach ($files as $file) {
        preg_match_all("/(?:__|@lang)\\('((?:[^'\\\\]|\\\\.)+)'/", $file->getContents(), $matches);
        foreach ($matches[1] as $string) {
            if (! str_contains($string, '::') && ! preg_match('/^[a-z_]+(\.[a-z_]+)+$/', $string)) {
                $strings[] = stripslashes($string);
            }
        }
    }

    // Labels translated where they are shown: admin menu groups, the core widget.
    foreach (config('cms.admin_menu_groups') as $group) {
        $strings[] = $group['label'];
    }

    return array_values(array_unique($strings));
}

it('translates every core UI string', function (string $locale) {
    $translations = json_decode(File::get(lang_path($locale.'.json')), true);

    $missing = array_values(array_filter(coreUiStrings(), fn (string $string) => ! isset($translations[$string])));

    expect($missing)->toBe([]);
})->with(TRANSLATED_LOCALES);

it('gives every module translation file the same keys in each language', function (string $locale) {
    $missing = [];

    foreach (File::glob(base_path('modules/*/Resources/lang/en/*.php')) as $english) {
        $translated = str_replace(DIRECTORY_SEPARATOR.'en'.DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR.$locale.DIRECTORY_SEPARATOR, str_replace('/', DIRECTORY_SEPARATOR, $english));
        $relative = str_replace(base_path().DIRECTORY_SEPARATOR, '', $translated);

        if (! is_file($translated)) {
            $missing[] = $relative;

            continue;
        }

        $keys = array_keys(Arr::dot(require $english));
        $have = array_keys(Arr::dot(require $translated));
        foreach (array_diff($keys, $have) as $key) {
            $missing[] = $relative.': '.$key;
        }
    }

    expect($missing)->toBe([]);
})->with(TRANSLATED_LOCALES);

it('has the kernel messages in each language', function (string $locale) {
    expect(array_keys(Arr::dot(require lang_path($locale.'/cms.php'))))
        ->toEqualCanonicalizing(array_keys(Arr::dot(require lang_path('en/cms.php'))));
})->with(TRANSLATED_LOCALES);
