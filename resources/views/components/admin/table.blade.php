{{--
    List table in a card (OdinAdmin, reference: odinadmin/basic-table.html).

    <x-admin.table bulk-form="bulk-form" :empty="__('blog::admin.empty')">
        <x-slot:toolbar> <x-admin.filters …/> </x-slot:toolbar>
        <x-slot:bulk> <x-admin.bulk-actions …/> </x-slot:bulk>
        <x-slot:head> <th>Title</th> … <th class="col-actions"></th> </x-slot:head>
        @foreach ($posts as $post)
            <tr>
                <x-admin.table.check :value="$post->id" />
                <td class="col-main" data-label="Title">{{ $post->title }}</td>
            </tr>
        @endforeach
        <x-slot:footer> <x-admin.pagination :paginator="$posts" /> </x-slot:footer>
    </x-admin.table>

    Phones show each row as a card: give every <td> a data-label (its column name); the main cell gets
    .col-main (no label).
--}}
@props([
    'bulkForm' => null,
    'empty' => null,
])

<div {{ $attributes->class(['card card-auto']) }}>
    @isset($toolbar)
        <div class="table-toolbar">
            {{ $toolbar }}
        </div>
    @endisset

    {{ $bulk ?? '' }}

    <div class="table-responsive">
        <table class="table table-custom table-padded table-stack align-middle">
            <thead>
                <tr>
                    @if ($bulkForm)
                        <th class="col-check">
                            <input type="checkbox" class="form-check-input check-soft js-check-all" data-form="{{ $bulkForm }}"
                                aria-label="{{ __('Select all') }}">
                        </th>
                    @endif
                    {{ $head }}
                </tr>
            </thead>
            <tbody>
                {{ $slot }}

                @unless ($slot->hasActualContent())
                    <tr class="table-empty">
                        <td colspan="100"><i class="far fa-folder-open"></i>{{ $empty ?? __('Nothing found.') }}</td>
                    </tr>
                @endunless
            </tbody>
        </table>
    </div>

    @isset($footer)
        <div class="table-footer">
            {{ $footer }}
        </div>
    @endisset
</div>
