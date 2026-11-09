{{--
    The contacts page: the page hero, the message form (error bag "contact"; after sending, a thank-you
    replaces it), the published contacts beside it and a map of the first one with coordinates.
    $contacts, $mapContact (Contact|null), $heading (words in *asterisks* get the highlight), $intro.
--}}
@extends('themes.'.config('cms.theme').'.layouts.app')

@php
    $formErrors = ($errors ?? new \Illuminate\Support\ViewErrorBag)->getBag('contact');
    $sent = session('contacts.sent');
    $primary = $contacts->first();
    $headingHtml = preg_replace('/\*([^*]+)\*/', '<span class="hl">$1</span>', e($heading));
    $icons = ['email' => 'bi-envelope', 'phone' => 'bi-telephone', 'address' => 'bi-geo-alt'];

    // An OpenStreetMap embed around the point, with a marker.
    if ($mapContact) {
        [$lat, $lng] = [$mapContact->latitude, $mapContact->longitude];
        $mapEmbed = 'https://www.openstreetmap.org/export/embed.html?'.http_build_query([
            'bbox' => implode(',', [$lng - 0.008, $lat - 0.004, $lng + 0.008, $lat + 0.004]),
            'layer' => 'mapnik',
            'marker' => $lat.','.$lng,
        ]);
    }

    // A text field of the form: label, input and its error.
    $field = fn (string $name) => [
        'id' => 'contact-'.$name,
        'invalid' => $formErrors->has($name),
        'error' => $formErrors->first($name),
    ];
@endphp

@section('title', __('contacts::front.title'))
@section('meta_description', \Illuminate\Support\Str::limit($intro, 160))

