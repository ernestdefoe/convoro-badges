<?php

namespace Convoro\Ext\Badges;

use App\Models\Post;
use App\Models\Reaction;
use App\Models\Topic;
use App\Models\User;
use App\Support\Notifier;
use Convoro\Ext\Badges\Models\Badge;
use Convoro\Ext\Badges\Notifications\BadgeAwarded;
use Illuminate\Support\Facades\DB;

/**
 * The award engine. Given a user, evaluates every enabled badge's criteria
 * against the user's current stats and awards any newly-earned ones. Wrapped in
 * a catch-all so badge evaluation can NEVER break the action that triggered it
 * (posting, reacting, registering).
 */
class Awarder
{
    public static function evaluate(?User $user, bool $notify = true): void
    {
        if (! $user) {
            return;
        }

        try {
            $badges = Badge::where('enabled', true)->get();
            if ($badges->isEmpty()) {
                return;
            }

            $earned = DB::table('user_badges')->where('user_id', $user->id)->pluck('badge_id')->all();
            $pending = $badges->reject(fn ($b) => in_array($b->id, $earned));
            if ($pending->isEmpty()) {
                return;
            }

            $stats = self::stats($user);
            foreach ($pending as $b) {
                if (self::meets($b, $stats)) {
                    self::award($user, $b, $notify);
                }
            }
        } catch (\Throwable) {
            // Never let badge evaluation bubble up into the user's request.
        }
    }

    /** Award engine, triggered for the author of a post/topic, or a reaction's recipient. */
    public static function evaluateUserId(?int $userId, bool $notify = true): void
    {
        if ($userId) {
            self::evaluate(User::find($userId), $notify);
        }
    }

    /** @return array<string,int> the metrics each criteria_type is checked against */
    private static function stats(User $user): array
    {
        $postIds = Post::where('user_id', $user->id)->pluck('id');

        return [
            'posts' => $postIds->count(),
            'topics' => Topic::where('user_id', $user->id)->count(),
            'reactions_received' => $postIds->isEmpty() ? 0 : Reaction::whereIn('post_id', $postIds)->count(),
            'age_days' => $user->created_at ? (int) $user->created_at->diffInDays(now()) : 0,
            'order' => User::where('id', '<=', $user->id)->count(), // ~ join order
        ];
    }

    private static function meets(Badge $b, array $stats): bool
    {
        $v = $stats[$b->criteria_type] ?? null;
        if ($v === null) {
            return false;
        }

        // "order" = be among the first N members (<=); everything else is a >= milestone.
        return $b->criteria_type === 'order' ? $v <= $b->threshold : $v >= $b->threshold;
    }

    private static function award(User $user, Badge $b, bool $notify): void
    {
        $inserted = DB::table('user_badges')->insertOrIgnore([
            'user_id' => $user->id,
            'badge_id' => $b->id,
            'awarded_at' => now(),
        ]);

        if ($inserted && $notify) {
            try {
                Notifier::send($user, new BadgeAwarded($b));
            } catch (\Throwable) {
            }
        }
    }
}
