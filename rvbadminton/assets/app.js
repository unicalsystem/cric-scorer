/* RV Badminton - the phone app. One page; the address after # decides what is shown.
   #/ matches · #/m/12 a match · #/new · #/tours · #/t/3 · #/tour-new · #/players · #/p/9 · #/me */
'use strict';
const S = { boot: null, user: null, poll: null, voice: localStorage.getItem('bd_voice') === '1' };
const $ = (s, el = document) => el.querySelector(s);
const esc = s => String(s == null ? '' : s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const view = () => $('#view');
const IC = {
  back: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 5l-7 7 7 7"/></svg>',
  more: '<svg viewBox="0 0 24 24" fill="currentColor"><circle cx="5" cy="12" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="19" cy="12" r="2"/></svg>',
  share: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 15V4m0 0L8 8m4-4l4 4M5 13v5a2 2 0 002 2h10a2 2 0 002-2v-5"/></svg>',
  plus: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>',
  ball: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"><path d="M9.5 14.5L4 5l5 1 3-3 3 3 5-1-5.5 9.5"/><path d="M9.5 14.5h5M12 3v11.5"/><path d="M9.5 14.5a2.5 2.5 0 005 0" fill="currentColor"/><path d="M12 17v4"/></svg>',
  shuttle: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 13L3.5 3.5l4.2 1.2L12 1.5l4.3 3.2 4.2-1.2L16 13z" fill="#fff" stroke="#0f2440" stroke-width="1.3" stroke-linejoin="round"/><path d="M8 13h8M12 1.5V13M7.7 4.7L10 13M16.3 4.7L14 13" stroke="#0f2440" stroke-width=".9"/><path d="M8 13h8v1a4 4 0 01-8 0z" fill="#dcf03a" stroke="#0f2440" stroke-width="1.3"/></svg>',
  cup: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 4h10v5a5 5 0 01-10 0V4zM7 6H4v1a3 3 0 003 3M17 6h3v1a3 3 0 01-3 3M12 14v4M8 20h8"/></svg>',
  people: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8" r="3.2"/><path d="M3 19c.6-3.2 3-5 6-5s5.4 1.800 6 5M16 5.200a3.200 3.200 0 010 5.600M18 14.300c1.600.800 2.700 2.400 3 4.700"/></svg>',
  me: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="3.600"/><path d="M4.500 20c1-3.800 4-6 7.500-6s6.500 2.200 7.500 6"/></svg>',
  mark: '<svg class="mark" viewBox="0 0 40 40" aria-hidden="true"><circle cx="20" cy="20" r="19" fill="#dcf03a"/><path d="M15 25l-5-14 5 2 5-5 5 5 5-2-5 14z" fill="#fff" stroke="#0f2440" stroke-width="1.6" stroke-linejoin="round"/><path d="M15 25h10v1.500a5 5 0 01-10 0z" fill="#0f2440"/></svg>',
};

/* ---------- install as an app ---------- */
let installEvt = null;
const standalone = () => matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;
window.addEventListener('beforeinstallprompt', e => { e.preventDefault(); installEvt = e; document.querySelectorAll('[data-install]').forEach(b => b.hidden = false); });
window.addEventListener('appinstalled', () => { installEvt = null; document.querySelectorAll('[data-install]').forEach(b => b.hidden = true); toast('RV Badminton is installed'); });
function install() {
  if (installEvt) { installEvt.prompt(); installEvt.userChoice.finally(() => { installEvt = null; }); return; }
  const ios = /iphone|ipad|ipod/i.test(navigator.userAgent);
  sheet(`<h2>Add RV Badminton to your home screen</h2><p class="muted">${ios ? 'In Safari, tap the Share button, then “Add to Home Screen”.' : 'Open your browser menu (⋮) and choose “Install app” or “Add to Home screen”.'} It then opens full screen like any other app.</p><div class="btns"><button class="btn pri" data-close>OK</button></div>`);
}
if ('serviceWorker' in navigator) window.addEventListener('load', () => navigator.serviceWorker.register('sw.js').catch(() => {}));

/* ---------- plumbing ---------- */
async function api(a, body, params) {
  const qs = new URLSearchParams({ a, ...(params || {}) });
  const r = await fetch('api.php?' + qs, body ? { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body), credentials: 'same-origin' } : { credentials: 'same-origin' });
  let j = null;
  try { j = await r.json(); } catch (e) { /* not JSON */ }
  if (!r.ok || !j || j.error) { const err = new Error((j && j.error) || 'Could not reach the server. Check your connection and try again.'); err.status = r.status; throw err; }
  return j;
}
let toastT;
function toast(msg, bad) { const t = $('#toast'); t.textContent = msg; t.className = 'on' + (bad ? ' err' : ''); clearTimeout(toastT); toastT = setTimeout(() => t.className = '', bad ? 4200 : 2400); }
/** Run an action; show its error as a toast */
async function run(fn) { try { return await fn(); } catch (e) { if (e.status === 401) { toast('Sign in to do that', true); go('#/me'); } else toast(e.message, true); } }
function go(h) { if (location.hash === h) route(); else location.hash = h; }
function sheet(html) {
  const d = document.createElement('dialog');
  d.className = 'sheet';
  d.innerHTML = `<div class="pad">${html}</div>`;
  document.body.appendChild(d);
  d.addEventListener('close', () => d.remove());
  d.addEventListener('click', e => { if (e.target === d || e.target.closest('[data-close]')) d.close(); });
  d.showModal();
  return d;
}
function confirmSheet(title, text, yes, danger) {
  return new Promise(res => {
    const d = sheet(`<h2>${esc(title)}</h2><p class="muted">${esc(text)}</p><div class="btns"><button class="btn" data-close>Cancel</button><button class="btn ${danger ? 'danger' : 'pri'}" id="yes">${esc(yes)}</button></div>`);
    let ok = false;
    $('#yes', d).onclick = () => { ok = true; d.close(); };
    d.addEventListener('close', () => res(ok));
  });
}
function setTop(title, sub, opts = {}) {
  const logo = BD.logo ? `<img class="mark" src="${esc(BD.logo)}" alt="">` : IC.mark;
  $('#top').innerHTML = (opts.back ? `<button class="ib" id="tb-back" aria-label="Back">${IC.back}</button>` : logo)
    + `<h1>${esc(title)}${sub ? `<small>${esc(sub)}</small>` : ''}</h1>` + (opts.right || '');
  if (opts.back) $('#tb-back').onclick = () => go(opts.back);
  document.title = title + ' · ' + BD.name;
}
function setTabs(on) {
  const t = [['#/', 'Matches', IC.ball, 'home'], ['#/tours', 'Tournaments', IC.cup, 'tours'], ['#/players', 'Players', IC.people, 'players'], ['#/me', S.user ? 'Me' : 'Sign in', IC.me, 'me']];
  $('#tabs').className = on ? '' : 'hidden';
  $('#app').className = on ? '' : 'bare';
  $('#tabs').innerHTML = t.map(x => `<a href="${x[0]}" class="${x[3] === on ? 'on' : ''}"${x[3] === on ? ' aria-current="page"' : ''}>${x[2]}<span>${x[1]}</span></a>`).join('');
}
const when = s => { if (!s) return ''; const d = new Date(s.replace(' ', 'T')); const today = new Date().toDateString() === d.toDateString();
  return (today ? 'Today' : d.toLocaleDateString('en-IN', { day: 'numeric', month: 'short' })) + ', ' + d.toLocaleTimeString('en-IN', { hour: 'numeric', minute: '2-digit' }); };
const day = s => s ? new Date(s.replace(' ', 'T')).toLocaleDateString('en-IN', { day: 'numeric', month: 'short' }) : '';

/* ---------- teams ---------- */
const team = id => (S.boot.teams || []).find(t => t.id === id) || null;
const badge = (id, cls = '') => { const t = team(id); return t ? `<img class="tb ${cls}" src="${esc(t.logo)}" alt="${esc(t.name)}" title="${esc(t.name)}" loading="lazy">` : ''; };
const teamOptions = sel => `<option value="">No team</option>` + (S.boot.teams || []).map(t => `<option value="${t.id}" ${t.id === sel ? 'selected' : ''}>${esc(t.name)}</option>`).join('');

