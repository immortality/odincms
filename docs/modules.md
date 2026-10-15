# Writing a module

A module is a folder in `modules/` with a `module.json` manifest. The CMS discovers it ("Sync" in
**Admin → Modules**, or `php artisan cms:modules:sync`), installs it (migrations, seeder,
permissions) and loads it only while it is enabled. A disabled module's routes, menu items, widgets,
settings sections and link targets disappear; its data stays until it is uninstalled.

The quickest start is the generator, which writes a working CRUD module with a test:

```bash
php artisan cms:make:module Tours
php artisan cms:modules:sync
php artisan cms:module:install tours      # then open /admin/tours
```

The Blog, Pages and Categories modules are complete examples of everything below.

## Folder structure

```
modules/Tours/                      namespace Modules\Tours (PSR-4: Modules\ → modules/)
├── module.json
├── Providers/ToursServiceProvider.php
├── Routes/admin.php                /admin/tours/…, names admin.tours.*
├── Routes/web.php                  public routes
├── Http/Controllers/Admin/…  Http/Requests/…
├── Models/…  Policies/…  Widgets/…  Events/…
├── Database/Migrations/…  Database/Seeders/ToursDatabaseSeeder.php  Database/Factories/…
├── Resources/views/…               view('tours::admin.index')
├── Resources/lang/{en,ru,ky}/…     __('tours::admin.title')
└── Tests/Feature/…                 run by `composer test`
```

## module.json

```json
{
    "name": "Tours",
    "slug": "tours",
    "version": "1.0.0",
    "description": { "en": "Tours and departures", "ru": "Туры и выезды", "ky": "Турлар" },
    "core": false,
    "provider": "Modules\\Tours\\Providers\\ToursServiceProvider",
    "requires": ["categories"],
    "admin_menu": {
        "group": "content",
        "icon": "fas fa-map",
        "order": 40,
        "items": [
            { "label": "tours::admin.menu", "route": "admin.tours.index", "permission": "tours.view" }
        ]
    },
    "permissions": ["tours.view", "tours.create", "tours.edit", "tours.delete"],
    "widgets": ["Modules\\Tours\\Widgets\\FeaturedToursWidget"]
}
```

| Key | Meaning |
|---|---|
| `name`, `description` | Shown in Admin → Modules. `description` is text or translations by locale. |
| `slug` | Lowercase kebab-case, unique. Used in URLs (`/admin/tours`), route names (`admin.tours.*`), view and translation namespaces (`tours::`). |
| `version` | Semantic version. Raising it offers "Upgrade", which runs the module's new migrations. |
| `core` | Core modules cannot be uninstalled (only those in `cms.disableable_core_modules` can be disabled). Leave `false`. |
| `provider` | The service provider, a subclass of `App\Cms\Modules\ModuleServiceProvider`. |
| `requires` | Slugs of modules that must be installed and enabled first. They cannot be disabled while this module needs them. Only import classes from modules you require. |
| `admin_menu` | Sidebar entry: `group` (`content`, `appearance`, `users`, `system` or a new one), `icon` (Font Awesome classes, e.g. `fas fa-map`), `order`, and `items` (label translation key, route name, permission). An item without permission is shown to every admin. Items whose route does not exist are skipped. |
| `permissions` | Created on install, removed on uninstall, listed in Admin → Permissions. Name them `{slug}.{action}`. |
| `widgets` | Widget classes the module offers (see Widgets). |
| `images` | Cropped images and their sizes: `{ "photo": { "label": "…", "width": 1200, "height": 800, "sizes": { "thumb": [600, 400] } } }` (see Images). |

## The provider

```php
namespace Modules\Tours\Providers;

use App\Cms\Modules\ModuleServiceProvider;
use Modules\Tours\Models\Tour;
use Modules\Tours\Policies\TourPolicy;

class ToursServiceProvider extends ModuleServiceProvider
{
    protected array $policies = [Tour::class => TourPolicy::class];

    public function register(): void
    {
        // bindings; settings sections (see Settings)
    }

    public function boot(): void
    {
        parent::boot();   // required: views, translations, migrations, routes, policies, widgets

        // things that use other modules (they are all registered by now): link targets, category types
    }
}
```

`ModuleServiceProvider` loads `Resources/views` and `Resources/lang` as `tours::`, the migrations,
`Routes/admin.php` (prefix `/{admin_prefix}/tours`, names `admin.tours.`, middleware
`web, auth, verified, admin`) and `Routes/web.php` (middleware `web`). Override `$adminMiddleware` or
`$webMiddleware` to change the middleware.

## Routes

Admin routes (`Routes/admin.php`) are relative to `/admin/tours`:

