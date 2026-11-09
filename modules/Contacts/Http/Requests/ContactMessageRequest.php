<?php

namespace Modules\Contacts\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A visitor's message from the contacts page. "website" is a hidden honeypot field that people leave
 * empty. Errors go to the "contact" error bag and back to the form (#contact-form).
 */
class ContactMessageRequest extends FormRequest
{
    protected $errorBag = 'contact';

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
            'phone' => ['nullable', 'string', 'max:40', 'regex:/^[\d\s()+\-.]+$/'],
            'subject' => ['nullable', 'string', 'max:160'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
            'website' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => __('contacts::front.form.name'),
            'email' => __('contacts::front.form.email'),
            'phone' => __('contacts::front.form.phone'),
            'subject' => __('contacts::front.form.subject'),
            'message' => __('contacts::front.form.message'),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return route('contacts.index').'#contact-form';
    }

    /**
     * The validated data, ready for the model.
     *
     * @return array<string, mixed>
     */
    public function modelAttributes(): array
    {
        $optional = fn (string $key) => filled($this->validated($key)) ? trim((string) $this->validated($key)) : null;

        return [
            'name' => trim((string) $this->validated('name')),
            'email' => (string) $this->validated('email'),
            'phone' => $optional('phone'),
            'subject' => $optional('subject'),
            'message' => trim((string) $this->validated('message')),
            'locale' => app()->getLocale(),
        ];
    }
}
