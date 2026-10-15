<?php

namespace Modules\Blog\Http\Controllers\Admin;

use App\Cms\Media\CroppedImageInput;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Modules\Blog\Http\Requests\PostRequest;
use Modules\Blog\Models\Post;
use Modules\Categories\Models\Category;

class PostController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Post::class);

        $perPage = min(max($request->integer('per_page', 20), 5), 100);

        $posts = Post::query()
            ->with(['category', 'author', 'media'])
            ->search($request->string('search')->toString(), ['title', 'slug'])
            ->when($request->string('status')->toString(), fn (Builder $query, string $status) => match ($status) {
                'draft' => $query->draft(),
                'published' => $query->published(),
                'scheduled' => $query->where('status', Post::PUBLISHED)->where('published_at', '>', now()),
                default => $query,
            })
            ->when($request->integer('category'), function (Builder $query, int $categoryId) {
                $category = Category::ofType('blog')->find($categoryId);
                $query->whereIn('category_id', $category?->descendantIds() ?? [0]);
            })
            ->when($request->boolean('mine'), fn (Builder $query) => $query->where('author_id', $request->user()?->id))
            ->orderByRaw('published_at IS NULL DESC')
            ->latest('published_at')
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();

        return view('blog::admin.index', [
            'posts' => $posts,
            'categories' => Category::options('blog'),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Post::class);

        return $this->form(new Post(['status' => Post::DRAFT]));
    }

    public function store(PostRequest $request): RedirectResponse
    {
        $post = DB::transaction(function () use ($request) {
            $post = Post::create([...$request->modelAttributes(), 'author_id' => $request->user()?->id]);
            $this->saveCover($request, $post);

            return $post;
        });

        return redirect()->route('admin.blog.edit', $post)->with('success', __('blog::admin.saved'));
    }

    public function edit(Post $post): View
    {
        Gate::authorize('update', $post);

        return $this->form($post);
    }

    public function update(PostRequest $request, Post $post): RedirectResponse
    {
        DB::transaction(function () use ($request, $post) {
            $post->update($request->modelAttributes());
            $this->saveCover($request, $post);
        });

        return redirect()->route('admin.blog.edit', $post)->with('success', __('blog::admin.saved'));
    }

    public function destroy(Post $post): RedirectResponse
    {
        Gate::authorize('delete', $post);

        $post->delete();

        return redirect()->route('admin.blog.index')->with('success', __('blog::admin.deleted'));
    }

    /**
     * Publish, unpublish or delete several posts. Posts the user may not change are skipped.
     */
    public function bulk(Request $request): RedirectResponse
    {
        abort_unless(Gate::any(['blog.edit', 'blog.delete']), 403);

        $request->validate([
            'action' => ['required', Rule::in(['publish', 'unpublish', 'delete'])],
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);

        $action = $request->string('action')->toString();
        $done = 0;
        $skipped = [];

        foreach (Post::whereKey($request->array('ids'))->get() as $post) {
            $allowed = $action === 'delete'
                ? Gate::allows('delete', $post)
                : Gate::allows('publish', Post::class) && Gate::allows('update', $post);

            if (! $allowed) {
                $skipped[] = $post->title;

                continue;
            }

            match ($action) {
                'delete' => $post->delete(),
                'publish' => $post->update(['status' => Post::PUBLISHED]),
                default => $post->update(['status' => Post::DRAFT]),
            };
            $done++;
        }

        $redirect = back()->with('success', trans_choice('blog::admin.bulk_done', $done, ['count' => $done]));

        return $skipped === []
            ? $redirect
            : $redirect->with('warning', __('blog::admin.bulk_skipped', ['titles' => implode(', ', $skipped)]));
    }

    private function form(Post $post): View
    {
        return view('blog::admin.form', [
            'post' => $post,
            'categories' => Category::options('blog'),
            'canPublish' => Gate::allows('publish', Post::class),
        ]);
    }

    private function saveCover(Request $request, Post $post): void
    {
        CroppedImageInput::save($request, $post, 'cover');
    }
}