```php
Route::controller(TourController::class)->group(function () {
    Route::get('/', 'index')->name('index');               // admin.tours.index
    Route::get('/create', 'create')->name('create');
    Route::post('/', 'store')->name('store');
    Route::post('/bulk', 'bulk')->name('bulk');
    Route::get('/{tour}/edit', 'edit')->name('edit');
    Route::put('/{tour}', 'update')->name('update');
    Route::delete('/{tour}', 'destroy')->name('destroy');
});
```

Public routes (`Routes/web.php`) are defined once; the CMS serves them in every language
(`/tours`, `/ru/tours`) and `route()` adds the current language's prefix by itself. Don't add
`{locale}` parameters. URLs that no route matches are tried as Pages last.

## Permissions

Every admin route must check a permission. `tests/Feature/Cms/AdminRoutesProtectedTest.php` calls every
`admin.*` route as a user without permissions and fails unless the answer is 403.

- In controllers: `Gate::authorize('tours.view')` or a policy (`Gate::authorize('update', $tour)`).
- In form requests: check in `authorize()` (it runs before validation, so users without permission
  get 403, not validation errors).
- Bulk actions: check up front (`abort_unless(Gate::any(['tours.edit', 'tours.delete']), 403)`), then
  per record.
- In views: `@can('tours.create') … @endcan`.
- Record-level rules go in policies (e.g. `PostPolicy`: authors edit only their own drafts).
- Members of the `super-admin` group pass every check.

Optional labels for the Permissions screen: `Resources/lang/{locale}/permissions.php` with
`'view' => 'View tours'`, … (keys are the part after `tours.`).

## Admin views

Use the admin layout and components (OdinAdmin; reference pages in `../odinadmin/*.html`, Font Awesome icons):

```blade
<x-admin-layout::app :title="__('tours::admin.title')" :breadcrumbs="[__('Dashboard') => route('admin.dashboard'), __('tours::admin.title') => null]">
    <x-slot:actions> … buttons (btn-brand / btn-soft) … </x-slot:actions>
    …
</x-admin-layout::app>
```

| Component | Use |
|---|---|
| `x-admin.table`, `x-admin.table.check`, `x-admin.filters`, `x-admin.bulk-actions`, `x-admin.pagination` | lists with search, filters and bulk actions |
| `x-admin.tree` | drag-and-drop trees; save the order with `App\Cms\Support\TreeOrder::save()` |
| `x-admin.form.input`, `.textarea`, `.select`, `.checkbox`, `.image`, `.editor` (TinyMCE) | form fields with labels and errors |
| `x-admin.form.translatable` | one tab per language around one field: posts `title[en]`, `title[ru]`, … |
| `x-admin.form.translatable-group` | one tab per language holding several fields (title + detail, meta title + description); see `changelogs.md` |
| `x-admin.form.cropped-image` | drag-and-drop image with a crop modal in the module's proportions (see Images) |
| `x-admin.form.map` | a point on a map (Leaflet + OpenStreetMap): posts `latitude` / `longitude`; start view in `config('cms.map')`; see the Contacts module |
| `x-admin.confirm-delete` | delete button with confirmation |

Every POST form needs `@csrf` (checked by `HardeningTest`). Uploads need `mimes:` and `max:` rules.

## Models

Translatable columns are JSON (`$table->json('title')`) with `spatie/laravel-translatable`
(`#[Translatable(['title', 'body'])]`). The content traits in `App\Cms\Support\Concerns`:

| Trait | Gives |
|---|---|
| `HasSlug` | a unique `slug` made from the default-language title; override `slugScope()` to make it unique per group |
| `HasStatus` | `draft` / `published`, `published()` scope; return a column from `publishedAtColumn()` for scheduling |
| `Sortable` | `sort_order` (new rows last), `ordered()` scope, `reorder($ids)`; `sortScope()` for siblings |
| `Searchable` | `->search($text, ['title', 'slug'])`: case-insensitive, in every language |
| `PurifiesHtml` | cleans editor HTML on save: `protected function purifiedAttributes(): array { return ['body']; }` |

Eager-load relations (`with()`): outside production, lazy loading throws.

## Images (upload and crop)

Admins drop an image, crop it in a modal to the module's proportions, and can re-crop it later.
The original is kept; the versions are made from the crop area as WebP.

1. Declare the size in `module.json` (`width` × `height` is the main version, `large`):

   ```json
   "images": { "photo": { "label": "tours::admin.image_label", "width": 1200, "height": 800, "sizes": { "thumb": [600, 400] } } }
   ```

2. The model implements `App\Cms\Media\Contracts\CroppedImages` with the trait:

   ```php
   class Tour extends Model implements CroppedImages
   {
       use HasCroppedImages;   // App\Cms\Media\Concerns\HasCroppedImages (includes InteractsWithMedia)

       public function croppedImages(): array { return ['photo' => 'tours.photo']; }
   }
   ```

