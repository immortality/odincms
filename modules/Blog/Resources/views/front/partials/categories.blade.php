{{-- One level of the blog's category list. $categories (by parent id), $parentId, $current --}}
<ul class="list-unstyled {{ $parentId ? 'ms-3' : '' }} mb-0">
    @foreach ($categories->get($parentId, collect()) as $item)
        <li class="py-1">
            <a href="{{ route('blog.category', $item->slug) }}" @class(['fw-semibold' => $current?->is($item)])>{{ $item->label() }}</a>
            @include('blog::front.partials.categories', ['parentId' => $item->id])
        </li>
    @endforeach
</ul>
