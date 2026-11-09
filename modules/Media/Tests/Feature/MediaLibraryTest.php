<?php

use App\Cms\Modules\Module;
use App\Cms\Modules\ModuleManager;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Media\Models\MediaFile;
use Modules\Media\Models\MediaFolder;
use Modules\Media\Support\Uploader;

beforeEach(function () {
    Storage::fake('public');

    $this->useModule('media');
});

/**
 * A file in the library, stored the way an upload stores it.
 */
function libraryFile(UploadedFile $file, ?MediaFolder $folder = null): MediaFile
{
    return app(Uploader::class)->store($file, $folder, null);
}

it('is a core module that cannot be disabled or uninstalled', function () {
    $module = Module::where('slug', 'media')->sole();

    expect($module->is_core)->toBeTrue()
        ->and(app(ModuleManager::class)->allowedActions($module))->not->toContain('disable', 'uninstall');
});

it('sends guests to the login page and forbids users without permission', function () {
    $this->get(route('admin.media.index'))->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create())->get(route('admin.media.index'))->assertForbidden();
    $this->actingAsUserWith(['media.view'])->get(route('admin.media.index'))->assertOk()
        ->assertDontSee('data-upload-url', false);

    $this->actingAsUserWith(['media.view'])
        ->postJson(route('admin.media.files.store'), ['file' => UploadedFile::fake()->image('a.jpg')])
        ->assertForbidden();
});

it('uploads a file into a folder with a readable, unique stored name', function () {
    $folder = MediaFolder::create(['name' => 'Photos']);

    $response = $this->actingAsAdmin()->postJson(route('admin.media.files.store'), [
        'file' => UploadedFile::fake()->image('Team Photo.JPG', 640, 480),
        'folder_id' => $folder->id,
    ]);

    $response->assertCreated()->assertJsonStructure(['id', 'html']);
    expect($response->json('html'))->toContain('Team Photo.jpg')->toContain('class="fm-file violet"');

    $file = MediaFile::sole();
    expect($file->folder_id)->toBe($folder->id)
        ->and($file->name)->toBe('Team Photo.jpg')
        ->and($file->extension)->toBe('jpg')
        ->and($file->path)->toMatch('#^media/\d{4}/\d{2}/team-photo-[a-z0-9]{6}\.jpg$#')
        ->and([$file->width, $file->height])->toBe([640, 480])
        ->and($file->uploaded_by)->not->toBeNull();
    Storage::disk('public')->assertExists($file->path);
});

