<x-admin-layout::app
    :title="__('settings::admin.title')"
    :description="__('settings::admin.description')"
    :breadcrumbs="[__('Dashboard') => route('admin.dashboard'), __('settings::admin.title') => route('admin.settings.index'), __($current['label']) => null]">

    <div class="row g-4">
        <div class="col-lg-3">
            {{-- One page per section: links styled as the template's vertical tabs --}}
            <nav aria-label="{{ __('settings::admin.title') }}">
                <ul class="tabs-vertical">
                    @foreach ($sections as $key => $section)
                        <li>
                            <a href="{{ route('admin.settings.show', $key) }}" @class(['tab-link text-decoration-none', 'active' => $key === $current['key']])
                                @if ($key === $current['key']) aria-current="page" @endif>
                                <i class="{{ \App\Cms\Admin\AdminIcon::fa($section['icon'], 'fas fa-sliders-h') }}"></i><span>{{ __($section['label']) }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </nav>
        </div>

        <div class="col-lg-9">
            <div class="card card-auto">
                <div class="card-header">
                    <span>{{ __($current['label']) }}</span>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.settings.update', $current['key']) }}">
                        @csrf
                        @method('PUT')

                        <fieldset @disabled(! $canEdit)>
                            @foreach ($current['fields'] as $name => $field)
                                <x-admin.form.field :name="$name" :field="$field" :value="$values[$name]" />
                            @endforeach
                        </fieldset>

                        @if ($canEdit)
                            <div class="form-actions">
                                <button type="submit" class="btn-brand"><i class="fas fa-check"></i> {{ __('settings::admin.save') }}</button>
                            </div>
                        @endif
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-admin-layout::app>
