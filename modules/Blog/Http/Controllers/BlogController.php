<?php

namespace Modules\Blog\Http\Controllers;

use App\Cms\Settings\Settings;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Modules\Blog\Models\Post;
use Modules\Categories\Models\Category;

/**
 * The public blog: /blog, /blog/category/{slug} (posts of the category and its subcategories), /blog/{slug}.
 * Prefixed by the locale for the non-default ones: /ru/blog/….
 */
class BlogController extends Controller
{
    public function __construct(private Settings $settings) {}

    public function index(): View
    {
        return view('blog::front.index', [
            'posts' => $this->posts(),
            'category' => null,
            'categories' => Category::tree('blog', activeOnly: true),
        ]);
    }

    public function category(string $slug): View
    {
        $category = Category::ofType('blog')->active()->where('slug', $slug)->firstOrFail();

        return view('blog::front.index', [
            'posts' => $this->posts(fn (Builder $query) => $query->whereIn('category_id', $category->descendantIds())),
            'category' => $category,
            'categories' => Category::tree('blog', activeOnly: true),
        ]);
    }

    /**
     * Drafts and scheduled posts are shown to those who may edit them (a preview), and are 404 for everyone else.
     */
    public function show(Request $request, string $slug): View
    {
        $post = Post::with(['category', 'author', 'media'])->where('slug', $slug)->firstOrFail();

        $preview = ! $post->isPublished();
        abort_if($preview && ! ($request->user() && Gate::forUser($request->user())->allows('update', $post)), 404);

        return view('blog::front.show', ['post' => $post, 'preview' => $preview]);
    }

    /**
     * @param  (callable(Builder<Post>): mixed)|null  $filter
     * @return LengthAwarePaginator<int, Post>
     */
    private function posts(?callable $filter = null): LengthAwarePaginator
    {
        return Post::published()
            ->with(['category', 'media'])
            ->when($filter !== null, fn (Builder $query) => $filter($query))
            ->latest('published_at')
            ->latest('id')
            ->paginate(max(1, (int) $this->settings->get('blog.per_page', 10)));
    }
}
