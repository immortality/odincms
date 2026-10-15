<?php

namespace Modules\Consultations\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Modules\Consultations\Models\ConsultationRequest;

/**
 * A visitor sent the consultation form (other modules can react, e.g. a CRM sync).
 */
class ConsultationRequested
{
    use Dispatchable;

    public function __construct(public ConsultationRequest $request) {}
}
