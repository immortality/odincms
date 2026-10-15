<?php

namespace Modules\Navigation\Http\Controllers\Admin;

use App\Cms\Support\Linkable;
use App\Cms\Support\LinkableRegistry;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * The link picker's search: GET ?type={class}&q={text} → [{id, label, url}].
 */
class LinkableSearchController extends Controller
{
    public function __invoke(Request $request, LinkableRegistry $registry): JsonResponse
    {
        Gate::authorize('navigation.view');

        $type = $request->string('type')->toString();
        abort_unless($registry->has($type), 422);

        /** @var class-string<Linkable> $type */
        $results = $type::linkableSearch($request->string('q')->trim()->toString())
            ->take(30)
            ->map(fn (Linkable $model) => [
                'id' => $model->getKey(),
                'label' => $model->linkLabel(),
                'url' => $model->linkUrl(),
            ])
            ->values();

        return response()->json($results);
    }
}
