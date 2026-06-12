<?php

namespace Convoro\Ext\Badges\Observers;

use App\Models\Topic;
use Convoro\Ext\Badges\Awarder;

class TopicObserver
{
    public function created(Topic $topic): void
    {
        Awarder::evaluateUserId($topic->user_id);
    }
}
