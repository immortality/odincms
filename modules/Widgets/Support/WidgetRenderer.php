<?php

namespace Modules\Widgets\Support;

use App\Cms\Support\CmsCache;
use App\Cms\Widgets\Widget;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;
use Modules\Widgets\Models\WidgetArea;
use Modules\Widgets\Models\WidgetInstance;
use Throwable;

/**
 * Renders @widgetArea('sidebar') (spec §9): the area's active instances in order, skipping
 * widgets whose module is disabled and those whose visibility rules don't match. Each widget's
 * HTML is cached for its cache_ttl per locale; saving an instance clears the cache.
 */
class WidgetRenderer
{
    /** @var array<string, array<string, list<WidgetInstance>>> the theme's areas by cache key (see instances()) */
    private array $areas = [];

    public function __construct(private CmsCache $cache) {}

    public function render(string $slug, ?string $view = null): HtmlString
    {
        $widgets = $this->widgets($slug);

        if ($widgets === []) {
            return new HtmlString('');
        }

        return new HtmlString(view($view ?? 'widgets::area', ['widgets' => $widgets, 'slug' => $slug])->render());
    }

    /**
     * The visible widgets of an area: [['instance', 'key', 'title', 'html', 'own_heading']].
     *
     * @return list<array{instance: WidgetInstance, key: string, title: string|null, html: string, own_heading: bool}>
     */
    public function widgets(string $slug, ?Request $request = null, ?Authenticatable $user = null): array
    {
        $request ??= request();
        $user ??= auth()->user();
        $locale = app()->getLocale();

        $widgets = [];

        foreach ($this->instances($slug) as $instance) {
            if (! Visibility::allows($instance->visibility, $request, $user, $locale)) {
                continue;
            }

            $title = $instance->getTranslation('title', $locale, true) ?: null;

            $widget = $instance->makeWidget();
            if ($widget === null) {
                continue; // module disabled or uninstalled
            }

            $html = $this->html($widget, $instance, $locale, $title);
            if ($html === null) {
                continue;
            }

            $widgets[] = [
                'instance' => $instance,
                'key' => $instance->widget,
                'title' => $title,
                'html' => $html,
                // The widget shows the title itself (Widget::ownsHeading()): area views leave it out.
                'own_heading' => $widget->ownsHeading(),
            ];
        }

        return $widgets;
    }

    /**
     * The active instances of one of the theme's areas, in order. All areas of the theme are read
     * with one query and cached until the CMS cache is flushed (saving an area or an instance does
     * that); within a request they are kept in memory, so all the areas of a page cost no queries.
     * The cache holds plain attribute arrays (it does not unserialize objects, config cache.serializable_classes).
     *
     * @return list<WidgetInstance>
     */
    private function instances(string $slug): array
    {
        $theme = (string) config('cms.theme');
        $key = $this->cache->key("widgets.areas.{$theme}");

        $this->areas[$key] ??= array_map(
            fn (array $rows) => WidgetInstance::hydrate($rows)->all(),
            $this->cache->rememberForever("widgets.areas.{$theme}", fn () => WidgetArea::where('theme', $theme)
                ->with(['instances' => fn ($query) => $query->where('is_active', true)])
                ->get()
                ->mapWithKeys(fn (WidgetArea $area) => [
                    $area->slug => $area->instances->map(fn (WidgetInstance $instance) => $instance->getAttributes())->all(),
                ])
                ->all()),
        );

        return $this->areas[$key][$slug] ?? [];
    }

    /**
     * The widget's HTML (cached unless the widget is not cacheable), or null when it fails to render.
     */
    private function html(Widget $widget, WidgetInstance $instance, string $locale, ?string $title): ?string
    {
        $widget->setTitle($title);

        $render = function () use ($widget, $instance): ?string {
            try {
                $output = $widget->render();

                return match (true) {
                    $output instanceof View => $output->render(),
                    $output instanceof Htmlable => $output->toHtml(),
                    default => e($output),
                };
            } catch (Throwable $e) {
                // One broken widget must not break the page.
                Log::error("OdinCMS: widget #{$instance->id} ({$instance->widget}) failed to render: ".$e->getMessage());

                return null;
            }
        };

        if (! $instance->cache_ttl || ! $widget::$cacheable) {
            return $render();
        }

        return $this->cache->remember("widget.{$instance->id}.{$locale}", $instance->cache_ttl, $render);
    }
}
