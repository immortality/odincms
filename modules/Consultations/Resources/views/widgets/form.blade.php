{{--
    The "Request a free consultation" widget ("Consulting CTA" of the OdinZero template): a blue box with an
    optional figure, the heading and the form. The widget is never cached, so the CSRF token, the errors
    (error bag "consultation") and the old input belong to this visitor. After sending, the thank-you replaces the form.
    $eyebrow, $slogan, $intro, $stat (value, label) or null, $formTitle, $button, $services (id => title).
    A theme can replace it: themes/{theme}/widgets/consultations-form.blade.php
--}}
@php
    $formErrors = ($errors ?? new \Illuminate\Support\ViewErrorBag)->getBag('consultation');
    $sent = session('consultations.sent');
@endphp

<div class="cta-box consultation-box reveal" id="consultation">
    <div class="row align-items-center g-4 g-lg-5">
        <div class="col-lg-6 on-dark">
            @if ($stat)
                <div class="cta-stat"><span class="big">{{ $stat['value'] }}</span><span class="lbl">{{ $stat['label'] }}</span></div>
            @endif
            <x-theme::section-head :eyebrow="$eyebrow" :slogan="$slogan" :intro="$intro" align="start" class="mb-0" />
        </div>

        <div class="col-lg-6">
            @if ($sent)
                <div class="cta-form consultation-sent" role="status">
                    <div class="consultation-sent-icon"><i class="bi bi-check-lg"></i></div>
                    <h3>{{ __('consultations::front.sent_title') }}</h3>
                    <p class="mb-0">{{ __('consultations::front.sent_text') }}</p>
                </div>
            @else
                <form class="cta-form" method="POST" action="{{ route('consultations.store') }}" novalidate>
                    @csrf
                    <h3 class="cta-form-title">{{ $formTitle }}</h3>

                    {{-- Honeypot: hidden from people, filled in by bots. --}}
                    <div class="consultation-hp" aria-hidden="true">
                        <label for="consultation-website">Website</label>
                        <input type="text" id="consultation-website" name="website" tabindex="-1" autocomplete="off">
                    </div>

                    <div class="row g-3">
                        <div class="col-sm-6">
                            <label for="consultation-name" class="visually-hidden">{{ __('consultations::front.name') }}</label>
                            <input type="text" id="consultation-name" name="name" value="{{ old('name') }}" maxlength="120" required autocomplete="name"
                                   @class(['form-control', 'is-invalid' => $formErrors->has('name')]) placeholder="{{ __('consultations::front.name') }}">
                            @if ($formErrors->has('name'))<div class="invalid-feedback">{{ $formErrors->first('name') }}</div>@endif
                        </div>
                        <div class="col-sm-6">
                            <label for="consultation-email" class="visually-hidden">{{ __('consultations::front.email') }}</label>
                            <input type="email" id="consultation-email" name="email" value="{{ old('email') }}" maxlength="255" required autocomplete="email"
                                   @class(['form-control', 'is-invalid' => $formErrors->has('email')]) placeholder="{{ __('consultations::front.email') }}">
                            @if ($formErrors->has('email'))<div class="invalid-feedback">{{ $formErrors->first('email') }}</div>@endif
                        </div>
                        @if ($services !== [])
                            <div class="col-12">
                                <label for="consultation-service" class="visually-hidden">{{ __('consultations::front.service') }}</label>
                                <select id="consultation-service" name="service" @class(['form-select', 'is-invalid' => $formErrors->has('service')])>
                                    <option value="">{{ __('consultations::front.choose_service') }}</option>
                                    @foreach ($services as $id => $title)
                                        <option value="{{ $id }}" @selected((string) old('service') === (string) $id)>{{ $title }}</option>
                                    @endforeach
                                </select>
                                @if ($formErrors->has('service'))<div class="invalid-feedback">{{ $formErrors->first('service') }}</div>@endif
                            </div>
                        @endif
                        <div class="col-12">
                            <label for="consultation-message" class="visually-hidden">{{ __('consultations::front.message') }}</label>
                            <textarea id="consultation-message" name="message" rows="3" maxlength="2000"
                                      @class(['form-control', 'is-invalid' => $formErrors->has('message')]) placeholder="{{ __('consultations::front.message_placeholder') }}">{{ old('message') }}</textarea>
                            @if ($formErrors->has('message'))<div class="invalid-feedback">{{ $formErrors->first('message') }}</div>@endif
                        </div>
                        @if ($formErrors->has('website'))
                            <div class="col-12"><div class="invalid-feedback d-block">{{ __('consultations::front.rejected') }}</div></div>
                        @endif
                        <div class="col-12">
                            <button type="submit" class="btn btn-brand w-100 justify-content-center">{{ $button }} <i class="bi bi-send"></i></button>
                        </div>
                    </div>
                </form>
            @endif
        </div>
    </div>
</div>
