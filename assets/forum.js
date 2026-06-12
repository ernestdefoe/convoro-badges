// Convoro extension — Member Badges. Renders a member's earned badges on their
// profile (into the `profile:below` slot). Framework-light, themed via CSS vars.
(function () {
  var c = window.Convoro;
  if (!c || typeof c.registerSlot !== 'function') return;
  var V = function (n, f) { return 'rgb(var(--c-' + n + ',' + f + '))'; };
  var T = {
    surface: V('surface', '255 255 255'), ink: V('text', '27 32 48'),
    ink2: V('text-2', '74 81 104'), muted: V('muted', '138 144 166'),
    line: V('border', '230 232 240'),
  };
  function tr(k) { return c.t ? c.t(k) : k; }

  c.registerSlot('profile:below', {
    ext: 'convoro-badges',
    order: 10,
    mount: function (el, ctx) {
      var userId = ctx && ctx.props && ctx.props.userId;
      if (!userId) return;

      fetch('/api/ext/badges/user/' + encodeURIComponent(userId), { headers: { Accept: 'application/json' } })
        .then(function (r) { return r.ok ? r.json() : []; })
        .then(function (badges) {
          if (!Array.isArray(badges) || !badges.length) return; // hide if none

          var box = document.createElement('div');
          box.style.cssText = 'margin-top:24px;border:1px solid ' + T.line + ';background:' + T.surface + ';border-radius:var(--c-radius,12px);box-shadow:0 1px 2px rgba(0,0,0,.05);overflow:hidden';

          var h = document.createElement('h4');
          h.textContent = tr('Badges');
          h.style.cssText = 'margin:0;padding:12px 16px;border-bottom:1px solid ' + T.line + ';font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:.03em;color:' + T.muted;
          box.appendChild(h);

          var wrap = document.createElement('div');
          wrap.style.cssText = 'display:flex;flex-wrap:wrap;gap:8px;padding:16px';
          badges.forEach(function (b) {
            var color = b.color || '#5b5bd6';
            var chip = document.createElement('span');
            chip.title = b.description || '';
            chip.style.cssText = 'display:inline-flex;align-items:center;gap:7px;padding:6px 12px 6px 10px;border-radius:999px;font-size:13px;font-weight:600;color:' + color + ';background:' + color + '1f;border:1px solid ' + color + '40';
            var em = document.createElement('span');
            em.textContent = b.emoji || '🏅';
            em.style.cssText = 'font-size:15px;line-height:1';
            var nm = document.createElement('span');
            nm.textContent = b.name;
            nm.style.color = T.ink;
            chip.appendChild(em); chip.appendChild(nm);
            wrap.appendChild(chip);
          });
          box.appendChild(wrap);
          el.appendChild(box);
        })
        .catch(function () {});
    },
  });
})();
