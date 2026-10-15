<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\Fixtures\Content\Article;

beforeEach(function () {
    Schema::create('test_articles', function (Blueprint $table) {
        $table->id();
        $table->string('group')->nullable();
        $table->json('title');
        $table->json('body')->nullable();
        $table->text('note')->nullable();
        $table->string('slug');
        $table->string('status', 16)->default('draft');
        $table->timestamp('published_at')->nullable();
        $table->unsignedInteger('sort_order')->default(0);
        $table->timestamps();
        $table->softDeletes();
        $table->unique(['group', 'slug']);
    });
});

describe('HasSlug', function () {
    it('makes the slug from the default-locale title, transliterating Cyrillic', function () {
        expect(Article::create(['title' => ['en' => 'Hello World!', 'ru' => 'Привет']])->slug)->toBe('hello-world')
            ->and(Article::create(['title' => ['ru' => 'Иссык-Куль летом']])->slug)->toBe('issyk-kul-letom');
    });

    it('normalizes a given slug and keeps it on later saves', function () {
        $article = Article::create(['title' => ['en' => 'Title'], 'slug' => 'My Custom Slug']);
        expect($article->slug)->toBe('my-custom-slug');

        $article->update(['title' => ['en' => 'Another title']]);
        expect($article->fresh()->slug)->toBe('my-custom-slug');
    });

    it('adds a suffix when the slug is taken, counting trashed rows', function () {
        Article::create(['title' => ['en' => 'News']]);
        Article::create(['title' => ['en' => 'News']])->delete();

        expect(Article::create(['title' => ['en' => 'News']])->slug)->toBe('news-3');
    });

    it('is unique only within the slug scope', function () {
        Article::create(['group' => 'a', 'title' => ['en' => 'Same']]);

        expect(Article::create(['group' => 'b', 'title' => ['en' => 'Same']])->slug)->toBe('same');
    });

    it('keeps its own slug when saved again', function () {
        $article = Article::create(['title' => ['en' => 'Stable']]);
        $article->save();

        expect($article->slug)->toBe('stable');
    });
});

describe('HasStatus', function () {
    it('fills the publish date on publishing and hides scheduled content', function () {
        $this->freezeSecond();

        $now = Article::create(['title' => ['en' => 'Now'], 'status' => 'published']);
        $later = Article::create(['title' => ['en' => 'Later'], 'status' => 'published', 'published_at' => now()->addDay()]);
        Article::create(['title' => ['en' => 'Draft']]);

        expect($now->published_at?->equalTo(now()))->toBeTrue()
            ->and(Article::published()->pluck('slug')->all())->toBe(['now'])
            ->and(Article::draft()->pluck('slug')->all())->toBe(['draft'])
            ->and($now->isPublished())->toBeTrue()
            ->and($later->isPublished())->toBeFalse()
            ->and($later->isScheduled())->toBeTrue();
    });
});

describe('Sortable', function () {
    it('puts new rows last among their siblings and saves a new order', function () {
        $a = Article::create(['group' => 'x', 'title' => ['en' => 'A']]);
        $b = Article::create(['group' => 'x', 'title' => ['en' => 'B']]);
        $other = Article::create(['group' => 'y', 'title' => ['en' => 'C']]);

        expect([$a->sort_order, $b->sort_order, $other->sort_order])->toBe([1, 2, 1]);

        Article::reorder([$b->id, $a->id]);

        expect(Article::where('group', 'x')->ordered()->pluck('slug')->all())->toBe(['b', 'a']);
    });
});

describe('PurifiesHtml', function () {
    it('cleans translatable and plain HTML attributes on save', function () {
        $article = Article::create([
            'title' => ['en' => 'Safe'],
            'body' => ['en' => '<p onclick="x()">Hi</p><script>alert(1)</script>', 'ru' => '<p>Привет</p><iframe src="https://evil.test"></iframe>'],
            'note' => '<a href="javascript:alert(1)">x</a><b>ok</b>',
        ])->fresh();

        expect($article->getTranslation('body', 'en'))->toBe('<p>Hi</p>')
            ->and($article->getTranslation('body', 'ru'))->toStartWith('<p>Привет</p>')->not->toContain('evil.test')
            ->and($article->note)->toBe('<a>x</a><b>ok</b>');
    });

    it('keeps what the editor produces: tables, images, links in new tabs, YouTube embeds', function () {
        $html = '<table><tbody><tr><td>1</td></tr></tbody></table>'
            .'<p><img src="/storage/a.jpg" alt="A"></p>'
            .'<p><a href="https://example.com" target="_blank" rel="noopener">link</a></p>'
            .'<iframe src="https://www.youtube.com/embed/abc" width="560" height="315"></iframe>';

        $article = Article::create(['title' => ['en' => 'Rich'], 'body' => ['en' => $html]]);

        expect($article->getTranslation('body', 'en'))
            ->toContain('<td>1</td>', 'alt="A"', 'target="_blank"', 'src="https://www.youtube.com/embed/abc"');
    });
});

describe('Searchable', function () {
    it('finds text in any locale of translatable columns, ignoring case, but not in the JSON keys', function () {
        Article::create(['title' => ['en' => 'Mountain Lakes', 'ru' => 'Горные озёра']]);
        Article::create(['title' => ['en' => 'City walks'], 'slug' => 'bishkek']);

        $search = fn (string $text) => Article::search($text, ['title', 'slug'])->pluck('slug')->all();

        expect($search('mountain'))->toBe(['mountain-lakes'])
            ->and($search('ГОРНЫЕ'))->toBe(['mountain-lakes'])
            ->and($search('bishkek'))->toBe(['bishkek'])
            ->and($search('en'))->toBe([])
            ->and($search('100%'))->toBe([]);
    });
});
