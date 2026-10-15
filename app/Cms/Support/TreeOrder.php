<?php

namespace App\Cms\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Saves the order of a drag-and-drop tree (menu links, categories, pages).
 *
 * The admin posts { items: [{id, parent_id}, …] }: every node once, in display order. The tree is
 * checked (the same nodes as $ids, parents among them, no loops, at most $maxDepth levels), then
 * each node gets its parent and its position among its siblings (sort_order 1, 2, 3, …).
 */
final class TreeOrder
{
    /**
     * @param  class-string<Model>  $model
     * @param  list<int>  $ids  every node of the tree
     * @return list<array{id: int, parent_id: int|null}> the saved items
     */
    public static function save(Request $request, string $model, array $ids, int $maxDepth): array
    {
        $items = self::items($request);
        self::check($items, $ids, $maxDepth);
        self::apply($model, $items);

        return $items;
    }

    /**
     * Save checked items (for callers that check more in between, e.g. unique slugs per parent).
     *
     * @param  class-string<Model>  $model
     * @param  list<array{id: int, parent_id: int|null}>  $items
     */
    public static function apply(string $model, array $items): void
    {
        DB::transaction(function () use ($items, $model) {
            $positions = [];
            foreach ($items as $item) {
                $key = $item['parent_id'] ?? 0;
                $positions[$key] = ($positions[$key] ?? 0) + 1;

                $model::query()->whereKey($item['id'])->update(['parent_id' => $item['parent_id'], 'sort_order' => $positions[$key]]);
            }
        });
    }

    /**
     * @return list<array{id: int, parent_id: int|null}>
     */
    public static function items(Request $request): array
    {
        $request->validate([
            'items' => ['required', 'array'],
            'items.*.id' => ['required', 'integer'],
            'items.*.parent_id' => ['nullable', 'integer'],
        ]);

        return array_values(array_map(fn (array $item) => [
            'id' => (int) $item['id'],
            'parent_id' => isset($item['parent_id']) ? (int) $item['parent_id'] : null,
        ], (array) $request->input('items')));
    }

    /**
     * Abort with 422 unless the items form a valid tree of exactly $ids.
     *
     * @param  list<array{id: int, parent_id: int|null}>  $items
     * @param  list<int>  $ids
     */
    public static function check(array $items, array $ids, int $maxDepth): void
    {
        $sent = array_column($items, 'id');
        sort($sent);
        sort($ids);
        abort_unless($sent === array_map('intval', $ids), 422, __('cms.tree.mismatch'));

        $parents = array_column($items, 'parent_id', 'id');
        foreach ($parents as $id => $parentId) {
            $depth = 1;
            for ($current = $parentId; $current !== null; $current = $parents[$current] ?? null) {
                abort_unless(array_key_exists($current, $parents) && $current !== $id, 422, __('cms.tree.loop'));
                abort_if(++$depth > $maxDepth, 422, __('cms.tree.too_deep', ['levels' => $maxDepth]));
            }
        }
    }
}
