<?php

namespace Modules\Consultations\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Services\Models\Service;

/**
 * A visitor's consultation request. "service" is the id of a published service (optional); "website" is a
 * hidden honeypot field that people leave empty. Errors go to the "consultation" error bag and back to the
 * form (#consultation), so they don't mix with other forms on the page.
 */
class ConsultationFormRequest extends FormRequest
{
    protected $errorBag = 'consultation';

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'service' => ['nullable', 'integer', Rule::in(Service::published()->pluck('id')->all())],
            'message' => ['nullable', 'string', 'max:2000'],
            'website' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => __('consultations::front.name'),
            'email' => __('consultations::front.email'),
            'service' => __('consultations::front.service'),
            'message' => __('consultations::front.message'),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return strtok(url()->previous(), '#').'#consultation';
    }

    /**
     * The validated data, ready for the model: the service as its title in the visitor's language.
     *
     * @return array<string, mixed>
     */
    public function modelAttributes(): array
    {
        $service = $this->filled('service') ? Service::find($this->integer('service')) : null;

        return [
            'name' => trim((string) $this->validated('name')),
            'email' => (string) $this->validated('email'),
            'service' => $service?->title,
            'message' => filled($this->validated('message')) ? trim((string) $this->validated('message')) : null,
            'locale' => app()->getLocale(),
        ];
    }
}
