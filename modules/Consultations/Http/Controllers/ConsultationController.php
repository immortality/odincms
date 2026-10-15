<?php

namespace Modules\Consultations\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Modules\Consultations\Events\ConsultationRequested;
use Modules\Consultations\Http\Requests\ConsultationFormRequest;
use Modules\Consultations\Models\ConsultationRequest;

/**
 * Receives the "Request a free consultation" form and sends the visitor back to it with a thank-you.
 */
class ConsultationController extends Controller
{
    public function store(ConsultationFormRequest $request): RedirectResponse
    {
        $consultation = ConsultationRequest::create($request->modelAttributes());

        ConsultationRequested::dispatch($consultation);

        return redirect()->to(strtok(url()->previous(), '#').'#consultation')->with('consultations.sent', true);
    }
}
