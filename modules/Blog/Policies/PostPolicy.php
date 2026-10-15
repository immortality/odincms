<?php

namespace Modules\Blog\Policies;

use App\Models\User;
use Modules\Blog\Models\Post;

/**
 * Editors (blog.publish) work on every post. Authors (without blog.publish) edit and delete only
 * their own drafts, and cannot publish.
 */
class PostPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('blog.view');
    }

    public function create(User $user): bool
    {
        return $user->can('blog.create');
    }

    public function update(User $user, Post $post): bool
    {
        return $user->can('blog.edit') && $this->ownsOrPublishes($user, $post);
    }

    public function delete(User $user, Post $post): bool
    {
        return $user->can('blog.delete') && $this->ownsOrPublishes($user, $post);
    }

    public function publish(User $user): bool
    {
        return $user->can('blog.publish');
    }

    private function ownsOrPublishes(User $user, Post $post): bool
    {
        return $user->can('blog.publish') || ($post->author_id === $user->id && $post->status === Post::DRAFT);
    }
}
