<?php

namespace Modules\Blog\Widgets;

use App\Cms\Widgets\Widget;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Modules\Blog\Models\Post;
use Modules\Categories\Models\Category;

/**
 * The newest published posts, optionally of one category (and its subcategories), as a list
 * (a sidebar) or as cards (a home page section with its own heading: the widget title as the eyebrow,
 * the slogan and the intro, and "View all posts" beside it).
 */
class LatestPostsWidget extends Widget
{
    public static string $key = 'blog.latest-posts';

    public static string $name = 'blog::widgets.latest_posts.name';

    public static string $description = 'blog::widgets.latest_posts.description';

    public static string $icon = 'fas fa-newspaper';

    public function fields(): array
    {
        $heading = $this->headingFields(
            'blog::widgets.latest_posts.slogan_help',
            'blog::widgets.latest_posts.intro_help',
        );

        return [
            'slogan' => $heading['slogan'],
            'intro' => $heading['intro'],
            'limit' => [
                'type' => 'number',
                'label' => 'blog::widgets.latest_posts.limit',
                'default' => 5,
                'rules' => ['required', 'integer', 'min:1', 'max:20'],
            ],
            'category' => [
                'type' => 'select',
                'label' => 'blog::widgets.latest_posts.category',
                'options' => fn () => Category::options('blog'),
                'placeholder' => 'blog::widgets.latest_posts.all_categories',
            ],
            'layout' => [
                'type' => 'select',
                'label' => 'blog::widgets.latest_posts.layout',
                'options' => fn () => [
                    'list' => __('blog::widgets.latest_posts.layouts.list'),
                    'cards' => __('blog::widgets.latest_posts.layouts.cards'),
                ],
                'default' => 'list',
            ],
            'show_image' => [
                'type' => 'checkbox',
                'label' => 'blog::widgets.latest_posts.show_image',
                'default' => false,
            ],
            'show_date' => [
                'type' => 'checkbox',
                'label' => 'blog::widgets.latest_posts.show_date',
                'default' => true,
            ],
        ];
    }

    /**
     * The cards layout shows the title itself (as the eyebrow); the list keeps the area's heading.
     */
    public function ownsHeading(): bool
    {
        return $this->setting('layout') === 'cards';
    }

    public function render(): View
    {
        $category = filled($this->setting('category')) ? Category::ofType('blog')->find((int) $this->setting('category')) : null;

        $posts = Post::published()
            ->with(['media', 'category'])
            ->when($category !== null, fn (Builder $query) => $query->whereIn('category_id', $category->descendantIds()))
            ->latest('published_at')
            ->latest('id')
            ->take((int) $this->setting('limit'))
            ->get();

        // "list" for a sidebar, "cards" (the template's news cards) for a home page section.
        $layout = $this->setting('layout') === 'cards' ? 'latest-posts-cards' : 'latest-posts';

        return view($this->themeView('blog', $layout), [
            'posts' => $posts,
            'showImage' => (bool) $this->setting('show_image'),
            'showDate' => (bool) $this->setting('show_date'),
            'eyebrow' => $this->title(),
            'slogan' => $this->translated('slogan'),
            'intro' => $this->translated('intro'),
        ]);
    }
}
