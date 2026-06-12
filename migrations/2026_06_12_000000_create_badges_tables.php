<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('badges')) {
            Schema::create('badges', function (Blueprint $t) {
                $t->id();
                $t->string('slug')->unique();
                $t->string('name');
                $t->string('description')->nullable();
                $t->string('emoji', 16)->default('🏅');
                $t->string('color', 20)->default('#5b5bd6');
                // posts | topics | reactions_received | age_days | order
                $t->string('criteria_type', 32);
                $t->unsignedInteger('threshold')->default(1);
                $t->boolean('enabled')->default(true);
                $t->unsignedInteger('position')->default(0);
                $t->timestamps();
            });
        }

        if (! Schema::hasTable('user_badges')) {
            Schema::create('user_badges', function (Blueprint $t) {
                $t->id();
                $t->foreignId('user_id')->constrained()->cascadeOnDelete();
                $t->foreignId('badge_id')->constrained('badges')->cascadeOnDelete();
                $t->timestamp('awarded_at')->nullable();
                $t->unique(['user_id', 'badge_id']);
            });
        }

        // Seed a sensible default set (idempotent).
        $defaults = [
            ['slug' => 'founding-member', 'name' => 'Founding Member', 'emoji' => '🏆', 'color' => '#a855f7', 'criteria_type' => 'order', 'threshold' => 25, 'description' => 'One of the first 25 members', 'position' => 5],
            ['slug' => 'first-post', 'name' => 'First Post', 'emoji' => '🎉', 'color' => '#10b981', 'criteria_type' => 'posts', 'threshold' => 1, 'description' => 'Made their first post', 'position' => 10],
            ['slug' => 'conversationalist', 'name' => 'Conversationalist', 'emoji' => '💬', 'color' => '#0ea5e9', 'criteria_type' => 'posts', 'threshold' => 25, 'description' => 'Posted 25 times', 'position' => 20],
            ['slug' => 'prolific', 'name' => 'Prolific', 'emoji' => '✍️', 'color' => '#6366f1', 'criteria_type' => 'posts', 'threshold' => 100, 'description' => 'Posted 100 times', 'position' => 30],
            ['slug' => 'topic-starter', 'name' => 'Topic Starter', 'emoji' => '🧵', 'color' => '#f59e0b', 'criteria_type' => 'topics', 'threshold' => 5, 'description' => 'Started 5 topics', 'position' => 40],
            ['slug' => 'well-liked', 'name' => 'Well-Liked', 'emoji' => '❤️', 'color' => '#ef4444', 'criteria_type' => 'reactions_received', 'threshold' => 10, 'description' => 'Received 10 reactions', 'position' => 50],
            ['slug' => 'crowd-favorite', 'name' => 'Crowd Favorite', 'emoji' => '🌟', 'color' => '#eab308', 'criteria_type' => 'reactions_received', 'threshold' => 50, 'description' => 'Received 50 reactions', 'position' => 60],
            ['slug' => 'veteran', 'name' => 'Veteran', 'emoji' => '🎖️', 'color' => '#64748b', 'criteria_type' => 'age_days', 'threshold' => 365, 'description' => 'Member for over a year', 'position' => 70],
        ];
        foreach ($defaults as $d) {
            if (! DB::table('badges')->where('slug', $d['slug'])->exists()) {
                DB::table('badges')->insert(array_merge($d, ['enabled' => true, 'created_at' => now(), 'updated_at' => now()]));
            }
        }

        // Backfill badges already earned by existing members (no notifications).
        if (class_exists(\Convoro\Ext\Badges\Awarder::class)) {
            \App\Models\User::query()->orderBy('id')->chunk(200, function ($users) {
                foreach ($users as $u) {
                    \Convoro\Ext\Badges\Awarder::evaluate($u, false);
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_badges');
        Schema::dropIfExists('badges');
    }
};