@section('content')
    <main>
        <section class="page-hero">
            <div class="container">
                <ol class="crumbs reveal">
                    <li><a href="{{ url('/') }}">{{ __('contacts::front.home') }}</a></li>
                    <li aria-current="page">{{ __('contacts::front.title') }}</li>
                </ol>
                <h1 class="reveal" data-delay="1">{!! $headingHtml !!}</h1>
                @if (filled($intro))
                    <p class="lead reveal" data-delay="2">{{ $intro }}</p>
                @endif
            </div>
        </section>

        <section class="section contact-page">
            <div class="container">
                <div class="row g-4 g-lg-5">
                    <div class="col-lg-7">
                        <div class="contact-card reveal" id="contact-form">
                            @if ($sent)
                                <div class="contact-sent" role="status">
                                    <div class="contact-sent-icon"><i class="bi bi-check-lg"></i></div>
                                    <h2>{{ __('contacts::front.sent_title') }}</h2>
                                    <p>{{ __('contacts::front.sent_text') }}</p>
                                    <a href="{{ route('contacts.index') }}#contact-form" class="link-arrow">{{ __('contacts::front.send_another') }} <i class="bi bi-arrow-right"></i></a>
                                </div>
                            @else
                                <span class="eyebrow">{{ __('contacts::front.form_eyebrow') }}</span>
                                <h2>{{ __('contacts::front.form_title') }}</h2>
                                <p>{{ __('contacts::front.form_intro') }}</p>

                                <form method="POST" action="{{ route('contacts.send') }}" novalidate>
                                    @csrf

                                    {{-- Honeypot: hidden from people, filled in by bots. --}}
                                    <div class="contact-hp" aria-hidden="true">
                                        <label for="contact-website">Website</label>
                                        <input type="text" id="contact-website" name="website" tabindex="-1" autocomplete="off">
                                    </div>

                                    <div class="row g-3">
                                        @foreach (['name' => ['text', 'name', 120, true], 'email' => ['email', 'email', 255, true], 'phone' => ['tel', 'tel', 40, false], 'subject' => ['text', 'off', 160, false]] as $name => [$type, $autocomplete, $max, $required])
                                            @php($f = $field($name))
                                            <div class="col-md-6">
                                                <label class="form-label" for="{{ $f['id'] }}">
                                                    {{ __("contacts::front.form.{$name}") }}
                                                    @unless ($required)<span class="opt">{{ __('contacts::front.form.optional') }}</span>@endunless
                                                </label>
                                                <input type="{{ $type }}" id="{{ $f['id'] }}" name="{{ $name }}" value="{{ old($name) }}" maxlength="{{ $max }}"
                                                       autocomplete="{{ $autocomplete }}" @required($required)
                                                       @class(['form-control', 'is-invalid' => $f['invalid']]) placeholder="{{ __("contacts::front.form.{$name}_placeholder") }}">
                                                @if ($f['invalid'])<div class="invalid-feedback">{{ $f['error'] }}</div>@endif
                                            </div>
                                        @endforeach

                                        @php($f = $field('message'))
                                        <div class="col-12">
                                            <label class="form-label" for="{{ $f['id'] }}">{{ __('contacts::front.form.message') }}</label>
                                            <textarea id="{{ $f['id'] }}" name="message" rows="6" minlength="10" maxlength="5000" required
                                                      @class(['form-control', 'is-invalid' => $f['invalid']]) placeholder="{{ __('contacts::front.form.message_placeholder') }}">{{ old('message') }}</textarea>
                                            @if ($f['invalid'])<div class="invalid-feedback">{{ $f['error'] }}</div>@endif
                                        </div>

                                        @if ($formErrors->has('website'))
                                            <div class="col-12"><div class="invalid-feedback d-block">{{ __('contacts::front.rejected') }}</div></div>
                                        @endif

                                        <div class="col-12">
                                            <div class="contact-foot">
                                                <button type="submit" class="btn btn-brand">{{ __('contacts::front.form.submit') }} <i class="bi bi-send"></i></button>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                            @endif
                        </div>
                    </div>

                    <div class="col-lg-5">
                        <div class="contact-aside">
                            @if ($primary?->email)
                                <div class="aside-box aside-mail reveal" data-delay="1">
                                    <span class="ico"><i class="bi bi-envelope"></i></span>
                                    <div>
                                        <small>{{ __('contacts::front.prefer_email') }}</small>
                                        <a href="mailto:{{ $primary->email }}">{{ $primary->email }}</a>
                                    </div>
                                </div>
                            @endif
                            @if ($primary?->phone)
                                <div class="aside-box aside-mail reveal" data-delay="1">
                                    <span class="ico"><i class="bi bi-telephone"></i></span>
                                    <div>
                                        <small>{{ __('contacts::front.call_us') }}</small>
                                        <a href="{{ $primary->telUrl() }}">{{ $primary->phone }}</a>
                                    </div>
                                </div>
                            @endif

                            @forelse ($contacts as $contact)
                                <div class="aside-box contact-office reveal" data-delay="2">
                                    @if ($photo = $contact->photoUrl())
                                        <img src="{{ $photo }}" alt="{{ $contact->title }}" class="contact-office-photo" loading="lazy">
                                    @endif
                                    <h3>{{ $contact->title }}</h3>
                                    <ul>
                                        @foreach ($contact->links(['address', 'phone', 'email']) as $link)
                                            <li>
                                                <i class="bi {{ $icons[$link['type']] }}"></i>
                                                @if ($link['url'] && $link['type'] !== 'address')
                                                    <a href="{{ $link['url'] }}">{{ $link['text'] }}</a>
                                                @else
                                                    <span>{{ $link['text'] }}</span>
                                                @endif
                                            </li>
                                        @endforeach
                                    </ul>
                                    @if ($mapUrl = $contact->mapUrl())
                                        <a href="{{ $mapUrl }}" class="link-arrow" target="_blank" rel="noopener">{{ __('contacts::front.open_map') }} <i class="bi bi-arrow-up-right"></i></a>
                                    @endif
                                </div>
                            @empty
                                <div class="aside-box reveal" data-delay="2">
                                    <p class="mb-0">{{ __('contacts::front.empty') }}</p>
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>

                @if ($mapContact)
                    <div class="contact-map reveal">
                        <iframe src="{{ $mapEmbed }}" title="{{ __('contacts::front.map_title', ['title' => $mapContact->title]) }}" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                    </div>
                @endif
            </div>
        </section>
    </main>
@endsection
