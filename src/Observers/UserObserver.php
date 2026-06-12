<?php

namespace Convoro\Ext\Badges\Observers;

use App\Models\User;
use Convoro\Ext\Badges\Awarder;

class UserObserver
{
    public function created(User $user): void
    {
        // New member → may already qualify for "Founding Member".
        Awarder::evaluate($user);
    }
}
