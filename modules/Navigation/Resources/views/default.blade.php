{{-- Default menu markup (Bootstrap 5 navbar): two levels, the second as a dropdown. $items, $slug --}}
<ul class="navbar-nav navigation-{{ $slug }}">
    @foreach ($items as $item)
        @if ($item['children'] !== [])
            <li class="nav-item dropdown {{ $item['css_class'] }}">
                <a href="{{ $item['url'] }}" @class(['nav-link dropdown-toggle', 'active' => $item['active']]) role="button" data-bs-toggle="dropdown" aria-expanded="false">{{ $item['label'] }}</a>
                <ul class="dropdown-menu">
                    @foreach ($item['children'] as $child)
                        <li>
                            <a href="{{ $child['url'] }}" @class(['dropdown-item', $child['css_class'], 'active' => $child['active']])
                               @if ($child['new_tab']) target="_blank" rel="noopener" @endif
                               @if ($child['active']) aria-current="page" @endif>{{ $child['label'] }}</a>
                        </li>
                    @endforeach
                </ul>
            </li>
        @else
            <li class="nav-item {{ $item['css_class'] }}">
                <a href="{{ $item['url'] }}" @class(['nav-link', 'active' => $item['active']])
                   @if ($item['new_tab']) target="_blank" rel="noopener" @endif
                   @if ($item['active']) aria-current="page" @endif>{{ $item['label'] }}</a>
            </li>
        @endif
    @endforeach
</ul>
