<?php

namespace Modules\Modules\Http\Controllers\Admin;

use App\Cms\Modules\Exceptions\ModuleException;
use App\Cms\Modules\Module;
use App\Cms\Modules\ModuleManager;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Lists the modules and runs the lifecycle actions through ModuleManager, which enforces the
 * rules (dependencies, core protection); a refused action comes back as an error message.
 */
class ModuleController extends Controller
{
    /** Action => permission. Uninstalling deletes data, so it has its own permission. */
    private const PERMISSIONS = [
        'install' => 'modules.manage',
        'enable' => 'modules.manage',
        'disable' => 'modules.manage',
        'upgrade' => 'modules.manage',
        'uninstall' => 'modules.uninstall',
    ];

    public function __construct(private ModuleManager $modules) {}

    public function index(): View
    {
        Gate::authorize('modules.view');

        $manifests = $this->modules->discover();
        $rows = Module::ordered()->get();
        $statuses = $rows->mapWithKeys(fn (Module $module) => [$module->slug => $this->status($module)]);

        return view('modules::admin.index', [
            'modules' => $rows->map(function (Module $module) use ($manifests, $rows) {
                $manifest = $manifests->get($module->slug);

                return [
                    'module' => $module,
                    'status' => $this->status($module),
                    'description' => $manifest?->description(),
                    'icon' => $manifest?->adminMenu()['icon'] ?? null,
                    'requires' => $manifest?->requires() ?? [],
                    'requiredBy' => $manifests->filter(fn ($other) => in_array($module->slug, $other->requires(), true)
                        && $rows->firstWhere('slug', $other->slug)?->isInstalled())->keys()->all(),
                    'actions' => array_values(array_filter(
                        $this->modules->allowedActions($module),
                        fn (string $action) => Gate::allows(self::PERMISSIONS[$action]),
                    )),
                ];
            }),
            'statuses' => $statuses,
            // Not "errors": that name is the view's validation error bag.
            'manifestErrors' => $this->modules->errors(),
            'needsSync' => $this->modules->needsSync(),
            'canManage' => Gate::allows('modules.manage'),
        ]);
    }

    public function sync(): RedirectResponse
    {
        Gate::authorize('modules.manage');

        $result = $this->modules->sync();

        $parts = [];
        foreach (['added', 'updated', 'restored', 'missing'] as $kind) {
            if ($result->{$kind} !== []) {
                $parts[] = __("modules::admin.sync.{$kind}", ['modules' => implode(', ', $result->{$kind})]);
            }
        }

        $redirect = redirect()->route('admin.modules.index')
            ->with('success', $parts === [] ? __('modules::admin.sync.none') : implode(' ', $parts));

        return $result->hasErrors()
            ? $redirect->with('warning', __('modules::admin.sync.invalid', ['count' => count($result->invalid)]))
            : $redirect;
    }

    public function action(Module $module, string $action): RedirectResponse
    {
        abort_unless(array_key_exists($action, self::PERMISSIONS), 404);
        Gate::authorize(self::PERMISSIONS[$action]);

        try {
            $this->modules->{$action}($module->slug);
        } catch (ModuleException $e) {
            return redirect()->route('admin.modules.index')->with('error', $e->getMessage());
        }

        return redirect()->route('admin.modules.index')
            ->with('success', __("modules::admin.done.{$action}", ['name' => $module->name]));
    }

    private function status(Module $module): string
    {
        return match (true) {
            $module->is_missing => 'missing',
            ! $module->isInstalled() => 'not_installed',
            $module->enabled => 'enabled',
            default => 'disabled',
        };
    }
}
