<?php

namespace Convoro\Ext\Badges\Observers;

use App\Models\Post;
use App\Models\Reaction;
use Convoro\Ext\Badges\Awarder;

class ReactionObserver
{
    public function created(Reaction $reaction): void
    {
        // The reaction's recipient (the post's author) is who earns "reactions received".
        Awarder::evaluateUserId(Post::whereKey($reaction->post_id)->value('user_id'));
    }
}