/* ---------- score pieces ---------- */
function gameCell(g, side, cls = 's') {
  const mine = side ? g.b : g.a, theirs = side ? g.a : g.b;
  return `<span class="${cls} ${mine > theirs ? 'hi' : 'lo'}">${mine}</span>`;
}
/** A match in a list */
function matchRow(m, extra = '') {
  const mini = m.mini || { games: [], pts: [0, 0] };
  const live = m.status === 'live', done = m.status === 'done';
  const ctx = [m.tour, m.round_name, m.court].filter(Boolean).join(', ');
  const right = live ? '<span class="live">Live</span>' : done ? day(m.finished_at) : (m.sched_at ? when(m.sched_at) : 'Not started');
  const line = side => {
    const nm = side ? m.b : m.a;
    const cls = done ? (m.winner === side ? 'won' : 'lost') : '';
    let cells = mini.games.map(g => gameCell(g, side)).join('');
    if (live) cells += `<span class="pt">${mini.pts[side]}</span>`;
    if (done && !mini.games.length && m.winner === side) cells = `<span class="small muted">${esc(m.summary || '')}</span>`;
    return `<div class="line ${cls}"><span class="srv ${live && mini.server === side ? 'on' : ''}"></span>${badge((m.teams || [])[side])}<span class="nm ${nm ? '' : 'tbd'}">${esc(nm || 'To be decided')}</span>${cells}${done ? `<span class="tick">${m.winner === side ? '✓' : ''}</span>` : ''}</div>`;
  };
  return `<a class="mrow" href="#/m/${m.id}"><div class="meta"><span>${esc(ctx || (m.doubles ? 'Doubles' : 'Singles'))}</span><span>${right}</span></div>${line(0)}${line(1)}${extra}</a>`;
}

