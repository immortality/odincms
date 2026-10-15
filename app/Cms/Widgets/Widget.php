<?php

namespace App\Cms\Widgets;

use App\Cms\Support\FormFields;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * A widget type (spec §9). A module lists its widget classes in module.json ("widgets"); the
 * admin places configured copies (widget instances) in the theme's widget areas.
 *
 *   class LatestPostsWidget extends Widget
 *   {
 *       public static string $key = 'blog.latest-posts';        // "{module}.{name}"
 *       public static string $name = 'blog::widgets.latest_posts';
 *
 *       public function fields(): array
 *       {
 *           return ['limit' => ['type' => 'number', 'label' => 'Limit', 'default' => 5, 'rules' => ['integer', 'min:1', 'max:20']]];
 *       }
 *
 *       public function render(): View
 *       {
 *           return view('blog::widgets.latest-posts', ['posts' => Post::latest()->take($this->setting('limit'))->get()]);
 *       }
 *   }
 *
 * Fields are defined as in App\Cms\Support\FormFields.
 */
abstract class Widget
{
    /** Unique key, "{module slug}.{name}". */
    public static string $key;

    /** Name shown in the admin (translation key or text). */
    public static string $name;

    /** Short description shown in the admin (translation key or text). */
    public static string $description = '';

    public static string $icon = 'fas fa-puzzle-piece';

    /**
     * True when the widget shows the instance title itself (e.g. as the small label above its heading):
     * area views then leave the title out.
     */
    public static bool $ownHeading = false;

    /**
     * False for widgets whose output must not be cached (e.g. a form: its CSRF token, errors and old input
     * belong to one visitor). The instance's cache time is then ignored.
     */
    public static bool $cacheable = true;

    /** The instance title in the current language, set by the renderer before render(). */
    protected ?string $title = null;

    /**
     * @param  array<string, mixed>  $settings  the instance's saved settings
     */
    public function __construct(protected array $settings = []) {}

    public function setTitle(?string $title): static
    {
        $this->title = $title;

        return $this;
    }

    /**
     * Does this instance show the title itself? $ownHeading by default; override when it depends on the
     * settings (e.g. a layout).
     */
    public function ownsHeading(): bool
    {
        return static::$ownHeading;
    }

    /**
     * The instance title in the current language ('' when there is none).
     */
    public function title(): string
    {
        return trim((string) $this->title);
    }

    /**
     * The settings form of an instance.
     *
     * @return array<string, array<string, mixed>>
     */
    public function fields(): array
    {
        return [];
    }

    abstract public function render(): View|Htmlable|string;

    /**
     * A saved setting, or the field's default.
     */
    public function setting(string $name): mixed
    {
        if (array_key_exists($name, $this->settings)) {
            return $this->settings[$name];
        }

        return FormFields::resolve($this->fields()[$name]['default'] ?? null);
    }

    /**
     * The section heading fields shared by home page widgets, each in every language: "slogan" (the big
     * heading; *words* in asterisks are highlighted), "intro" (the paragraph under it) and "detail" (free
     * formatted content after the intro, edited with the editor and purified on save). The instance title
     * is the small label above them (set $ownHeading). The widget form puts the title and these fields
     * under one set of language tabs. Help texts can be replaced.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function headingFields(?string $sloganHelp = null, ?string $introHelp = null): array
    {
        return [
            'slogan' => [
                'type' => 'translatable',
                'label' => 'Slogan',
                'help' => $sloganHelp ?? 'The big heading; the widget title is the small label above it. Wrap words in *asterisks* to highlight them.',
            ],
            'intro' => [
                'type' => 'translatable',
                'input' => 'textarea',
                'label' => 'Intro',
                'help' => $introHelp ?? 'A short paragraph under the heading.',
            ],
            'detail' => [
                'type' => 'translatable',
                'input' => 'editor',
                'label' => 'Detail',
                'help' => 'Any formatted content after the intro: text, lists, images, tables, embedded videos.',
            ],
        ];
    }

    /**
     * A translatable setting in the current language, falling back to the default language ('' when empty).
     */
    public function translated(string $name): string
    {
        $values = (array) $this->setting($name);

        return trim((string) ($values[app()->getLocale()] ?? $values[(string) config('cms.default_locale')] ?? ''));
    }

    /**
     * The view to render: the active theme's own "themes.{theme}.widgets.{module}-{name}" when it has one
     * (the markup belongs to the theme), otherwise the module's "{module}::widgets.{name}".
     */
    protected function themeView(string $module, string $name): string
    {
        $theme = 'themes.'.config('cms.theme').".widgets.{$module}-{$name}";

        return view()->exists($theme) ? $theme : "{$module}::widgets.{$name}";
    }

    /**
     * Validate settings input and return the values to store (see FormFields::values()).
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function validate(array $input): array
    {
        $fields = $this->normalizedFields();

        return FormFields::values($fields, Validator::make($input, FormFields::rules($fields), [], FormFields::attributes($fields))->validate());
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function normalizedFields(): array
    {
        return FormFields::normalize($this->fields(), static::$key);
    }

    public static function label(): string
    {
        return (string) __(static::$name);
    }
}
