<?php

namespace Modules\Settings\Http\Controllers\Admin;

use App\Cms\Settings\Settings;
use App\Cms\Support\FormFields;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

/**
 * One tab per settings section (core sections from this module, others from enabled modules).
 * Saved values that change the configuration (languages, registration, ...) apply from the next request.
 */
class SettingsController extends Controller
{
    public function __construct(private Settings $settings) {}

    public function index(): RedirectResponse
    {
        Gate::authorize('settings.view');

        $first = array_key_first($this->settings->sections());
        abort_if($first === null, 404);

        return redirect()->route('admin.settings.show', $first);
    }

    public function show(string $section): View
    {
        Gate::authorize('settings.view');
        $current = $this->settings->getSection($section) ?? abort(404);

        $values = [];
        foreach (array_keys($current['fields']) as $name) {
            $values[$name] = $this->settings->get("{$section}.{$name}");
        }

        return view('settings::admin.index', [
            'sections' => $this->settings->sections(),
            'current' => $current,
            'values' => $values,
            'settings' => $this->settings,
            'canEdit' => Gate::allows('settings.edit'),
        ]);
    }

    public function update(Request $request, string $section): RedirectResponse
    {
        Gate::authorize('settings.edit');
        $current = $this->settings->getSection($section) ?? abort(404);

        $validator = Validator::make($request->all(), $this->settings->rules($section), [], FormFields::attributes($current['fields']));

        // The default language must be one of the active ones.
        if ($section === 'locales') {
            $validator->after(function ($validator) use ($request) {
                if (! in_array($request->input('default'), (array) $request->input('active', []), true)) {
                    $validator->errors()->add('default', __('settings::admin.errors.default_not_active'));
                }
            });
        }

        $this->settings->setMany($this->settings->valuesFromInput($section, $validator->validate()));

        return redirect()->route('admin.settings.show', $section)
            ->with('success', __('settings::admin.saved', ['section' => __($current['label'])]));
    }
}
