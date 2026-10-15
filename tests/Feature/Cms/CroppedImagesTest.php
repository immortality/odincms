<?php

use App\Cms\Media\ImageSizes;
use App\Cms\Modules\Exceptions\InvalidManifestException;
use App\Cms\Modules\Manifest;
use App\Cms\Settings\Settings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ViewErrorBag;
use Modules\Services\Models\Service;
use Modules\Services\Providers\ServicesServiceProvider;

/**
 * Cropped images: module.json "images", HasCroppedImages, CroppedImageInput and Settings → Images.
 * Uses the Services module ("services.photo": 1200x800, thumb 600x400).
 */
beforeEach(function () {
    Storage::fake('public');
    $this->useModule('settings');
    $this->useModule('services');
});

/**
 * [width, height] of a generated version.
 *
 * @return array{0: int, 1: int}
 */
function versionSize(Service $service, string $version): array
{
    $media = $service->fresh()->getFirstMedia('photo');
    [$width, $height] = getimagesize($media->getPath($version));

    return [$width, $height];
}

function postService(array $data, ?Service $service = null)
{
    $input = ['title' => ['en' => 'Horse riding'], 'status' => 'draft', ...$data];

    return $service
        ? test()->actingAsAdmin()->put(route('admin.services.update', $service), $input)
        : test()->actingAsAdmin()->post(route('admin.services.store'), $input);
}

it('reads the image sizes from module.json', function () {
    $spec = app(ImageSizes::class)->for('services.photo');

    expect($spec->width)->toBe(1200)
        ->and($spec->height)->toBe(800)
        ->and($spec->versions())->toBe(['large' => [1200, 800], 'thumb' => [600, 400]]);
});

it('refuses invalid image sizes in a manifest', function (array $images) {
    expect(fn () => Manifest::fromArray([
        'name' => 'Services', 'slug' => 'services', 'version' => '1.0.0',
        'provider' => ServicesServiceProvider::class, 'images' => $images,
    ], base_path('modules/Services')))->toThrow(InvalidManifestException::class);
})->with([
    'no height' => [['photo' => ['width' => 1200]]],
    'too small' => [['photo' => ['width' => 10, 'height' => 800]]],
    'bad name' => [['Photo' => ['width' => 1200, 'height' => 800]]],
    'bad size' => [['photo' => ['width' => 1200, 'height' => 800, 'sizes' => ['thumb' => [600]]]]],
    'size named large' => [['photo' => ['width' => 1200, 'height' => 800, 'sizes' => ['large' => [600, 400]]]]],
]);

it('crops a new photo to the chosen area, in every size, as WebP', function () {
    postService([
        'photo' => UploadedFile::fake()->image('wide.jpg', 1800, 1200),
        'photo_crop' => json_encode(['x' => 300.4, 'y' => 200, 'width' => 1200, 'height' => 800]),
    ])->assertSessionHasNoErrors();

    $service = Service::sole();
    $media = $service->getFirstMedia('photo');

    expect($media->getCustomProperty('crop'))->toBe(['x' => 300, 'y' => 200, 'width' => 1200, 'height' => 800])
        ->and($media->getCustomProperty('image_spec'))->toBe('services.photo')
        ->and(versionSize($service, 'large'))->toBe([1200, 800])
        ->and(versionSize($service, 'thumb'))->toBe([600, 400])
        ->and($service->photoUrl('large'))->toContain('.webp?v=');
});

it('crops the centre when no area is given', function () {
    postService(['photo' => UploadedFile::fake()->image('tall.jpg', 1000, 2000)])->assertSessionHasNoErrors();

    expect(versionSize(Service::sole(), 'large'))->toBe([1200, 800]);
});

it('re-crops the current photo without a new upload', function () {
    postService(['photo' => UploadedFile::fake()->image('a.jpg', 1500, 1000)]);
    $service = Service::sole();
    $before = $service->getFirstMedia('photo')->updated_at;
    $this->travel(5)->seconds();

    postService(['photo_crop' => json_encode(['x' => 0, 'y' => 0, 'width' => 600, 'height' => 400])], $service)
        ->assertSessionHasNoErrors();

    $media = $service->fresh()->getFirstMedia('photo');
    expect($media->getCustomProperty('crop'))->toBe(['x' => 0, 'y' => 0, 'width' => 600, 'height' => 400])
        ->and($media->updated_at->greaterThan($before))->toBeTrue()
        ->and(versionSize($service, 'large'))->toBe([1200, 800]); // upscaled from the small area
});

it('removes the photo, even if a new one was chosen before pressing Remove', function () {
    postService(['photo' => UploadedFile::fake()->image('a.jpg', 1200, 800)]);
    $service = Service::sole();

    postService(['photo' => UploadedFile::fake()->image('b.jpg', 1200, 800), 'photo_remove' => '1'], $service);

    expect($service->fresh()->getFirstMedia('photo'))->toBeNull();
});

it('refuses files that are not images, and bad crop data', function () {
    postService(['photo' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf'), 'photo_crop' => 'not json'])
        ->assertSessionHasErrors(['photo', 'photo_crop']);
});

it('lets Settings → Images change a size, re-creating the existing photos', function () {
    postService(['photo' => UploadedFile::fake()->image('a.jpg', 1500, 1000)]);
    $service = Service::sole();
    expect(versionSize($service, 'large'))->toBe([1200, 800]);

    $this->actingAsAdmin()->get(route('admin.settings.show', 'images'))->assertOk()->assertSee('Service photo');

    $this->actingAsAdmin()->put(route('admin.settings.update', 'images'), ['services-photo' => '800x800'])
        ->assertSessionHasNoErrors();

    expect(app(ImageSizes::class)->for('services.photo')->versions())->toBe(['large' => [800, 800], 'thumb' => [600, 600]])
        ->and(versionSize($service, 'large'))->toBe([800, 800])
        ->and(versionSize($service, 'thumb'))->toBe([600, 600]);

    $this->actingAsAdmin()->put(route('admin.settings.update', 'images'), ['services-photo' => '800 by 800'])
        ->assertSessionHasErrors('services-photo');
});

it('keeps the default size when the setting is not changed', function () {
    app(Settings::class)->setMany(['images.services-photo' => '1200x800']);

    expect(app(ImageSizes::class)->for('services.photo')->dimensions())->toBe('1200×800');
});

it('renders a drop zone and a crop modal with the module\'s proportions', function () {
    view()->share('errors', new ViewErrorBag);
    $html = Blade::render('<x-admin.form.cropped-image name="photo" :model="$service" label="Photo" />', ['service' => new Service]);

    expect($html)->toContain('data-cropped-image', 'data-crop-width="1200"', 'data-crop-height="800"', 'class="dz-area', 'data-modal',
        'name="photo"', 'name="photo_crop"', 'name="photo_remove"', '1200×800');
});

it('shows the current photo instead of the drop zone', function () {
    postService(['photo' => UploadedFile::fake()->image('a.jpg', 1200, 800)]);
    $service = Service::sole();

    $this->actingAsAdmin()->get(route('admin.services.edit', $service))
        ->assertOk()
        ->assertSee($service->photoUrl('large'), false)
        ->assertSee('data-original="'.$service->getFirstMedia('photo')->getUrl().'"', false);
});
