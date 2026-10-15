@php
    $title = $contact->exists ? __('contacts::admin.edit') : __('contacts::admin.new');
@endphp

<x-admin-layout::app
    :title="$title"
    :breadcrumbs="[
        __('Dashboard') => route('admin.dashboard'),
        __('contacts::admin.title') => route('admin.contacts.index'),
        $title => null,
    ]">

    {{-- Back, Delete and Save in the page head, above the form. --}}
    <x-slot:actions>
        <a href="{{ route('admin.contacts.index') }}" class="btn-soft">
            <i class="fas fa-arrow-left"></i> {{ __('contacts::admin.back') }}
        </a>
        @if ($contact->exists)
            @can('delete', $contact)
                {{-- Its own small form, outside the edit form below (forms cannot be nested). --}}
                <x-admin.confirm-delete :action="route('admin.contacts.destroy', $contact)"
                    :message="__('contacts::admin.delete_confirm', ['title' => $contact->title])" />
            @endcan
        @endif
        {{-- Submits the form below through its id. --}}
        <button type="submit" form="contact-form" class="btn-brand">
            <i class="fas fa-check"></i> {{ __('contacts::admin.save') }}
        </button>
    </x-slot:actions>

    <form method="POST" id="contact-form" action="{{ $contact->exists ? route('admin.contacts.update', $contact) : route('admin.contacts.store') }}" enctype="multipart/form-data">
        @csrf
        @if ($contact->exists)
            @method('PUT')
        @endif

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card card-auto">
                    <div class="card-body">
                        {{-- One tab per language, each with the title, the address and the city. --}}
                        <x-admin.form.translatable-group :fields="[
                            ['name' => 'title', 'label' => __('contacts::admin.fields.title'), 'value' => $contact->getTranslations('title'), 'required' => true],
                            ['name' => 'address', 'label' => __('contacts::admin.fields.address'), 'value' => $contact->getTranslations('address')],
                            ['name' => 'city', 'label' => __('contacts::admin.fields.city'), 'value' => $contact->getTranslations('city')],
                        ]" />

                        <div class="row g-3">
                            <div class="col-md-6">
                                <x-admin.form.input name="phone" type="tel" :label="__('contacts::admin.fields.phone')" :value="$contact->phone"
                                    icon="fas fa-phone" placeholder="+996 555 123 456" autocomplete="off" />
                            </div>
                            <div class="col-md-6">
                                <x-admin.form.input name="email" type="email" :label="__('contacts::admin.fields.email')" :value="$contact->email"
                                    icon="far fa-envelope" placeholder="office@example.com" autocomplete="off" />
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card card-auto mt-4">
                    <div class="card-header">
                        <span>{{ __('contacts::admin.location') }}</span>
                        @if ($map = $contact->mapUrl())
                            <a href="{{ $map }}" target="_blank" rel="noopener" class="btn-ghost" style="font-size:.72rem;padding:4px 10px;">
                                {{ __('contacts::admin.open_map') }} <i class="fas fa-external-link-alt"></i>
                            </a>
                        @endif
                    </div>
                    <div class="card-body">
                        <x-admin.form.map :label="__('contacts::admin.fields.coordinates')"
                            :latitude="$contact->latitude" :longitude="$contact->longitude" :help="__('contacts::admin.map_help')" />
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card card-auto">
                    <div class="card-body">
                        <x-admin.form.select name="status" :label="__('contacts::admin.fields.status')" :options="__('contacts::admin.statuses')"
                            :value="$contact->status" required />
                        <x-admin.form.input name="slug" :label="__('contacts::admin.fields.slug')" :value="$contact->slug"
                            :help="__('contacts::admin.slug_help')" />
                    </div>
                </div>

                <div class="card card-auto mt-4">
                    <div class="card-body">
                        <x-admin.form.cropped-image name="photo" :model="$contact" :label="__('contacts::admin.fields.photo')" />
                    </div>
                </div>

                @if ($contact->exists)
                    <div class="card card-auto mt-4">
                        <div class="card-body">
                            <div class="sum-row">
                                <div class="sum-ico"><i class="far fa-calendar-plus"></i></div>
                                <span class="sum-label">{{ __('contacts::admin.fields.created_at') }}</span>
                                <span class="sum-val">{{ $contact->created_at?->format('d.m.Y H:i') }}</span>
                            </div>
                            <div class="sum-row">
                                <div class="sum-ico"><i class="far fa-clock"></i></div>
                                <span class="sum-label">{{ __('contacts::admin.fields.updated_at') }}</span>
                                <span class="sum-val">{{ $contact->updated_at?->format('d.m.Y H:i') }}</span>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </form>
</x-admin-layout::app>
