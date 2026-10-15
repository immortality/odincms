<?php

namespace Modules\Services\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Modules\Services\Models\Service;

/**
 * The public services: /services and /services/{slug} (/ru/services/… in other languages).
 */
class ServiceController extends Controller
{
    public function index(): View
    {
        return view('services::front.index', [
            'services' => Service::published()->with('media')->latest()->latest('id')->paginate(12),
        ]);
    }

    /**
     * A draft is shown to those who may edit it (a preview), and is 404 for everyone else.
     */
    public function show(Request $request, string $slug): View
    {
        $service = Service::with('media')->where('slug', $slug)->firstOrFail();

        $preview = ! $service->isPublished();
        abort_if($preview && ! ($request->user() && Gate::forUser($request->user())->allows('update', $service)), 404);

        return view('services::front.show', ['service' => $service, 'preview' => $preview]);
    }
}
