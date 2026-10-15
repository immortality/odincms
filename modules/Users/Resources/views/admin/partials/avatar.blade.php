{{-- $user, optional $tone (OdinAdmin avatar colour c1–c5, default c2) --}}
<div class="av-sm {{ $tone ?? 'c2' }}">
    @if ($user->exists && $user->avatarUrl())
        <img src="{{ $user->avatarUrl() }}" alt="">
    @else
        {{ $user->exists ? $user->initials() : '?' }}
    @endif
</div>
