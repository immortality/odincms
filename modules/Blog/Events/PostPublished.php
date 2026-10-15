<?php

namespace Modules\Blog\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Blog\Models\Post;

/**
 * A post's status changed to published (it may still be scheduled: see $post->published_at).
 * Other modules can react (notify subscribers, ping search engines, …) without depending on Blog's code.
 */
class PostPublished
{
    use Dispatchable, SerializesModels;

    public function __construct(public Post $post) {}
}
