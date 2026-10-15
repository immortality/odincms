<?php

namespace Modules\Consultations\Providers;

use App\Cms\Modules\ModuleServiceProvider;
use App\Cms\Settings\Settings;
use Illuminate\Support\Facades\Event;
use Modules\Consultations\Events\ConsultationRequested;
use Modules\Consultations\Listeners\NotifyTeam;

/**
 * Consultation requests: the form widget (module.json), the public POST route, the admin list
 * (/admin/consultations), the e-mail to the team and its address in Settings.
 */
class ConsultationsServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        $this->app->make(Settings::class)->section('consultations', 'consultations::admin.settings.section', [
            'notify_email' => [
                'type' => 'email',
                'label' => 'consultations::admin.settings.notify_email',
                'help' => 'consultations::admin.settings.notify_email_help',
            ],
        ], module: 'consultations', icon: 'fas fa-inbox', order: 75);
    }

    public function boot(): void
    {
        parent::boot();

        Event::listen(ConsultationRequested::class, NotifyTeam::class);
    }
}