/* ---------- Matches (home) ---------- */
async function vHome() {
  setTop(BD.name, BD.club);
  setTabs('home');
  const d = await api('home');
  const sec = (title, list, empty) => list.length || empty ? `<h2 class="sec">${title} <span class="n">${list.length || ''}</span></h2>${list.map(m => matchRow(m)).join('') || `<p class="empty">${empty}</p>`}` : '';
  const none = !d.live.length && !d.next.length && !d.done.length;
  const hero = BD.logo ? `<div class="hero"><img src="${esc(BD.logo)}" alt="${esc(BD.club)}"><div class="t"><b>${esc(BD.name)}</b><span>${esc(BD.club)}, Nacharam</span>
      <button class="btn sm" data-install ${standalone() ? 'hidden' : ''}>Install the app</button></div></div>` : '';
  const strip = (S.boot.teams || []).length ? `<div class="tstrip">${S.boot.teams.map(t => `<a href="#/team/${t.id}"><img src="${esc(t.logo)}" alt=""><span>${esc(t.name)}</span></a>`).join('')}</div>` : '';
  view().innerHTML = hero + strip + (none ? `<div class="card"><h2 class="sec">No matches yet</h2><p class="muted">Start the first one: tap “New match”, type the players' names, and score each rally by tapping the side of the court that won it.</p></div>` :
    sec('Live now', d.live, '') + sec('Up next', d.next, '') + sec('Results', d.done, d.live.length || d.next.length ? '' : 'No finished matches yet.'))
    + `<a class="btn fab" href="#/new">${IC.plus} New match</a>`;
  const ib = $('[data-install]'); if (ib) ib.onclick = install;
  S.poll = setInterval(async () => { if (document.hidden) return; try { const n = await api('home'); if (JSON.stringify(n) !== JSON.stringify(d) && location.hash.replace(/^#?\/?$/, '') === '') route(); } catch (e) { /* next time */ } }, 15000);
}

/* ---------- Match format ---------- */
/* Every rule that can differ between tournaments is a setting. Defaults are the club rule sheet: 21 points, win by 2 from
   20-all, sudden death at 30 (30-29), interval and (in the deciding game) change of ends when the leader reaches 11. */
const FORMAT_DEFAULT = { best: 1, pts: 21, two: true, cap: 30, mid: 11 };
const KO_DEFAULT = { ...FORMAT_DEFAULT, best: 3 };
function formatFields(x = '', f = FORMAT_DEFAULT, bestLabel = 'Games in the match') {
  const o = (v, cur, label) => `<option value="${v}" ${String(v) === String(cur) ? 'selected' : ''}>${label}</option>`;
  const deuce = !f.two ? 'none' : f.cap ? 'cap' : 'two';
  return `<div class="fmt" data-fmt="${x}">
    <label class="f">${bestLabel}<select data-f="best">${o(1, f.best, 'One game')}${o(3, f.best, 'Best of 3 games')}${o(5, f.best, 'Best of 5 games')}</select></label>
    <label class="f">Points to win a game<select data-f="pts">${[21, 15, 11, 25, 30].map(n => o(n, f.pts, n + ' points' + (n === 21 ? ' (standard)' : ''))).join('')}</select></label>
    <label class="f">At ${'<span data-f="pm1">' + (f.pts - 1) + '</span>'}-all<select data-f="deuce">${o('cap', deuce, 'Win by 2, with a sudden-death cap')}${o('two', deuce, 'Win by 2, no cap')}${o('none', deuce, 'No setting: first to the points wins')}</select></label>
    <label class="f" data-f="capl">Cap: whoever reaches it wins<input data-f="cap" type="number" inputmode="numeric" min="2" max="99" value="${f.cap || f.pts + 9}"><span class="hint" data-f="caphint"></span></label>
    <label class="f">Interval${'<span data-f="endsx"></span>'} when the leading score reaches<input data-f="mid" type="number" inputmode="numeric" min="0" max="49" value="${f.mid}"><span class="hint">60-second interval. 0 = no interval.</span></label></div>`;
}
function wireFormat(root = document) {
  root.querySelectorAll('.fmt').forEach(el => {
    const g = k => el.querySelector(`[data-f="${k}"]`);
    let lastPts = +g('pts').value;
    const sync = e => {
      const p = +g('pts').value;
      if (e && e.target === g('pts')) {   // a new game length brings its usual cap and interval, e.g. 15 → cap 21, interval at 8
        if (+g('cap').value === lastPts + 9 || +g('cap').value <= p) g('cap').value = p + 9;
        if (+g('mid').value === Math.ceil(lastPts / 2) || +g('mid').value >= p) g('mid').value = Math.ceil(p / 2);
        lastPts = p;
      }
      g('pm1').textContent = p - 1;
      g('capl').hidden = g('deuce').value !== 'cap';
      g('caphint').textContent = `At ${+g('cap').value - 1}-all the next rally wins the game (${g('cap').value}-${+g('cap').value - 1}).`;
      g('endsx').textContent = g('best').value === '1' ? ' and change of ends' : ' (and change of ends in the deciding game)';
    };
    el.onchange = sync; el.oninput = e => { if (e.target.type === 'number') sync(); }; sync();
  });
}
function readFormat(x = '', root = document) {
  const el = root.querySelector(`.fmt[data-fmt="${x}"]`), g = k => el.querySelector(`[data-f="${k}"]`).value;
  const d = g('deuce'), pts = +g('pts');
  return { best: +g('best'), pts, two: d !== 'none', cap: d === 'cap' ? Math.max(pts + 1, +g('cap') || pts + 9) : 0, mid: Math.max(0, Math.min(pts - 1, +g('mid') || 0)) };
}

/* ---------- New match ---------- */
async function vNew() {
  setTop('New match', '', { back: '#/' });
  setTabs(null);
  if (!S.user) { view().innerHTML = `<div class="card"><p>Sign in to start and score a match.</p><p style="margin-top:12px"><a class="btn pri" href="#/me">Sign in</a></p></div>`; return; }
  const pl = (await api('players')).players;
  let dbl = false;
  const draw = () => {
    const inp = (id, ph) => `<input id="${id}" list="pl" autocomplete="off" autocapitalize="words" placeholder="${ph}" maxlength="80" value="${esc($('#' + id) ? $('#' + id).value : '')}">`;
    const tsel = id => (S.boot.teams || []).length ? `<select id="${id}" aria-label="Team">${teamOptions($('#' + id) ? +$('#' + id).value : 0).replace('No team', 'Team (optional)')}</select>` : '';
    $('#sides').innerHTML = `<label class="f">${dbl ? 'Pair A' : 'Player A'}${inp('a1', 'Name')}${dbl ? inp('a2', 'Partner') : ''}${tsel('ta')}</label>
      <label class="f">${dbl ? 'Pair B' : 'Player B'}${inp('b1', 'Name')}${dbl ? inp('b2', 'Partner') : ''}${tsel('tb')}</label>`;
    const known = Object.fromEntries(pl.map(p => [p.name.toLowerCase(), p.team_id]));   // a known player brings their team along
    [['a1', 'ta'], ['b1', 'tb']].forEach(([i, t]) => { if ($('#' + t)) $('#' + i).onchange = () => { const k = known[$('#' + i).value.trim().toLowerCase()]; if (k) $('#' + t).value = k; }; });
  };
  view().innerHTML = `<datalist id="pl">${pl.map(p => `<option value="${esc(p.name)}">`).join('')}</datalist>
    <div class="seg" role="group" aria-label="Match type"><button id="sg" aria-pressed="true">Singles</button><button id="db" aria-pressed="false">Doubles</button></div>
    <div id="sides"></div>
    ${formatFields()}
    <label class="f">Serving first (won the toss)<select id="first"><option value="0">Side A</option><option value="1">Side B</option></select><span class="hint">You can change this on the match screen until the first rally.</span></label>
    <label class="f">Court <span class="muted">(optional)</span><input id="court" maxlength="40" placeholder="Court 1"></label>
    <p style="margin-top:22px"><button class="btn pri wide" id="go">Create match</button></p>`;
  draw(); wireFormat();
  const pick = d => { dbl = d; $('#sg').setAttribute('aria-pressed', !d); $('#db').setAttribute('aria-pressed', d); draw(); };
  $('#sg').onclick = () => pick(false); $('#db').onclick = () => pick(true);
  $('#go').onclick = () => run(async () => {
    const v = id => ($('#' + id) ? $('#' + id).value.trim() : '');
    if (!v('a1') || !v('b1') || (dbl && (!v('a2') || !v('b2')))) return toast('Fill in every player\'s name', true);
    const r = await api('match_new', { doubles: dbl, a: dbl ? v('a1') + ' / ' + v('a2') : v('a1'), b: dbl ? v('b1') + ' / ' + v('b2') : v('b1'),
      format: readFormat(), first_srv: +$('#first').value, court: v('court'), team_a: +v('ta') || 0, team_b: +v('tb') || 0 });
    go('#/m/' + r.id);
  });
}

/* ---------- A match: scoreboard, court, stats ---------- */
function say(text) {
  if (!S.voice || !('speechSynthesis' in window) || !text) return;
  try { speechSynthesis.cancel(); const u = new SpeechSynthesisUtterance(text); u.rate = 1.05; u.lang = 'en-IN'; speechSynthesis.speak(u); } catch (e) { /* no voice on this phone */ }
}
/** What the umpire would say after a rally */
function callText(m) {
  const st = m.state, nm = side => (side ? m.b : m.a);
  if (st.over) return `Match won by ${nm(st.winner)}, ${(m.summary || '').replace(/-/g, ' ')}`;
  if (st.brk) { const g = st.games[st.games.length - 1], w = g.a > g.b ? 0 : 1; return `Game won by ${nm(w)}, ${Math.max(g.a, g.b)} ${Math.min(g.a, g.b)}. ${st.call}`; }
  return st.call.replace(/^love all, play$/, 'Love all, play');
}
/** Point-of-play banner: what matters right now */
function flagHtml(m) {
  const st = m.state, nm = side => esc(side ? m.b : m.a);
  if (st.over || m.status !== 'live') return '';
  if (st.brk) { const g = st.games[st.games.length - 1], w = g.a > g.b ? 0 : 1; return `<div class="flag warm">Game ${st.games.length} to ${nm(w)}, ${Math.max(g.a, g.b)}–${Math.min(g.a, g.b)}. ${st.games.length ? '2-minute break, change ends' : ''}</div>`; }
  if (st.interval) return `<div class="flag warm">Interval: 60 seconds${st.ends ? '. Change ends' : ''}</div>`;
  const f = st.flag;
  if (f && f.k === 'both') return `<div class="flag hot">${st.pts[0]} all: next rally wins the game</div>`;
  if (f) return `<div class="flag hot">${f.k === 'mp' ? 'Match point' : 'Game point'}, ${nm(f.side)}</div>`;
  if (st.setting) return `<div class="flag">${st.pts[0]} all: win by 2${st.format.cap ? `, ${st.format.cap - 1}-all decides` : ''}</div>`;
  if (st.srvOver) return '<div class="flag">Service over</div>';
  const label = st.format.best === 1 ? '' : st.deciding ? 'Deciding game' : `Game ${st.game}`;
  return `<div class="flag">${[label, m.court ? esc(m.court) : ''].filter(Boolean).join(', ') || (m.doubles ? 'Doubles' : 'Singles')}</div>`;
}
async function vMatch(id) {
  setTabs(null);
  let m = (await api('match', null, { id })).match, tab = 'stats', busy = false;
  const pref = k => localStorage.getItem('bd_' + k) === '1';   // rally tags are off unless switched on
  const back = () => (m.tour_id ? '#/t/' + m.tour_id : '#/');
  const shareBtn = `<button class="ib" id="share" aria-label="Share this match">${IC.share}</button>`;
  const paint = () => {
    const st = m.state, live = !st.over, can = m.can_score;
    setTop(`${m.a} v ${m.b}`, [m.tour, m.round_name, m.format_label].filter(Boolean).join(', '), { back: back(), right: shareBtn + (can ? `<button class="ib" id="menu" aria-label="Match options">${IC.more}</button>` : '') });
    const bl = side => `<div class="bl ${st.over && st.winner === side ? 'won' : ''}"><span class="srv ${live && st.server && st.server[0] === side ? 'on' : ''}"></span>
      ${badge(m.teams[side], 'lg')}<span class="nm">${esc(side ? m.b : m.a)}${team(m.teams[side]) ? `<small>${esc(team(m.teams[side]).name)}</small>` : ''}</span>${st.games.map(g => gameCell(g, side)).join('')}
      ${live ? `<span class="pt">${st.pts[side]}</span>` : ''}</div>`;
    const status = m.status === 'live' ? '<span class="live">Live</span>' : m.status === 'done' ? 'Finished ' + day(m.finished_at) : (m.sched_at ? when(m.sched_at) : 'Not started');
    const result = st.over ? `<div class="result">${badge(m.teams[st.winner], 'xl')}<div class="who">${esc(st.winner ? m.b : m.a)} won</div><div class="sum">${esc(m.summary || '')}${st.outcome === 'ret' ? ' (opponent retired)' : st.outcome === 'wo' ? ' (walkover)' : st.outcome === 'def' ? ' (opponent disqualified)' : ''}</div></div>` : '';
    // the court: side B plays from the top, side A from the bottom. Each player's right court is on their own right,
    // so A's right is on the right of the screen and B's right on the left.
    let court = '';
    const waiting = !m.a || !m.b;
    if (can && live && !waiting) {
      const srv = st.server, rc = st.receiver, right = st.court === 'right';
      const x = srv[0] === 0 ? (right ? 75 : 25) : (right ? 25 : 75);
      const who = (side, i) => m.names[side][i] || (side ? m.b : m.a);
      const tag = (side, i) => srv[0] === side && srv[1] === i ? ' srv' : rc && rc[0] === side && rc[1] === i ? ' rcv' : '';
      const people = side => {
        if (!m.doubles) {
          const what = srv[0] === side ? `serving from the ${st.court} court` : `receiving in the ${st.court} court`;
          return `<span class="nm">${esc(side ? m.b : m.a)}</span><span class="do">${what}</span>`;
        }
        const rIdx = st.pos[side], lIdx = 1 - rIdx;   // partner in the right court, partner in the left court
        const cells = [[lIdx, 'left'], [rIdx, 'right']];
        if (side === 1) cells.reverse();                   // B faces the other way: their left is on the screen's right
        return `<span class="pair">${cells.map(([i, c]) => `<span class="p${tag(side, i)}"><b>${esc(who(side, i))}</b><i>${tag(side, i) === ' srv' ? 'serving' : tag(side, i) === ' rcv' ? 'receiving' : c + ' court'}</i></span>`).join('')}</span>`;
      };
      const half = (side, cls) => `<button class="half ${cls}" data-w="${side}" aria-label="Rally won by ${esc(side ? m.b : m.a)}">${badge(m.teams[side], 'ct')}${people(side)}</button>`;
      const tags = pref('tags') && m.last_k !== null && m.last_k !== 'sf' ? `<div class="tags"><span>Last rally</span>${[['win', 'Winner'], ['ue', 'Unforced error'], ['fl', 'Out / net']].map(t => `<button class="chip" data-tag="${t[0]}" aria-pressed="${m.last_k === t[0]}">${t[1]}</button>`).join('')}</div>` : '';
      court = `<div class="court" id="court"><div class="in">${half(1, 't')}<div class="net"></div>${half(0, 'b')}</div><span class="ball ${srv[0] ? 't' : 'b'}" style="left:calc(${x}% - 11px)">${IC.shuttle}</span></div>
        <div class="acts"><button class="btn" data-k="sf">Service fault</button><button class="btn" data-ev="let">Let</button><button class="btn" id="undo" ${m.ev || st.over ? '' : 'disabled'}>Undo</button></div>${tags}`;
    } else if (can && st.over) court = `<div class="acts" style="grid-template-columns:1fr"><button class="btn" id="undo">${st.manual ? 'Clear this result' : 'Undo the last rally'}</button></div>`;
    else if (waiting) court = '<div class="card setup"><p class="muted">This match starts once the previous round is finished.</p></div>';
    // doubles: before the first rally of every game each pair says who starts in the right service court
    const order = () => [0, 1].map(side => `<div class="row" style="margin-top:8px"><span class="grow small">${esc(side ? m.b : m.a)}: in the right court</span><div class="seg" style="flex:1.4">${[0, 1].map(i => `<button data-pos="${side}${i}" aria-pressed="${st.ord[side] === i}">${esc(m.names[side][i])}</button>`).join('')}</div></div>`).join('')
      + `<p class="small muted" style="margin-top:8px">The serving pair's right-court player serves first; the opponent in the right court receives.</p>`;
    const newGame = can && live && m.doubles && m.status === 'live' && st.gameStart ? `<div class="card setup"><h3>Game ${st.game}: positions</h3>${order()}</div>` : '';
    const setup = can && m.status === 'scheduled' && !waiting ? `<div class="card setup"><h3>Before the first rally</h3>
        <div class="row"><span class="grow">Serving first</span><div class="seg" style="flex:1.4"><button data-first="0" aria-pressed="${m.first_srv === 0}">${esc(m.a)}</button><button data-first="1" aria-pressed="${m.first_srv === 1}">${esc(m.b)}</button></div></div>
        ${m.doubles ? order() : ''}
        <p class="small muted" style="margin-top:8px">Tap the side of the court that wins each rally. Played already? <a href="#" id="typed">Enter the final score</a>.</p></div>` : '';
    // stats / rally by rally
    const s = st.stats, pct = (a, b) => b > 0 ? `${Math.round(100 * a / b)}%` : '–';
    const stats = st.manual ? '<p class="empty">This result was entered after the match, so there are no rally-by-rally stats.</p>' : !st.n ? '<p class="empty">Stats appear once the match starts.</p>' :
      `<table class="st"><thead><tr><td>${esc(m.a)}</td><td class="k"></td><td>${esc(m.b)}</td></tr></thead><tbody>${[
        ['Points won', s[0].pts, s[1].pts], ['Won on own serve', `${s[0].sw}/${s[0].sp}`, `${s[1].sw}/${s[1].sp}`],
        ['Won on opponent\'s serve', s[0].pts - s[0].sw, s[1].pts - s[1].sw], ['Serve held', pct(s[0].sw, s[0].sp), pct(s[1].sw, s[1].sp)],
        ['Most points in a row', s[0].run, s[1].run], ['Biggest lead', s[0].lead, s[1].lead],
        ...(s[0].gpc + s[1].gpc ? [['Game points saved', s[0].gps, s[1].gps]] : []),
        ['Service faults', s[0].sf, s[1].sf],
        ...(s[0].win + s[1].win + s[0].ue + s[1].ue + s[0].fl + s[1].fl ? [['Winners', s[0].win, s[1].win], ['Unforced errors', s[0].ue, s[1].ue], ['Out / net faults', s[0].fl, s[1].fl]] : []),
        ...(s[0].lets + s[1].lets ? [['Lets', s[0].lets, s[1].lets]] : []),
      ].map(r => `<tr><td>${r[1]}</td><td class="k">${r[0]}</td><td>${r[2]}</td></tr>`).join('')}</tbody></table>`;
    let pbp = '';
    st.log.forEach((g, gi) => {
      if (!g.p) return;
      let a = 0, b = 0;
      const fin = st.games[gi];
      pbp += `<h4>Game ${gi + 1}${fin ? `: ${fin.a}–${fin.b} to ${esc(fin.a > fin.b ? m.a : m.b)}` : ' (in play)'}</h4><div class="rally">` + [...g.p].map(c => { if (c === '0') a++; else b++; return `<i class="${c === '0' ? '' : 'o'}" title="${a}–${b}">${c === '0' ? a : b}</i>`; }).join('') + '</div>';
    });
    if (pbp) pbp += `<p class="small muted" style="margin-top:10px">Each mark is a rally, showing the winner's new score. Filled: ${esc(m.a)}. Ring: ${esc(m.b)}.</p>`;
    view().innerHTML = `<div class="mgrid"><div><div class="board"><div class="ctx"><span>${esc(m.doubles ? 'Doubles' : 'Singles')}${m.court ? ', ' + esc(m.court) : ''}</span><span>${status}</span></div>${bl(0)}${bl(1)}</div>
      ${result}${flagHtml(m)}${setup}${newGame}${court}</div>
      <div><div class="tabs2" role="tablist"><button role="tab" aria-selected="${tab === 'stats'}" data-tab="stats">Stats</button><button role="tab" aria-selected="${tab === 'pbp'}" data-tab="pbp">Rally by rally</button></div>
      <div class="${tab === 'pbp' ? 'pbp' : ''}">${tab === 'stats' ? stats : (pbp || '<p class="empty">Rallies appear here as they are played.</p>')}</div>
      ${!can && !S.user && live ? '<p class="small muted" style="margin-top:18px">Scoring this match? <a href="#/me">Sign in</a>.</p>' : ''}</div></div>`;
  };
  const act = async (a, body) => {
    if (busy) return; busy = true;
    const c = $('#court'); if (c) c.classList.add('busy');
    try { m = (await api(a, { id, n: m.ev, ...body })).match; if (navigator.vibrate) navigator.vibrate(12); if (a === 'point') say(callText(m)); }
    catch (e) { toast(e.message, true); if (e.status === 401) S.user = null; try { m = (await api('match', null, { id })).match; } catch (x) { /* keep what we have */ } }
    busy = false; paint();
    if (a === 'ev' && body.t === 'let') toast('Let: replay the rally');
    if (a === 'point' || a === 'undo') view().querySelectorAll('.bl .pt').forEach(el => el.classList.add('pop'));
  };
  view().onclick = e => {
    const t = e.target.closest('button,a'); if (!t) return;
    if (t.dataset.w !== undefined) return act('point', { w: +t.dataset.w });
    if (t.dataset.k === 'sf') return act('point', { w: 1 - m.state.server[0], k: 'sf' });
    if (t.id === 'undo') return act('undo', {});
    if (t.dataset.ev) return act('ev', { t: t.dataset.ev });
    if (t.dataset.tag) return act('tag', { k: t.dataset.tag });
    if (t.dataset.pos) { const [side, i] = t.dataset.pos, r = [...m.state.ord]; r[+side] = +i; return act('ev', { t: 'ord', r }); }
    if (t.dataset.tab) { tab = t.dataset.tab; return paint(); }
    if (t.dataset.first !== undefined) return act('match_edit', { first_srv: +t.dataset.first });
    if (t.id === 'typed') { e.preventDefault(); return typedResult(); }
  };
  const typedResult = () => {
    const n = m.state.format.best;
    const d = sheet(`<h2>Enter the final score</h2><p class="muted small">${esc(m.format_label)}. Leave unplayed games empty.</p>
      <div class="sets-in"><span></span><span class="hd">${esc(m.a)}</span><span class="hd">${esc(m.b)}</span>
      ${Array.from({ length: n }, (_, i) => `<span>${n === 1 ? 'Score' : 'Game ' + (i + 1)}</span><input inputmode="numeric" maxlength="2" data-s="${i}a" aria-label="Game ${i + 1}, ${esc(m.a)}"><input inputmode="numeric" maxlength="2" data-s="${i}b" aria-label="Game ${i + 1}, ${esc(m.b)}">`).join('')}</div>
      <div class="btns"><button class="btn" data-close>Cancel</button><button class="btn pri" id="save">Save result</button></div>`);
    $('#save', d).onclick = () => run(async () => {
      const games = [];
      for (let i = 0; i < n; i++) { const a = $(`[data-s="${i}a"]`, d).value.trim(), b = $(`[data-s="${i}b"]`, d).value.trim(); if (a !== '' || b !== '') games.push([a === '' ? -1 : +a, b === '' ? -1 : +b]); }
      m = (await api('match_result', { id, games, replace: true })).match; d.close(); paint();
    });
  };
  $('#top').onclick = e => {
    const t = e.target.closest('button'); if (!t) return;
    if (t.id === 'share') {
      const url = new URL('m/' + id, document.baseURI).href, text = `${m.a} v ${m.b}${m.summary ? ' ' + m.summary : ''}`;
      if (navigator.share) navigator.share({ title: text, text, url }).catch(() => {});
      else navigator.clipboard.writeText(url).then(() => toast('Link copied'), () => toast(url));
    }
    if (t.id === 'menu') {
      const live = !m.state.over;
      const d = sheet(`<h2>Match options</h2><div class="list">
        ${live ? `<button data-o="typed">Enter the final score</button><button data-o="ret0">${esc(m.b)} retired: ${esc(m.a)} wins</button><button data-o="ret1">${esc(m.a)} retired: ${esc(m.b)} wins</button>
          <button data-o="wo0">Walkover to ${esc(m.a)}</button><button data-o="wo1">Walkover to ${esc(m.b)}</button>
          <button data-o="def0">${esc(m.b)} disqualified: ${esc(m.a)} wins</button><button data-o="def1">${esc(m.a)} disqualified: ${esc(m.b)} wins</button>` : ''}
        ${m.status === 'scheduled' ? '<button data-o="fmt">Match format and rules</button>' : ''}
        <button data-o="tags">${pref('tags') ? 'Stop tagging rallies' : 'Tag rallies (winner, unforced error, out / net)'}</button>
        <button data-o="court">Court and start time</button><button data-o="scorer">Hand scoring to someone else</button>
        <button data-o="voice">${S.voice ? 'Stop reading the score aloud' : 'Read the score aloud'}</button>
        ${m.tour_id ? '' : '<button data-o="del" class="danger">Delete this match</button>'}</div>`);
      d.onclick = ev => run(async () => {
        const o = (ev.target.closest('[data-o]') || {}).dataset; if (!o) return;
        d.close();
        if (o.o === 'typed') return typedResult();
        if (o.o === 'tags') { localStorage.setItem('bd_tags', pref('tags') ? '0' : '1'); return paint(); }
        if (o.o === 'fmt') {
          const s2 = sheet(`<h2>Match format and rules</h2><p class="muted small">These can change until the first rally is played.</p>${formatFields('e', m.format)}
            <div class="btns"><button class="btn" data-close>Cancel</button><button class="btn pri" id="e-save">Save</button></div>`);
          wireFormat(s2);
          $('#e-save', s2).onclick = () => run(async () => { m = (await api('match_edit', { id, format: readFormat('e', s2) })).match; s2.close(); paint(); });
          return;
        }
        if (/^(ret|wo|def)/.test(o.o)) { const w = +o.o.slice(-1); if (await confirmSheet('End the match?', `${w ? m.b : m.a} is recorded as the winner. You can undo this.`, 'End match')) { m = (await api('match_end', { id, w, why: o.o.slice(0, -1) })).match; paint(); } return; }
        if (o.o === 'voice') { S.voice = !S.voice; localStorage.setItem('bd_voice', S.voice ? '1' : '0'); if (S.voice) say('Score announcements on'); return toast(S.voice ? 'The score is read aloud after each rally' : 'Voice off'); }
        if (o.o === 'del') { if (await confirmSheet('Delete this match?', 'The score and stats are removed for everyone. This cannot be undone.', 'Delete match', true)) { await api('match_delete', { id }); go('#/'); } return; }
        if (o.o === 'court') {
          const s2 = sheet(`<h2>Court and start time</h2><label class="f">Court<input id="e-court" maxlength="40" value="${esc(m.court || '')}"></label>
            <label class="f">Start time<input id="e-when" type="datetime-local" value="${esc((m.sched_at || '').replace(' ', 'T').slice(0, 16))}"></label>
            <div class="btns"><button class="btn" data-close>Cancel</button><button class="btn pri" id="e-save">Save</button></div>`);
          $('#e-save', s2).onclick = () => run(async () => { m = (await api('match_edit', { id, court: $('#e-court', s2).value, sched_at: $('#e-when', s2).value })).match; s2.close(); paint(); });
        }
        if (o.o === 'scorer') {
          const s2 = sheet(`<h2>Hand scoring to someone else</h2><p class="muted small">${m.scorer ? esc(m.scorer) + ' can score this match now. ' : ''}They need to have signed in to RV Badminton once. You can still score too.</p>
            <label class="f">Their mobile number or username<input id="e-who" autocapitalize="off" placeholder="98765 43210"></label>
            <div class="btns"><button class="btn" data-close>Cancel</button><button class="btn pri" id="e-save">Save</button></div>`);
          $('#e-save', s2).onclick = () => run(async () => { m = (await api('match_edit', { id, scorer: $('#e-who', s2).value })).match; s2.close(); toast(m.scorer ? m.scorer + ' can now score this match' : 'Scorer removed'); paint(); });
        }
      });
    }
  };
  paint();
  // spectators (and a second scorer) follow along
  S.poll = setInterval(async () => {
    if (document.hidden || busy || m.status === 'done' || document.querySelector('dialog[open]')) return;
    try { const r = await api('match', null, { id, rev: m.rev }); if (r.match && !busy) { m = r.match; paint(); } } catch (e) { /* next time */ }
  }, m.can_score ? 9000 : 3500);
}

/* ---------- Tournaments ---------- */
const KIND = { lk: 'League + play-offs', rr: 'League', ko: 'Knockout' };
async function vTours() {
  setTop('Tournaments', BD.club); setTabs('tours');
  const t = (await api('tours')).tours;
  view().innerHTML = (t.length ? t.map(x => `<a class="mrow" href="#/t/${x.id}"><div class="meta"><span>${KIND[x.kind]}, ${x.doubles ? 'doubles' : 'singles'}</span><span>${x.done} of ${x.total} played</span></div>
      <div class="line"><span class="nm" style="font-size:17px;font-weight:800">${esc(x.name)}</span><span class="muted small">${x.entries} ${x.doubles ? 'pairs' : 'players'}</span></div></a>`).join('')
    : `<div class="card"><h2 class="sec">No tournaments yet</h2><p class="muted">${S.user && S.user.admin ? 'Set one up: name it, list the players, and the fixtures are drawn for you.' : 'Club tournaments appear here once an admin sets one up.'}</p></div>`)
    + (S.user && S.user.admin ? `<a class="btn fab" href="#/tour-new">${IC.plus} New tournament</a>` : '');
}
async function vTour(id) {
  setTabs(null);
  const d = await api('tour', null, { id }), t = d.tour;
  setTop(t.name, `${KIND[t.kind]}, ${t.doubles ? 'doubles' : 'singles'}`, { back: '#/tours', right: t.can_edit ? `<button class="ib" id="tmenu" aria-label="Tournament options">${IC.more}</button>` : '' });
  const league = d.matches.filter(m => m.stage === 0), ko = d.matches.filter(m => m.stage === 1);
  let html = `<p class="small muted" style="margin:0 2px 6px">${t.kind === 'ko' ? (t.final_rounds ? `${esc(t.format_label)}; ${t.final_rounds === 1 ? 'final' : t.final_rounds === 2 ? 'semi-finals and final' : 'quarter-finals onward'}: ${esc(t.ko_label)}` : esc(t.format_label))
    : `League: ${esc(t.format_label)}${t.kind === 'lk' ? `. Play-offs (top ${t.qualify}): ${esc(t.ko_label)}` : ''}`}</p>`;
  const drawKo = () => {
    let h = '<div class="draw">';
    for (let r = 1; r <= t.rounds; r++) {
      const ms = ko.filter(m => m.round === r && m.status !== 'bye');
      const byes = ko.filter(m => m.round === r && m.status === 'bye').length;
      h += `<div class="col"><h4>${esc((ko.find(m => m.round === r) || {}).round_name || '')}</h4><div class="ms">${ms.map(m => matchRow({ ...m, round_name: '' })).join('')}${byes ? `<p class="small muted">${byes} ${byes === 1 ? 'entry has' : 'entries have'} a bye</p>` : ''}</div></div>`;
    }
    return h + '</div><p class="small muted">Swipe sideways to see every round. Winners move on automatically.</p>';
  };
  if (t.kind !== 'ko') {
    if (t.kind === 'lk') {
      html += `<h2 class="sec">Play-offs</h2>` + (ko.length ? drawKo() : `<div class="card"><p class="muted">The top ${t.qualify} ${t.groups > 1 ? `(${t.qualify / t.groups} from each group) ` : ''}go through. The play-offs are drawn as soon as the last league match finishes${t.league_left ? ` (${t.league_left} to go)` : ''}.</p>
        ${t.can_edit ? `<p style="margin-top:10px"><button class="btn sm" id="t-po">Draw the play-offs now</button></p>` : ''}</div>`);
    }
    for (let g = 0; g < t.groups; g++) {
      const rows = d.table.filter(r => r.grp === g), q = t.kind === 'lk' ? t.qualify / t.groups : 0;
      html += `<h2 class="sec">${t.groups > 1 ? 'Group ' + String.fromCharCode(65 + g) : 'League table'}</h2><div class="card" style="padding:6px 10px;overflow-x:auto"><table class="lt"><thead><tr><th></th><th class="nm">${t.doubles ? 'Pair' : 'Player'}</th><th>P</th><th>W</th><th>L</th><th>Pts</th><th>+/−</th><th class="wide">Points</th></tr></thead><tbody>
        ${rows.map((r, i) => `<tr class="${i < q ? 'q' : ''}"><td class="pos">${i + 1}</td><td class="nm">${esc(r.name)}</td><td>${r.p}</td><td>${r.w}</td><td>${r.l}</td><td class="w">${r.mp}</td><td>${r.pd > 0 ? '+' : ''}${r.pd}</td><td class="wide">${r.pf}–${r.pa}</td></tr>`).join('')}</tbody></table></div>`;
    }
    html += `<p class="small muted" style="margin:8px 2px 0">${t.win_pts} points for a win, ${t.loss_pts} for a loss. Level on points: net points difference (points scored minus conceded in all league games), then the result between the sides still level.${t.kind === 'lk' ? ' Highlighted: play-off places.' : ''}</p>`;
    const rounds = [...new Set(league.map(m => m.round))];
    html += rounds.map(r => `<h2 class="sec">Round ${r}</h2>` + league.filter(m => m.round === r).map(m => matchRow({ ...m, round_name: t.groups > 1 ? 'Group ' + String.fromCharCode(65 + m.grp) : '' })).join('')).join('');
  } else html += drawKo();
  view().innerHTML = html;
  const po = async () => { await api('tour_playoffs', { id }); toast('Play-offs drawn'); route(); };
  if ($('#t-po')) $('#t-po').onclick = () => run(async () => { if (!t.league_left || await confirmSheet('Draw the play-offs now?', `${t.league_left} league ${t.league_left === 1 ? 'match is' : 'matches are'} not finished. The play-offs are drawn from the tables as they stand.`, 'Draw play-offs')) await po(); });
  const b = $('#tmenu');
  if (b) b.onclick = () => {
    const s2 = sheet(`<h2>Tournament options</h2><div class="list">${t.kind === 'lk' && ko.length && !t.ko_started ? '<button data-o="redraw">Draw the play-offs again from the tables</button>' : ''}<button data-o="del" class="danger">Delete this tournament</button></div>`);
    s2.onclick = ev => run(async () => {
      const o = (ev.target.closest('[data-o]') || {}).dataset; if (!o) return;
      s2.close();
      if (o.o === 'redraw') return po();
      if (o.o === 'del' && await confirmSheet('Delete this tournament?', 'All its matches and scores are removed for everyone. This cannot be undone.', 'Delete tournament', true)) { await api('tour_delete', { id }); go('#/tours'); }
    });
  };
  S.poll = setInterval(() => { if (!document.hidden && !document.querySelector('dialog[open]') && d.matches.some(m => m.status === 'live')) route(); }, 20000);
}
async function vTourNew() {
  setTop('New tournament', '', { back: '#/tours' }); setTabs(null);
  if (!S.user || !S.user.admin) { view().innerHTML = '<div class="card"><p>Only a club admin can set up a tournament.</p></div>'; return; }
  let kind = 'lk', dbl = false;
  view().innerHTML = `<label class="f">Tournament name<input id="t-name" maxlength="120" placeholder="RV Club Badminton Cup 2026"></label>
    <label class="f">Type</label><div class="seg" style="margin-top:6px"><button data-kind="lk">League + play-offs</button><button data-kind="rr">League</button><button data-kind="ko">Knockout</button></div>
    <p class="small muted" id="t-kind-hint" style="margin-top:6px"></p>
    <div class="seg" style="margin-top:14px"><button data-dbl="0">Singles</button><button data-dbl="1">Doubles</button></div>
    <h2 class="sec" style="margin-top:20px" id="t-f1">League matches</h2>${formatFields('', FORMAT_DEFAULT)}
    <div id="t-ko"><label class="f" id="t-fr-l">Final rounds<select id="t-fr"><option value="0">Same format in every round</option><option value="1">The final uses a different format</option><option value="2" selected>Semi-finals and final use a different format</option><option value="3">Quarter-finals onward use a different format</option></select></label>
      <div id="t-ff"><h2 class="sec" style="margin-top:20px" id="t-f2">Semi-finals and final</h2>${formatFields('g', KO_DEFAULT)}</div></div>
    <div id="t-lg"><label class="f" id="t-groups-l">Groups<select id="t-groups">${[1, 2, 3, 4, 6, 8].map(n => `<option value="${n}">${n === 1 ? 'One group: everyone plays everyone' : n + ' groups'}</option>`).join('')}</select></label>
      <label class="f" id="t-q-l">Play-off places<select id="t-q"><option value="4" selected>Top 4: semi-finals and final</option><option value="2">Top 2: final only</option><option value="8">Top 8: quarter-finals onward</option></select></label>
      <div class="row"><label class="f grow">Points for a win<input id="t-wp" type="number" inputmode="numeric" min="0" max="10" value="2"></label><label class="f grow">Points for a loss<input id="t-lp" type="number" inputmode="numeric" min="0" max="10" value="0"></label></div></div>
    <label class="f"><span id="t-ent-l"></span><textarea id="t-entries" autocapitalize="words" spellcheck="false"></textarea><span class="hint" id="t-ent-h"></span></label>
    <p style="margin-top:22px"><button class="btn pri wide" id="t-go">Create tournament and fixtures</button></p>`;
  const sync = () => {
    view().querySelectorAll('[data-kind]').forEach(b => b.setAttribute('aria-pressed', b.dataset.kind === kind));
    view().querySelectorAll('[data-dbl]').forEach(b => b.setAttribute('aria-pressed', (b.dataset.dbl === '1') === dbl));
    $('#t-kind-hint').textContent = { lk: 'Everyone in a group plays everyone; the top places then play semi-finals and a final.', rr: 'Everyone in a group plays everyone else; a table ranks them.', ko: 'Lose and you are out. The draw is seeded in the order you list the entries; byes go to the top seeds.' }[kind];
    $('#t-lg').style.display = kind === 'ko' ? 'none' : '';
    $('#t-q-l').style.display = kind === 'lk' ? '' : 'none';
    $('#t-ko').style.display = kind === 'rr' ? 'none' : '';
    $('#t-fr-l').style.display = kind === 'ko' ? '' : 'none';
    $('#t-ff').hidden = kind === 'ko' && $('#t-fr').value === '0';
    $('#t-f1').textContent = kind === 'ko' ? 'Early rounds' : 'League matches';
    $('#t-f2').textContent = kind === 'lk' ? 'Play-offs' : ['', 'Final', 'Semi-finals and final', 'Quarter-finals onward'][+$('#t-fr').value];
    $('#t-ent-l').textContent = dbl ? 'Pairs, one per line' : 'Players, one per line';
    $('#t-entries').placeholder = dbl ? 'Asha / Meera\nRavi / Kiran' : 'Asha\nRavi\nKiran';
    $('#t-ent-h').textContent = (dbl ? 'Write each pair as "Name / Name". ' : '') + (kind === 'ko' ? 'List the strongest first: that order is the seeding.' : 'With more than one group, list the strongest first: they are spread across the groups.');
  };
  sync(); wireFormat(); $('#t-fr').onchange = sync;
  view().addEventListener('click', e => { const b = e.target.closest('[data-kind],[data-dbl]'); if (!b) return; if (b.dataset.kind) kind = b.dataset.kind; else dbl = b.dataset.dbl === '1'; sync(); });
  $('#t-go').onclick = () => run(async () => {
    const r = await api('tour_new', { name: $('#t-name').value, kind, doubles: dbl, format: readFormat(), ko_format: readFormat('g'), final_rounds: +$('#t-fr').value,
      qualify: +$('#t-q').value, win_pts: +$('#t-wp').value, loss_pts: +$('#t-lp').value, groups: +$('#t-groups').value, entries: $('#t-entries').value });
    go('#/t/' + r.id);
  });
}

/* ---------- Players ---------- */
async function vPlayers() {
  setTop('Players', BD.club); setTabs('players');
  const p = (await api('players')).players;
  const seg = (S.boot.teams || []).length ? `<div class="seg" style="margin-bottom:12px;max-width:560px"><button aria-pressed="true">Players</button><button aria-pressed="false" onclick="location.hash='#/teams'">Teams</button></div>` : '';
  view().innerHTML = seg + (p.length ? `<div class="card plist" style="padding:2px 14px">${p.map((x, i) => `<a href="#/p/${x.id}"><span class="rk">${x.played ? i + 1 : ''}</span>${badge(x.team_id)}<span class="nm">${esc(x.name)}</span>
      <span class="wl">${x.played ? `${x.won} won, ${x.played - x.won} lost` : '<span class="muted" style="font-weight:500">No matches yet</span>'}</span></a>`).join('')}</div>`
    : '<div class="card"><h2 class="sec">No players yet</h2><p class="muted">Players are added automatically when you type their name into a new match or tournament.</p></div>')
    + (S.user ? `<button class="btn fab" id="p-add">${IC.plus} Add player</button>` : '');
  const b = $('#p-add'); if (b) b.onclick = () => playerSheet(null);
}
function playerSheet(p) {
  const d = sheet(`<h2>${p ? 'Edit player' : 'Add a player'}</h2><label class="f">Name<input id="p-name" maxlength="80" autocapitalize="words" value="${esc(p ? p.name : '')}"></label>
    ${(S.boot.teams || []).length ? `<label class="f">Team<select id="p-t">${teamOptions(p ? p.team_id : 0)}</select></label>` : ''}
    <label class="f">Plays in<select id="p-g"><option value="">Not set</option><option value="M" ${p && p.gender === 'M' ? 'selected' : ''}>Men's events</option><option value="F" ${p && p.gender === 'F' ? 'selected' : ''}>Women's events</option></select></label>
    <div class="btns"><button class="btn" data-close>Cancel</button><button class="btn pri" id="p-save">${p ? 'Save' : 'Add player'}</button></div>
    ${p ? '<p style="margin-top:14px"><button class="btn danger wide sm" id="p-del">Delete player</button></p>' : ''}`);
  $('#p-save', d).onclick = () => run(async () => { await api('player_save', { id: p ? p.id : 0, name: $('#p-name', d).value, gender: $('#p-g', d).value, ...($('#p-t', d) ? { team_id: +$('#p-t', d).value || 0 } : {}) }); d.close(); route(); });
  if (p) $('#p-del', d).onclick = () => run(async () => { await api('player_delete', { id: p.id }); d.close(); go('#/players'); });
}
async function vPlayer(id) {
  setTabs(null);
  const d = await api('player', null, { id }), r = d.record;
  setTop(d.player.name, 'Player record', { back: '#/players', right: d.player.can_edit ? `<button class="ib" id="p-edit" aria-label="Edit player">${IC.more}</button>` : '' });
  const tm = team(d.player.team_id);
  view().innerHTML = (tm ? `<a class="tline" href="#/team/${tm.id}">${badge(tm.id, 'lg')}<span>Plays for <b>${esc(tm.name)}</b></span></a>` : '') + `<div class="stats3"><div><b>${r.played}</b><span>Played</span></div><div><b>${r.won}</b><span>Won</span></div><div><b>${r.played ? Math.round(100 * r.won / r.played) + '%' : '–'}</b><span>Win rate</span></div></div>
    <div class="stats3"><div><b>${r.singles[0]}–${r.singles[1]}</b><span>Singles</span></div><div><b>${r.doubles[0]}–${r.doubles[1]}</b><span>Doubles</span></div><div><b>${r.games[0]}–${r.games[1]}</b><span>Games</span></div></div>
    <h2 class="sec">Matches</h2>${d.matches.map(m => matchRow(m)).join('') || '<p class="empty">No matches played yet.</p>'}`;
  const b = $('#p-edit'); if (b) b.onclick = () => playerSheet(d.player);
}

/* ---------- Teams ---------- */
async function vTeams() {
  setTop('Teams', BD.club); setTabs('players');
  const t = (await api('teams')).teams;
  view().innerHTML = `<div class="seg" style="margin-bottom:12px;max-width:560px"><button aria-pressed="false" onclick="location.hash='#/players'">Players</button><button aria-pressed="true">Teams</button></div>
    <div class="tgrid">${t.map(x => `<a class="tcard" href="#/team/${x.id}" style="--tc:${esc(x.color)}"><img src="${esc(x.logo)}" alt="${esc(x.name)} logo"><b>${esc(x.name)}</b>
      <span>${x.players} ${x.players === 1 ? 'player' : 'players'}</span><span class="wl">${x.played ? `${x.won} won, ${x.played - x.won} lost` : 'No matches yet'}</span></a>`).join('')}</div>
    <p class="small muted" style="margin-top:12px">A match counts for a team when every player on that side belongs to it.</p>`;
}
async function vTeam(id) {
  setTabs(null);
  const d = await api('team', null, { id }), t = d.team;
  setTop(t.name, BD.club, { back: '#/teams' });
  view().innerHTML = `<div class="thero" style="--tc:${esc(t.color)}"><img src="${esc(t.logo)}" alt="${esc(t.name)} logo"><div><b>${esc(t.name)}</b><span>${esc(BD.club)}, Nacharam</span></div></div>
    <div class="stats3"><div><b>${t.players}</b><span>Players</span></div><div><b>${t.won}</b><span>Matches won</span></div><div><b>${t.played - t.won}</b><span>Lost</span></div></div>
    <h2 class="sec">Players <span class="n">${d.players.length || ''}</span></h2>
    ${d.players.length ? `<div class="card plist" style="padding:2px 14px">${d.players.map(p => `<a href="#/p/${p.id}"><span class="nm">${esc(p.name)}</span><span class="wl">${p.played ? `${p.won} won, ${p.played - p.won} lost` : '<span class="muted" style="font-weight:500">No matches yet</span>'}</span>${t.can_edit ? `<button class="btn sm" data-out="${p.id}" aria-label="Remove ${esc(p.name)} from ${esc(t.name)}">Remove</button>` : ''}</a>`).join('')}</div>` : `<p class="empty">No players in ${esc(t.name)} yet.</p>`}
    ${t.can_edit ? `<p style="margin-top:12px"><button class="btn" id="t-add">${IC.plus} Add players to ${esc(t.name)}</button></p>` : ''}
    <h2 class="sec">Matches</h2>${d.matches.map(m => matchRow(m)).join('') || '<p class="empty">No matches played yet.</p>'}`;
  view().onclick = e => {
    const out = e.target.closest('[data-out]');
    if (out) { e.preventDefault(); return run(async () => { await api('team_players', { id, remove: +out.dataset.out }); S.boot.teams = (await api('boot')).teams; route(); }); }
    if (e.target.closest('#t-add')) {
      const s2 = sheet(`<h2>Add players to ${esc(t.name)}</h2><label class="f">Names, one per line<textarea id="tp" autocapitalize="words" spellcheck="false" placeholder="Asha\nRavi\nKiran"></textarea>
        <span class="hint">New names are added as players. A player already in another team moves to ${esc(t.name)}.</span></label>
        <div class="btns"><button class="btn" data-close>Cancel</button><button class="btn pri" id="tp-go">Add players</button></div>`);
      $('#tp-go', s2).onclick = () => run(async () => { const r = await api('team_players', { id, names: $('#tp', s2).value }); s2.close(); toast(`${r.added} ${r.added === 1 ? 'player' : 'players'} added to ${t.name}`); route(); });
    }
  };
}

/* ---------- Me / sign in ---------- */
function loadScript(src) { return new Promise((ok, no) => { const s = document.createElement('script'); s.src = src; s.async = true; s.onload = ok; s.onerror = no; document.head.appendChild(s); }); }
function signedIn(r, msg) { S.user = r.user; toast(msg || `Signed in as ${r.user.name}`); go(S.after || '#/'); S.after = null; }
async function vMe() {
  setTabs('me');
  if (S.user) return vAccount();
  setTop('Sign in', BD.club);
  const b = S.boot;
  view().innerHTML = `<div class="signin">${BD.logo ? `<img class="brand" src="${esc(BD.logo)}" alt="${esc(BD.club)}">` : ''}<h2>Sign in to score</h2><p class="lead">Anyone can follow matches. Sign in to start a match and keep score. Your Cric-Scorer account works here too.</p>
    ${b.google_client_id ? '<div id="gbtn"></div><div class="or">or</div>' : ''}
    ${b.whatsapp ? `<div id="wa"><label class="f" style="margin-top:0">Mobile number on WhatsApp<input id="wa-mobile" type="tel" inputmode="tel" autocomplete="tel" placeholder="98765 43210"></label>
      <p style="margin-top:12px"><button class="btn wa wide" id="wa-go">Send me a code on WhatsApp</button></p></div>` : ''}
    ${!b.google_client_id && !b.whatsapp ? '<p class="muted">Google and WhatsApp sign-in are not switched on for this site yet. Use your Cric-Scorer username and password.</p>' : ''}
    <details ${!b.google_client_id && !b.whatsapp ? 'open' : ''}><summary>Use a Cric-Scorer username and password</summary>
      <form id="pw"><label class="f">Username<input id="pw-u" autocapitalize="off" autocomplete="username"></label><label class="f">Password<input id="pw-p" type="password" autocomplete="current-password"></label>
      <p style="margin-top:14px"><button class="btn wide">Sign in</button></p></form></details>
    <p class="small muted" style="margin-top:22px">By signing in you agree to the <a href="${esc(b.main_site)}/terms" target="_blank" rel="noopener">Terms of Use</a> and <a href="${esc(b.main_site)}/privacy" target="_blank" rel="noopener">Privacy Policy</a>.</p></div>`;
  $('#pw').onsubmit = e => { e.preventDefault(); run(async () => signedIn(await api('login', { username: $('#pw-u').value, password: $('#pw-p').value }))); };
  if (b.whatsapp) $('#wa-go').onclick = () => run(() => waFlow($('#wa-mobile').value, {}));
  if (b.google_client_id) {
    try {
      await loadScript('https://accounts.google.com/gsi/client');
      const onCred = cred => run(async () => {
        let r = await api('google', { credential: cred.credential });
        if (r.needs_consent) {
          if (!await confirmSheet('Create your account?', `${r.name || r.email} is new here. Creating an account means you agree to the Terms of Use and Privacy Policy.`, 'Create account')) return;
          r = await api('google', { credential: cred.credential, consent: true });
        }
        signedIn(r);
      });
      google.accounts.id.initialize({ client_id: b.google_client_id, callback: onCred, ux_mode: 'popup' });
      if ($('#gbtn')) google.accounts.id.renderButton($('#gbtn'), { type: 'standard', theme: 'outline', size: 'large', text: 'continue_with', shape: 'pill', width: Math.min(360, view().clientWidth - 28) });
    } catch (e) { if ($('#gbtn')) $('#gbtn').innerHTML = '<p class="small muted">Google sign-in could not load. Check your connection, or use another way below.</p>'; }
  }
}
async function waFlow(mobile, extra) {
  let r = await api('wa_request', { mobile, ...extra });
  if (r.new) {   // first time for this number: name + consent, then send the code
    const d = sheet(`<h2>New here? Create your account</h2><p class="muted small">No account uses ${esc(r.mobile)} yet.</p>
      <label class="f">Your name<input id="n-name" maxlength="100" autocapitalize="words" autocomplete="name"></label>
      <label class="chk"><input type="checkbox" id="n-ok"><span>I agree to the <a href="${esc(S.boot.main_site)}/terms" target="_blank" rel="noopener">Terms of Use</a> and <a href="${esc(S.boot.main_site)}/privacy" target="_blank" rel="noopener">Privacy Policy</a></span></label>
      <div class="btns"><button class="btn" data-close>Cancel</button><button class="btn pri" id="n-go">Send the code</button></div>`);
    $('#n-go', d).onclick = () => run(async () => {
      if (!$('#n-name', d).value.trim()) return toast('Enter your name', true);
      if (!$('#n-ok', d).checked) return toast('Tick the box to agree to the terms', true);
      const name = $('#n-name', d).value.trim(); d.close();
      await waFlow(mobile, { name, consent: true });
    });
    return;
  }
  const d = sheet(`<h2>Enter the code</h2><p class="muted small">We sent a 6-digit code to ${esc(r.mobile)} on WhatsApp. It works for ${r.minutes} minutes.${r.dev_code ? ` <b>Test mode: ${esc(r.dev_code)}</b>` : ''}</p>
    <label class="f">Code<input id="c-code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" style="font-size:24px;letter-spacing:6px;text-align:center;font-weight:800"></label>
    <div class="btns"><button class="btn" data-close>Cancel</button><button class="btn pri" id="c-go">Sign in</button></div>`);
  const submit = () => run(async () => { const x = await api('wa_verify', { id: r.id, code: $('#c-code', d).value }); d.close(); signedIn(x); });
  $('#c-go', d).onclick = submit;
  $('#c-code', d).oninput = e => { if (e.target.value.replace(/\D/g, '').length === 6) submit(); };
  $('#c-code', d).focus();
}
async function vAccount() {
  const u = S.user;
  setTop(u.name, u.admin ? 'Club admin' : 'Member');
  let admins = '';
  if (u.admin) {
    const a = (await api('admins')).admins;
    admins = `<h2 class="sec">Club admins</h2><div class="card"><p class="small muted">Admins set up tournaments, score any match and edit players. You are an admin through your verified sign-in.</p>
      ${a.map(x => `<div class="row" style="margin-top:10px"><span class="grow"><b>${esc(x.name)}</b> <span class="muted small">@${esc(x.username)}</span></span><button class="btn sm" data-rm="${x.id}">Remove</button></div>`).join('')}
      <div class="row" style="margin-top:12px"><input id="ad-who" class="grow" autocapitalize="off" placeholder="Mobile, Google e-mail or username" style="min-height:44px;padding:0 12px;border-radius:12px;border:1px solid var(--rule);background:var(--card)"><button class="btn sm pri" id="ad-add">Add</button></div></div>`;
  }
  view().innerHTML = `<div class="card"><div class="row"><div class="grow"><b style="font-size:18px">${esc(u.name)}</b><br><span class="muted small">@${esc(u.username)}${u.mobile ? ', ' + esc(u.mobile) : ''}${u.google ? ', Google linked' : ''}</span></div></div>
      <label class="chk"><input type="checkbox" id="voice" ${S.voice ? 'checked' : ''}><span>Read the score aloud after each point <span class="muted small">(on this phone)</span></span></label></div>
    ${admins}
    ${standalone() ? '' : `<h2 class="sec">App</h2><div class="card install"><img src="assets/icon-192.png" alt=""><div class="grow"><b>RV Badminton on your home screen</b><br><span class="muted small">Opens full screen, like any other app.</span></div><button class="btn sm pri" id="inst">Install</button></div>`}
    <h2 class="sec">More</h2><div class="card"><p><a href="${esc(S.boot.main_site)}" target="_blank" rel="noopener"><b>Cric-Scorer</b></a> <span class="muted">cricket scoring for the club, same account</span></p>
      <p class="small muted" style="margin-top:8px"><a href="${esc(S.boot.main_site)}/terms" target="_blank" rel="noopener">Terms of Use</a>, <a href="${esc(S.boot.main_site)}/privacy" target="_blank" rel="noopener">Privacy Policy</a>. RV Badminton ${esc(BD.v)}</p></div>
    <p style="margin-top:16px"><button class="btn wide" id="out">Sign out</button></p>`;
  if ($('#inst')) $('#inst').onclick = install;
  $('#voice').onchange = e => { S.voice = e.target.checked; localStorage.setItem('bd_voice', S.voice ? '1' : '0'); if (S.voice) say('Score announcements on'); };
  $('#out').onclick = () => run(async () => { await api('logout', {}); S.user = null; try { google.accounts.id.disableAutoSelect(); } catch (e) { /* not loaded */ } toast('Signed out'); go('#/'); });
  if (u.admin) {
    $('#ad-add').onclick = () => run(async () => { await api('admin_add', { who: $('#ad-who').value }); toast('Admin added'); route(); });
    view().onclick = e => { const b = e.target.closest('[data-rm]'); if (b) run(async () => { await api('admin_remove', { id: +b.dataset.rm }); route(); }); };
  }
}

/* ---------- router ---------- */
async function route() {
  clearInterval(S.poll); S.poll = null;
  view().onclick = null; $('#top').onclick = null;
  const h = location.hash.replace(/^#\/?/, ''), [p, id] = h.split('/');
  const routes = { '': vHome, m: () => vMatch(+id), new: vNew, tours: vTours, t: () => vTour(+id), 'tour-new': vTourNew, players: vPlayers, p: () => vPlayer(+id), teams: vTeams, team: () => vTeam(+id), me: vMe };
  const fn = routes[p] || vHome;
  $('#app').toggleAttribute('data-wide', ['', 'm', 't', 'tours', 'players', 'p', 'teams', 'team'].includes(routes[p] ? p : ''));   // lists and matches use the whole screen on a laptop or TV
  if ((p === 'new' || p === 'tour-new') && !S.user) S.after = '#/' + p;
  try { await fn(); window.scrollTo(0, 0); }
  catch (e) { setTop(BD.name, BD.club, { back: p ? '#/' : null }); view().innerHTML = `<div class="card"><h2 class="sec">This page could not load</h2><p class="muted">${esc(e.message)}</p><p style="margin-top:12px"><button class="btn" onclick="location.reload()">Try again</button></p></div>`; }
}
(async function start() {
  setTop(BD.name, BD.club);
  try { S.boot = await api('boot'); S.user = S.boot.user; if (S.boot.logo) BD.logo = S.boot.logo; }
  catch (e) { const sp = $('#splash'); if (sp) sp.remove(); view().innerHTML = `<div class="card"><h2 class="sec">RV Badminton could not start</h2><p class="muted">${esc(e.message)}</p><p style="margin-top:12px"><button class="btn" onclick="location.reload()">Try again</button></p></div>`; return; }
  if (BD.open && !location.hash) history.replaceState(null, '', new URL('./#/m/' + BD.open, document.baseURI));
  window.addEventListener('hashchange', route);
  await route();
  const sp = $('#splash'); if (sp) { sp.classList.add('out'); setTimeout(() => sp.remove(), 400); }
})();
