<?php

namespace Modules\Pages\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Modules\Pages\Models\Page;

/**
 * Serves a page at its path (the fallback route). Drafts are shown to users who may edit pages (a
 * preview), and are 404 for everyone else.
 */
class PageController extends Controller
{
    public function __invoke(Request $request, string $path): View
    {
        $page = Page::with('parent')->where('path', trim($path, '/'))->firstOrFail();

        $preview = ! $page->isPublished();
        abort_if($preview && ! $request->user()?->can('pages.edit'), 404);

        return view($page->view(), ['page' => $page, 'preview' => $preview]);
    }
}
