<?php

namespace Modules\Navigation\Http\Requests;

use App\Cms\Support\Linkable;
use App\Cms\Support\LinkableRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Modules\Groups\Models\Group;
use Modules\Navigation\Models\NavigationGroup;
use Modules\Navigation\Models\NavigationLink;
use Modules\Navigation\Support\RouteOptions;
use Throwable;

/**
 * Create or update a link. What is required depends on the type: model, path, route or url.
 * Route parameters are typed as "key=value" lines.
 */
class LinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can($this->link() ? 'navigation.edit' : 'navigation.create');
    }

    public function group(): NavigationGroup
    {
        /** @var NavigationGroup */
        return $this->route('group');
    }

    public function link(): ?NavigationLink
    {
        $link = $this->route('link');

        return $link instanceof NavigationLink ? $link : null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'route_parameters' => $this->parseParameters((string) $this->input('route_parameters_text', '')),
            // "/contact", " contact " → "contact"; "/" (the home page) stays "/".
            // An empty field (null after ConvertEmptyStringsToNull) is the home page.
            'path' => trim(trim((string) $this->input('path')), '/') ?: '/',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $type = $this->input('type');
        $default = config('cms.default_locale');

        return [
            'type' => ['required', Rule::in(NavigationLink::TYPES)],
            'title' => ['nullable', 'array'],
            // A model link may borrow the model's own title.
            "title.{$default}" => [Rule::requiredIf($type !== 'model'), 'nullable', 'string', 'max:255'],
            'title.*' => ['nullable', 'string', 'max:255'],
            // The form posts the fields of every type; only the chosen type's fields are validated.
            'url' => ['exclude_unless:type,url', 'required', 'string', 'max:2048', 'regex:/^(https?:\/\/|\/|#|mailto:|tel:)/i'],
            // An address on this site: "contact", "about-us/team", "catalog?sort=new", "faq#delivery".
            'path' => ['exclude_unless:type,path', 'required', 'string', 'max:2048', 'regex:/^(\/|[A-Za-z0-9\-._~%\/]+([?#]\S*)?)$/'],
            'route_name' => ['exclude_unless:type,route', 'required', 'string', Rule::in(array_keys(app(RouteOptions::class)->all()))],
            'route_parameters' => ['exclude_unless:type,route', 'array'],
            'linkable_type' => ['exclude_unless:type,model', 'required', 'string', Rule::in(array_keys(app(LinkableRegistry::class)->types()))],
            'linkable_id' => ['exclude_unless:type,model', 'required', 'integer'],
            'parent_id' => ['nullable', 'integer', Rule::exists('navigation_links', 'id')->where('group_id', $this->group()->id)],
            'css_class' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9 _-]*$/'],
            'open_new_tab' => ['boolean'],
            'is_active' => ['boolean'],
            'visibility' => ['array'],
            'visibility.*' => ['string', Rule::in(array_keys(self::visibilityOptions()))],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $type = $this->input('type');

            if ($type === 'route') {
                try {
                    route((string) $this->input('route_name'), (array) $this->input('route_parameters'));
                } catch (Throwable) {
                    $validator->errors()->add('route_parameters_text', __('navigation::admin.errors.route_parameters'));
                }
            }

            if ($type === 'model') {
                /** @var class-string<Model> $class */
                $class = (string) $this->input('linkable_type');
                if (! $class::query()->find($this->integer('linkable_id')) instanceof Linkable) {
                    $validator->errors()->add('linkable_id', __('navigation::admin.errors.linkable_missing'));
                }
            }

            // A link cannot be moved under itself or under one of its own children.
            $link = $this->link();
            if ($link !== null && $this->filled('parent_id') && in_array($this->integer('parent_id'), [$link->id, ...$this->descendantIds($link)], true)) {
                $validator->errors()->add('parent_id', __('navigation::admin.errors.parent_loop'));
            }
        }];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $attributes = [];
        foreach (config('cms.locales') as $locale) {
            $attributes["title.{$locale}"] = __('navigation::admin.fields.title').' ('.strtoupper($locale).')';
        }

        return $attributes + [
            'url' => __('navigation::admin.fields.url'),
            'path' => __('navigation::admin.fields.path'),
            'route_name' => __('navigation::admin.fields.route_name'),
            'linkable_type' => __('navigation::admin.fields.linkable_type'),
            'linkable_id' => __('navigation::admin.fields.linkable_id'),
            'css_class' => __('navigation::admin.fields.css_class'),
        ];
    }

    /**
     * The attributes to store: only the fields of the chosen type are kept.
     *
     * @return array<string, mixed>
     */
    public function modelAttributes(): array
    {
        $type = (string) $this->validated('type');

        return [
            'type' => $type,
            'title' => array_filter((array) $this->validated('title', []), fn ($value) => filled($value)),
            'url' => match ($type) {
                'url' => $this->validated('url'),
                'path' => $this->validated('path'),
                default => null,
            },
            'route_name' => $type === 'route' ? $this->validated('route_name') : null,
            'route_parameters' => $type === 'route' ? $this->validated('route_parameters') : null,
            'linkable_type' => $type === 'model' ? $this->validated('linkable_type') : null,
            'linkable_id' => $type === 'model' ? $this->integer('linkable_id') : null,
            'parent_id' => $this->filled('parent_id') ? $this->integer('parent_id') : null,
            'css_class' => $this->validated('css_class'),
            'open_new_tab' => $this->boolean('open_new_tab'),
            'is_active' => $this->boolean('is_active'),
            'visibility' => array_values((array) $this->validated('visibility', [])),
        ];
    }

    /**
     * "@guests", "@members" and the user groups.
     *
     * @return array<string, string>
     */
    public static function visibilityOptions(): array
    {
        $options = [
            NavigationLink::GUESTS => __('navigation::admin.visibility.guests'),
            NavigationLink::MEMBERS => __('navigation::admin.visibility.members'),
        ];

        foreach (Group::where('guard_name', 'web')->orderBy('id')->get() as $group) {
            $options[$group->name] = __('navigation::admin.visibility.group', ['group' => $group->label()]);
        }

        return $options;
    }

    /**
     * @return array<string, string>
     */
    private function parseParameters(string $text): array
    {
        $parameters = [];

        foreach (preg_split('/\R/', $text) ?: [] as $line) {
            if (trim($line) === '') {
                continue;
            }
            $parameters[trim(Str::before($line, '='))] = trim(Str::after($line, '='));
        }

        return $parameters;
    }

    /**
     * @return list<int>
     */
    private function descendantIds(NavigationLink $link): array
    {
        $ids = [];
        $parents = [$link->id];

        while ($parents !== []) {
            $children = NavigationLink::whereIn('parent_id', $parents)->pluck('id')->all();
            $ids = [...$ids, ...$children];
            $parents = $children;
        }

        return $ids;
    }
}