3. Form request: `...CroppedImageInput::rules('photo')`. Controller: `CroppedImageInput::save($request, $tour, 'photo')`.
4. Form (with `enctype="multipart/form-data"`):
   `<x-admin.form.cropped-image name="photo" :model="$tour" :label="__('tours::admin.fields.photo')" />`
5. Views: `$tour->imageUrl('photo', 'thumb')` / `'large'`.

Admins can change the size in **Admin → Settings → Images**; existing images are then re-created.

## Settings

A module adds its own section to Admin → Settings from `register()`:

```php
$this->app->make(\App\Cms\Settings\Settings::class)->section('tours', 'tours::admin.settings.section', [
    'per_page' => ['type' => 'number', 'label' => 'tours::admin.settings.per_page', 'default' => 12, 'rules' => ['required', 'integer', 'min:1']],
], module: 'tours', icon: 'fas fa-map', order: 70);
```

Read with `Settings::get('tours.per_page')` (facade `App\Cms\Support\Facades\Settings`) or
`Settings::translated('…')` for a translatable field. Field types: `text`, `email`, `number`,
`textarea`, `editor`, `select`, `multiselect`, `checkbox`, `translatable` (`input`: `input`, `textarea`
or `editor`). Options may be closures. Editor HTML is purified.

## Widgets

```php
namespace Modules\Tours\Widgets;

use App\Cms\Widgets\Widget;
use Illuminate\Contracts\View\View;

class FeaturedToursWidget extends Widget
{
    public static string $key = 'tours.featured';                 // {module}.{name}
    public static string $name = 'tours::widgets.featured.name';  // translation key
    public static string $description = 'tours::widgets.featured.description';
    public static string $icon = 'fas fa-map';

    public function fields(): array      // the same field types as Settings
    {
        return ['limit' => ['type' => 'number', 'label' => 'tours::widgets.featured.limit', 'default' => 3, 'rules' => ['integer', 'min:1', 'max:12']]];
    }

    public function render(): View
    {
        return view('tours::widgets.featured', ['tours' => Tour::published()->take((int) $this->setting('limit'))->get()]);
    }
}
```

List the class in `module.json` → `widgets`. Admins place it in theme areas (Admin → Widgets); its
output is cached per language. When the module is uninstalled, its placed widgets are deleted.

## Menu link targets (Linkable)

So that menus can link to a tour (and follow its slug changes), implement `App\Cms\Support\Linkable`
(`linkUrl()`, `linkLabel()`, static `linkableSearch($query)`) and register the model in `boot()`:

```php
$this->app->make(\App\Cms\Support\LinkableRegistry::class)->register(Tour::class, 'tours::admin.linkable');
```

## Categories

Declare `"requires": ["categories"]`, register a type in `boot()`, and add a nullable
`category_id` foreign key to `categories`:

```php
$this->app->make(\Modules\Categories\Support\CategoryTypes::class)
    ->register('tours', 'tours::admin.categories', fn (Category $category) => route('tours.category', $category->slug));
```

The type then has its own tree in Admin → Categories (`Category::options('tours')` for selects).

## Events

Fire events for things other modules may react to (e.g. `Modules\Blog\Events\PostPublished`), and
listen to other modules' events instead of calling their code. The kernel fires `ModuleInstalled`,
`ModuleEnabled`, `ModuleDisabled`, `ModuleUninstalled` and `ModuleUpgraded` (`App\Cms\Modules\Events`).

## Install, upgrade and uninstall

- **Install** runs the migrations in `Database/Migrations`, then `Database/Seeders/{Folder}DatabaseSeeder`
  if it exists (default data: menus, widget areas, …), creates the permissions and enables the module.
- **Upgrade**: add a new migration, raise `version` in `module.json`, Sync, then Upgrade.
- **Uninstall** rolls back the module's migrations (`down()` must drop everything `up()` created).

## Translations

Put every UI text in `Resources/lang/{en,ru,ky}/*.php` and use `__('tours::file.key')`.
`TranslationsTest` fails if a key in `en` is missing in another language.

## Tests

Module tests live in `Tests/Feature` and use the helpers of `tests/TestCase.php`:

```php
beforeEach(fn () => $this->useModule('tours'));   // mark installed + enabled, load it

it('lists tours', function () {
    Tour::factory()->create(['title' => ['en' => 'Song-Kul']]);

    $this->actingAsAdmin()->get(route('admin.tours.index'))->assertOk()->assertSee('Song-Kul');
    $this->actingAsUserWith(['tours.view'])->get(route('admin.tours.index'))->assertOk();
    $this->actingAsUserWith([])->get(route('admin.tours.index'))->assertForbidden();
});
```

Every module's migrations run in the test database, so `useModule()` only marks it installed.
Run everything with `composer test`.
