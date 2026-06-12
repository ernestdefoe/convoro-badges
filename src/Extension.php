<?php

namespace Convoro\Ext\Badges;

use App\Models\Post;
use App\Models\Reaction;
use App\Models\Topic;
use App\Models\User;
use Convoro\Ext\Badges\Models\Badge;
use Convoro\Ext\Badges\Observers\PostObserver;
use Convoro\Ext\Badges\Observers\ReactionObserver;
use Convoro\Ext\Badges\Observers\TopicObserver;
use Convoro\Ext\Badges\Observers\UserObserver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class Extension extends ServiceProvider
{
    public function boot(): void
    {
        // Award engine triggers — hook Eloquent model events (Convoro has no
        // domain events). Guarded inside Awarder so they never break a request.
        Post::observe(PostObserver::class);
        Reaction::observe(ReactionObserver::class);
        Topic::observe(TopicObserver::class);
        User::observe(UserObserver::class);

        $this->registerRoutes();
    }

    private function registerRoutes(): void
    {
        // Public: a member's earned badges (drives the profile display).
        Route::middleware('web')->get('/api/ext/badges/user/{userId}', function ($userId) {
            if (! self::tablesReady()) {
                return response()->json([]);
            }
            $rows = DB::table('user_badges')
                ->join('badges', 'badges.id', '=', 'user_badges.badge_id')
                ->where('user_badges.user_id', (int) $userId)
                ->where('badges.enabled', true)
                ->orderBy('badges.position')
                ->get(['badges.slug', 'badges.name', 'badges.description', 'badges.emoji', 'badges.color', 'user_badges.awarded_at']);

            return response()->json($rows);
        })->whereNumber('userId');

        // Admin: manage badges.
        Route::middleware(['web', 'auth', 'admin'])->prefix('admin/ext/badges')->group(function () {
            Route::get('/', fn () => response(self::adminPage()));

            Route::post('/save', function (Request $request) {
                $data = $request->validate([
                    'id' => ['required', 'integer'],
                    'name' => ['required', 'string', 'max:60'],
                    'emoji' => ['nullable', 'string', 'max:16'],
                    'color' => ['required', 'regex:/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'],
                    'threshold' => ['required', 'integer', 'min:0'],
                    'enabled' => ['required', 'boolean'],
                ]);
                Badge::whereKey($data['id'])->update([
                    'name' => $data['name'],
                    'emoji' => $data['emoji'] ?: '🏅',
                    'color' => $data['color'],
                    'threshold' => $data['threshold'],
                    'enabled' => (bool) $data['enabled'],
                ]);

                return response()->json(['ok' => true]);
            });

            // Re-evaluate every member against current badge rules (no notifications).
            Route::post('/rescan', function () {
                User::query()->orderBy('id')->chunk(200, function ($users) {
                    foreach ($users as $u) {
                        Awarder::evaluate($u, false);
                    }
                });

                return response()->json(['ok' => true]);
            });
        });
    }

    private static function tablesReady(): bool
    {
        try {
            return \Illuminate\Support\Facades\Schema::hasTable('badges');
        } catch (\Throwable) {
            return false;
        }
    }

    /** Standalone admin page (raw HTML, like other Convoro extensions). */
    private static function adminPage(): string
    {
        $badges = self::tablesReady()
            ? Badge::orderBy('position')->get()->map(function ($b) {
                $b->holders = DB::table('user_badges')->where('badge_id', $b->id)->count();

                return $b;
            })
            : collect();

        $csrf = csrf_token();
        $criteriaLabels = [
            'posts' => 'posts written',
            'topics' => 'topics started',
            'reactions_received' => 'reactions received',
            'age_days' => 'days as a member',
            'order' => 'among the first N members',
        ];

        $rows = '';
        foreach ($badges as $b) {
            $crit = $criteriaLabels[$b->criteria_type] ?? $b->criteria_type;
            $e = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES);
            $checked = $b->enabled ? 'checked' : '';
            $rows .= <<<ROW
            <div class="card" data-id="{$b->id}">
              <div class="row">
                <input class="emoji" value="{$e($b->emoji)}" maxlength="16" aria-label="Emoji" />
                <input class="name" value="{$e($b->name)}" maxlength="60" aria-label="Name" />
                <input class="color" type="color" value="{$e($b->color)}" aria-label="Color" />
                <label class="toggle"><input class="enabled" type="checkbox" {$checked}/> Enabled</label>
              </div>
              <div class="meta">
                Awarded when a member reaches <input class="threshold" type="number" min="0" value="{$e($b->threshold)}" /> {$e($crit)}.
                <span class="holders">· {$b->holders} member(s) have this</span>
                <button class="save" onclick="saveBadge(this)">Save</button>
                <span class="ok" hidden>✓ saved</span>
              </div>
              <div class="desc">{$e($b->description)}</div>
            </div>
            ROW;
        }

        if ($rows === '') {
            $rows = '<p class="empty">No badges yet — enable the extension and run its migration to seed the defaults.</p>';
        }

        return <<<HTML
        <!doctype html>
        <html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{$csrf}">
        <title>Member Badges — Convoro</title>
        <style>
          :root { color-scheme: light dark; }
          body { font-family: Inter, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; margin: 0; background: #0f1117; color: #e7e9f2; }
          .wrap { max-width: 760px; margin: 0 auto; padding: 32px 20px 64px; }
          h1 { font-size: 22px; margin: 0 0 4px; }
          .sub { color: #9aa0b8; margin: 0 0 20px; font-size: 14px; }
          a.back { color: #8b8bf0; text-decoration: none; font-size: 13px; }
          .card { background: #181a26; border: 1px solid #2a2f46; border-radius: 14px; padding: 14px 16px; margin-bottom: 12px; }
          .row { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
          .emoji { width: 54px; text-align: center; font-size: 18px; }
          .name { flex: 1; min-width: 140px; font-weight: 600; }
          input, button { font: inherit; }
          input[type=text], .emoji, .name, .threshold { background: #0f1117; border: 1px solid #2a2f46; color: #e7e9f2; border-radius: 8px; padding: 7px 9px; }
          .name, .emoji { background: #0f1117; border: 1px solid #2a2f46; color: #e7e9f2; border-radius: 8px; padding: 7px 9px; }
          .color { width: 40px; height: 34px; border: 1px solid #2a2f46; border-radius: 8px; background: transparent; }
          .toggle { font-size: 13px; color: #c4c8db; display: flex; gap: 6px; align-items: center; }
          .threshold { width: 80px; }
          .meta { margin-top: 10px; font-size: 13px; color: #9aa0b8; display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
          .holders { color: #6b7090; }
          .desc { margin-top: 6px; font-size: 12px; color: #6b7090; }
          button { cursor: pointer; border: 0; border-radius: 8px; padding: 7px 14px; font-weight: 600; }
          .save { background: #5b5bd6; color: #fff; margin-left: auto; }
          .rescan { background: #2a2f46; color: #e7e9f2; }
          .ok { color: #34d399; font-size: 13px; }
          .empty { color: #9aa0b8; }
        </style></head>
        <body><div class="wrap">
          <a class="back" href="/admin">← Back to admin</a>
          <h1>🏅 Member Badges</h1>
          <p class="sub">Badges are awarded automatically as members participate. Edit a badge, then hit Save. Use “Re-scan” after changing thresholds so existing members get re-evaluated.</p>
          <p><button class="rescan" onclick="rescan(this)">Re-scan all members</button> <span id="rescanOk" class="ok" hidden>✓ done</span></p>
          {$rows}
        </div>
        <script>
          const CSRF = document.querySelector('meta[name=csrf-token]').content;
          async function post(url, body) {
            const r = await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' }, body: JSON.stringify(body || {}) });
            return r.ok;
          }
          async function saveBadge(btn) {
            const card = btn.closest('.card');
            const ok = await post('/admin/ext/badges/save', {
              id: Number(card.dataset.id),
              name: card.querySelector('.name').value,
              emoji: card.querySelector('.emoji').value,
              color: card.querySelector('.color').value,
              threshold: Number(card.querySelector('.threshold').value),
              enabled: card.querySelector('.enabled').checked,
            });
            const tag = card.querySelector('.ok');
            if (ok) { tag.hidden = false; setTimeout(() => (tag.hidden = true), 1500); }
          }
          async function rescan(btn) {
            btn.disabled = true; btn.textContent = 'Re-scanning…';
            await post('/admin/ext/badges/rescan');
            btn.disabled = false; btn.textContent = 'Re-scan all members';
            const ok = document.getElementById('rescanOk'); ok.hidden = false; setTimeout(() => (ok.hidden = true), 2000);
          }
        </script>
        </body></html>
        HTML;
    }
}
