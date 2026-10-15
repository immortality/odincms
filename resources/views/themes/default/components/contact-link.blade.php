{{--
    One way to reach the site contact (an item of Contact::links()): the icon and the text, as a link
    when it has a URL (the address opens the map in a new tab).

    @foreach ($siteContact->links(['email', 'phone']) as $link)
        <x-theme::contact-link :link="$link" icon-class="me-2" />
    @endforeach
--}}
@props(['link', 'iconClass' => ''])

@php
    $icon = ['email' => 'bi-envelope', 'phone' => 'bi-telephone', 'address' => 'bi-geo-alt'][$link['type']] ?? 'bi-dot';
@endphp

@if ($link['url'])
    <a href="{{ $link['url'] }}" @if ($link['type'] === 'address') target="_blank" rel="noopener" @endif {{ $attributes }}><i class="bi {{ $icon }} {{ $iconClass }}"></i>{{ $link['text'] }}</a>
@else
    <span {{ $attributes }}><i class="bi {{ $icon }} {{ $iconClass }}"></i>{{ $link['text'] }}</span>
@endif
