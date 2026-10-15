<?php

namespace App\Http\Middleware;

use App\Cms\Support\LocalizedUrls;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Global middleware (runs before routing): /ru/blog/news is routed as /blog/news, with "ru" kept
 * in the request attribute LocalizedUrls::ATTRIBUTE for SetLocale. Routes are defined once.
 *
 * The default locale has no prefix: /en/blog (when en is the default) redirects to /blog.
 * The admin panel is never prefixed: /ru/admin stays a 404.
 */
class LocalePrefix
{
    public function handle(Request $request, Closure $next): Response
    {
        [$first, $rest] = array_pad(explode('/', ltrim($request->getPathInfo(), '/'), 2), 2, '');

        if ($first === '' || ! in_array($first, (array) config('cms.locales'), true) || ! LocalizedUrls::localizable($rest)) {
            return $next($request);
        }

        if (! LocalizedUrls::prefixed($first)) {
            // The default locale's URLs have no prefix.
            return $request->isMethodSafe()
                ? redirect()->to($this->uri($request, '/'.$rest), 301)
                : $next($request);
        }

        $request->attributes->set(LocalizedUrls::ATTRIBUTE, $first);
        // Already here, so responses made before the web group runs (e.g. a 404 from the router) are in this language.
        app()->setLocale($first);
        $this->route($request, '/'.$rest);

        return $next($request);
    }

    /**
     * Let the request continue as if it had been made to $path.
     */
    private function route(Request $request, string $path): void
    {
        $server = $request->server->all();
        $server['REQUEST_URI'] = $this->uri($request, $path, false);

        $request->initialize(
            $request->query->all(),
            $request->request->all(),
            $request->attributes->all(),
            $request->cookies->all(),
            $request->files->all(),
            $server,
            $request->getContent(),
        );
    }

    private function uri(Request $request, string $path, bool $absolute = true): string
    {
        $query = $request->getQueryString();

        return ($absolute ? $request->getSchemeAndHttpHost() : '').$request->getBaseUrl().$path.($query !== null ? '?'.$query : '');
    }
}
