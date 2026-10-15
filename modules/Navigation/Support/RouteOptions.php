<?php

namespace Modules\Navigation\Support;

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as Router;
use Illuminate\Support\Str;

/**
 * The named frontend routes a "route" link can point to: GET routes without required parameters,
 * outside the admin panel and its login pages.
 */
class RouteOptions
{
    /** Route names of the login, password and account pages (Fortify). */
    private const EXCLUDED = ['login*', 'logout', 'register*', 'password.*', 'verification.*', 'two-factor.*', 'user-*', 'storage.*'];

    /**
     * @return array<string, string> name => "name (/uri)"
     */
    public function all(): array
    {
        $prefix = trim((string) config('cms.admin_prefix'), '/');
        $options = [];

        foreach (Router::getRoutes()->getRoutes() as $route) {
            $name = $route->getName();

            // Routes that need parameters (blog.show, pages.show, …) are linked as content instead.
            if ($name === null || ! in_array('GET', $route->methods(), true) || $route->isFallback
                || preg_match('/\{[^}?]+\}/', $route->uri()) === 1
                || Str::is(self::EXCLUDED, $name) || Str::startsWith($name, 'admin.')
                || $route->uri() === $prefix || Str::startsWith($route->uri(), [$prefix.'/', '_', 'up'])) {
                continue;
            }

            $options[$name] = $name.' (/'.ltrim($route->uri(), '/').')';
        }

        ksort($options);

        return $options;
    }

    public function has(string $name): bool
    {
        return array_key_exists($name, $this->all());
    }
}
