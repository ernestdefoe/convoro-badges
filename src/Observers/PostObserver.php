<?php

namespace Convoro\Ext\Badges\Observers;

use App\Models\Post;
use Convoro\Ext\Badges\Awarder;

class PostObserver
{
    public function created(Post $post): void
    {
        Awarder::evaluateUserId($post->user_id);
    }
}
