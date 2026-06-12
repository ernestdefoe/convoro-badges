# Member Badges — Convoro extension

Reward members with earnable badges. Badges are awarded **automatically** as members
take part, and shown on their profile.

## Included badges
🏆 Founding Member · 🎉 First Post · 💬 Conversationalist · ✍️ Prolific ·
🧵 Topic Starter · ❤️ Well-Liked · 🌟 Crowd Favorite · 🎖️ Veteran

Admins can edit each badge (name, emoji, color, threshold) and re-scan all members
under **Admin → Member Badges** (`/admin/ext/badges`).

## How it works
A lightweight award engine listens to posts, reactions, topics and new members,
evaluates each badge's criteria and awards any newly-earned ones (with an in-app
notification). Profiles display earned badges via the `profile:below` slot.

Requires Convoro **≥ 0.41.0**. Free & MIT-licensed.
