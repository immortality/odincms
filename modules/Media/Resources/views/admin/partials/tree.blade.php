{{-- The folder tree in the sidebar: the folders under $parentId, the open path ($openIds) unfolded. --}}
<ul class="fm-tree">
    @foreach ($byParent->get($parentId, []) as $node)
        @php
            $isOpen = in_array($node->id, $openIds, true);
            $hasChildren = $byParent->has($node->id);
        @endphp
        <li>
            <a href="{{ route('admin.media.index', ['folder' => $node->id]) }}"
               class="fm-nav-link st-progress @if ($current?->id === $node->id) active @endif"
               @if ($current?->id === $node->id) aria-current="page" @endif>
                <i class="fas {{ $isOpen && $hasChildren ? 'fa-folder-open' : 'fa-folder' }}"></i> <span class="text-truncate">{{ $node->name }}</span>
            </a>
            @if ($isOpen && $hasChildren)
                @include('media::admin.partials.tree', ['parentId' => $node->id])
            @endif
        </li>
    @endforeach
</ul>
