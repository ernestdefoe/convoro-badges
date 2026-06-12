<?php

namespace Convoro\Ext\Badges\Notifications;

use Convoro\Ext\Badges\Models\Badge;
use Illuminate\Notifications\Notification;

class BadgeAwarded extends Notification
{
    public function __construct(public Badge $badge) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'badge',
            'emoji' => $this->badge->emoji,
            // Synthetic "actor" so the existing notification avatar renders the
            // badge emoji in a circle (no crash on a missing real actor).
            'actor' => [
                'name' => $this->badge->name,
                'initials' => $this->badge->emoji,
                'color' => 1,
                'avatar' => null,
            ],
            'text' => __('You earned the “:name” badge :emoji', [
                'name' => $this->badge->name,
                'emoji' => $this->badge->emoji,
            ]),
            'url' => '/u/'.$notifiable->id,
        ];
    }
}
