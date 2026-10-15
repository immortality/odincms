<?php

use App\Cms\Media\CroppedImageInput;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

/**
 * Step 8.2: forms, uploads and headers.
 */
it('puts a CSRF token in every POST form', function () {
    $files = File::allFiles(resource_path('views'));
    foreach (File::glob(base_path('modules/*/Resources/views')) as $moduleViews) {
        $files = [...$files, ...File::allFiles($moduleViews)];
    }
    $checked = 0;
    $missing = [];

    foreach ($files as $file) {
        preg_match_all('/<form\b[^>]*method="post"[^>]*>(.*?)<\/form>/is', $file->getContents(), $forms);

        foreach ($forms[1] as $body) {
            $checked++;
            if (! str_contains($body, '@csrf')) {
                $missing[] = $file->getRelativePathname();
            }
        }
    }

    expect($checked)->toBeGreaterThan(20)
        ->and(array_values(array_unique($missing)))->toBe([]);
});

it('never nests forms (the inner one would submit the outer one)', function () {
    $files = File::allFiles(resource_path('views'));
    foreach (File::glob(base_path('modules/*/Resources/views')) as $moduleViews) {
        $files = [...$files, ...File::allFiles($moduleViews)];
    }
    $nested = [];

    foreach ($files as $file) {
        preg_match_all('/<form\b.*?<\/form>/is', $file->getContents(), $forms);

        foreach ($forms[0] as $form) {
            // Components that render a form of their own count too.
            if (preg_match('/<form\b|<x-admin\.confirm-delete|<x-admin\.bulk-actions/i', substr($form, 5))) {
                $nested[] = $file->getRelativePathname();
            }
        }
    }

    expect($nested)->toBe([]);
});

it('limits every upload by type and size', function () {
    $uploads = [];
    $missing = [];

    // The shared rules of cropped images (<x-admin.form.cropped-image>).
    $cropped = implode('|', CroppedImageInput::rules('photo')['photo']);
    expect($cropped)->toContain('mimes:', 'max:');

    foreach (File::glob(base_path('modules/*/Http/Requests/*.php')) as $file) {
        preg_match_all("/CroppedImageInput::rules\('(\w+)'/", File::get($file), $croppedFields);
        foreach ($croppedFields[1] as $field) {
            $uploads[] = basename($file).': '.$field;
        }

        preg_match_all("/'(\w+)' => \[[^\]]*'(?:image|file)'[^\]]*\]/", File::get($file), $rules, PREG_SET_ORDER);

        foreach ($rules as [$rule, $field]) {
            $uploads[] = basename($file).': '.$field;
            if (! str_contains($rule, 'mimes:') || ! str_contains($rule, 'max:')) {
                $missing[] = basename($file).': '.$field;
            }
        }
    }

    // The avatar (user form, profile) and the blog cover.
    expect($uploads)->toContain('UserRequest.php: avatar', 'ProfileRequest.php: avatar', 'PostRequest.php: cover', 'ServiceRequest.php: photo')
        ->and($missing)->toBe([]);
});

it('sends security headers', function () {
    $this->get('/')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeaderMissing('Strict-Transport-Security'); // only on HTTPS in production
});

it('sends HSTS on HTTPS in production', function () {
    app()->detectEnvironment(fn () => 'production');

    $response = (new SecurityHeaders)->handle(
        Request::create('https://example.com/'),
        fn () => response('ok'),
    );

    expect($response->headers->get('Strict-Transport-Security'))->toBe('max-age=31536000; includeSubDomains');
});

it('keeps the configuration cacheable (php artisan optimize)', function () {
    $file = storage_path('framework/testing/config-'.uniqid().'.php');
    File::ensureDirectoryExists(dirname($file));
    File::put($file, '<?php return '.var_export(config()->all(), true).';'.PHP_EOL);

    // config:cache does the same: closures in config would not survive this round trip.
    expect(require $file)->toBeArray();

    File::delete($file);
});