it('accepts documents and refuses types that can carry scripts', function () {
    $this->actingAsAdmin()
        ->postJson(route('admin.media.files.store'), ['file' => UploadedFile::fake()->create('price list.pdf', 20, 'application/pdf')])
        ->assertCreated();
    expect(MediaFile::sole()->kind())->toBe('pdf');

    foreach ([['shell.php', 'application/x-php'], ['logo.svg', 'image/svg+xml'], ['page.html', 'text/html'], ['fake.jpg', 'application/x-php']] as [$name, $mime]) {
        $this->actingAsAdmin()
            ->postJson(route('admin.media.files.store'), ['file' => UploadedFile::fake()->create($name, 1, $mime)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');
    }

    expect(MediaFile::count())->toBe(1);
});

it('lists a folder, searches all folders and filters by kind', function () {
    $folder = MediaFolder::create(['name' => 'Press kit']);
    MediaFolder::create(['name' => 'Logos', 'parent_id' => $folder->id]);
    libraryFile(UploadedFile::fake()->image('cover.jpg'));
    libraryFile(UploadedFile::fake()->create('brochure.pdf', 10, 'application/pdf'), $folder);

    $this->actingAsAdmin()->get(route('admin.media.index'))->assertOk()
        ->assertSee('cover.jpg')->assertDontSee('brochure.pdf')->assertSee('Press kit')
        ->assertSee('data-media-library', false)->assertSee('odinadmin/js/file-manager.js', false);

    $this->actingAsAdmin()->get(route('admin.media.index', ['folder' => $folder->id]))->assertOk()
        ->assertSee('brochure.pdf')->assertDontSee('cover.jpg')->assertSee('Logos');

    $this->actingAsAdmin()->get(route('admin.media.index', ['search' => 'broch']))->assertOk()
        ->assertSee('brochure.pdf')->assertDontSee('cover.jpg');

    $this->actingAsAdmin()->get(route('admin.media.index', ['recent' => 1, 'kind' => 'image']))->assertOk()
        ->assertSee('cover.jpg')->assertDontSee('brochure.pdf');
});

it('renames a file but keeps its extension and address', function () {
    $file = libraryFile(UploadedFile::fake()->image('old.png'));
    $path = $file->path;

    $this->actingAsAdmin()->patchJson(route('admin.media.files.update', $file), ['name' => 'New name.jpg'])
        ->assertOk()->assertJson(['name' => 'New name.jpg.png']);
    $this->actingAsAdmin()->patchJson(route('admin.media.files.update', $file), ['name' => 'Hero.PNG'])
        ->assertOk()->assertJson(['name' => 'Hero.png']);
    $this->actingAsAdmin()->patchJson(route('admin.media.files.update', $file), ['name' => '../etc/x'])
        ->assertUnprocessable();

    expect($file->fresh()->path)->toBe($path);
});

it('deletes a file from the disk', function () {
    $file = libraryFile(UploadedFile::fake()->image('gone.jpg'));

    $this->actingAsUserWith(['media.view', 'media.edit'])->deleteJson(route('admin.media.files.destroy', $file))->assertForbidden();
    $this->actingAsAdmin()->deleteJson(route('admin.media.files.destroy', $file))->assertOk();

    expect(MediaFile::count())->toBe(0);
    Storage::disk('public')->assertMissing($file->path);
});

it('moves and deletes checked files', function () {
    $folder = MediaFolder::create(['name' => 'Archive']);
    $a = libraryFile(UploadedFile::fake()->image('a.jpg'));
    $b = libraryFile(UploadedFile::fake()->image('b.jpg'));

    $this->actingAsAdmin()->post(route('admin.media.files.bulk'), ['action' => 'move', 'ids' => [$a->id, $b->id], 'folder_id' => $folder->id])
        ->assertRedirect()->assertSessionHas('success');
    expect(MediaFile::where('folder_id', $folder->id)->count())->toBe(2);

    $this->actingAsUserWith(['media.view', 'media.edit'])
        ->post(route('admin.media.files.bulk'), ['action' => 'delete', 'ids' => [$a->id]])->assertForbidden();

    $this->actingAsAdmin()->post(route('admin.media.files.bulk'), ['action' => 'delete', 'ids' => [$a->id, $b->id]])->assertRedirect();
    expect(MediaFile::count())->toBe(0);
    Storage::disk('public')->assertMissing($a->path);
});

it('creates, renames and deletes folders; names are unique per parent; only empty ones go', function () {
    $this->actingAsAdmin()->post(route('admin.media.folders.store'), ['name' => 'Photos'])->assertRedirect();
    $photos = MediaFolder::where('name', 'Photos')->sole();

    $this->actingAsAdmin()->post(route('admin.media.folders.store'), ['name' => 'Photos'])
        ->assertSessionHasErrorsIn('folder', 'name');
    $this->actingAsAdmin()->post(route('admin.media.folders.store'), ['name' => 'Photos', 'parent_id' => $photos->id])
        ->assertSessionHasNoErrors();
    $this->actingAsAdmin()->post(route('admin.media.folders.store'), ['name' => 'a/b'])
        ->assertSessionHasErrorsIn('folder', 'name');

    $this->actingAsAdmin()->patch(route('admin.media.folders.update', $photos), ['name' => 'Pictures'])->assertRedirect();
    expect($photos->fresh()->name)->toBe('Pictures');

    // Not empty: it has a subfolder.
    $this->actingAsAdmin()->delete(route('admin.media.folders.destroy', $photos))->assertSessionHas('error');
    expect(MediaFolder::count())->toBe(2);

    $child = MediaFolder::where('parent_id', $photos->id)->sole();
    $this->actingAsAdmin()->delete(route('admin.media.folders.destroy', $child))
        ->assertRedirect(route('admin.media.index', ['folder' => $photos->id]));
    $this->actingAsAdmin()->delete(route('admin.media.folders.destroy', $photos))->assertRedirect(route('admin.media.index'));
    expect(MediaFolder::count())->toBe(0);
});

it('builds the folder tree with depth and trail', function () {
    $a = MediaFolder::create(['name' => 'B-root']);
    MediaFolder::create(['name' => 'Child', 'parent_id' => $a->id]);
    MediaFolder::create(['name' => 'A-root']);

    expect(MediaFolder::tree()->map(fn ($f) => [$f->trail, $f->depth])->all())
        ->toBe([['A-root', 0], ['B-root', 0], ['B-root / Child', 1]]);
});
