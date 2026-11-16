/* RV Tennis - the phone app. One page; the address after # decides what is shown.
   #/ matches · #/m/12 a match · #/new · #/tours · #/t/3 · #/tour-new · #/players · #/p/9 · #/me */
'use strict';
const S = { boot: null, user: null, poll: null, voice: localStorage.getItem('tn_voice') === '1' };
const $ = (s, el = document) => el.querySelector(s);
const esc = s => String(s == null ? '' : s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const view = () => $('#view');
const IC = {
  back: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 5l-7 7 7 7"/></svg>',
  more: '<svg viewBox="0 0 24 24" fill="currentColor"><circle cx="5" cy="12" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="19" cy="12" r="2"/></svg>',
  share: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 15V4m0 0L8 8m4-4l4 4M5 13v5a2 2 0 002 2h10a2 2 0 002-2v-5"/></svg>',
  plus: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>',
  ball: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M5.2 6.2c3 2.6 3 9 0 11.6M18.8 6.2c-3 2.6-3 9 0 11.6" stroke-linecap="round"/></svg>',
  cup: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 4h10v5a5 5 0 01-10 0V4zM7 6H4v1a3 3 0 003 3M17 6h3v1a3 3 0 01-3 3M12 14v4M8 20h8"/></svg>',
  people: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8" r="3.2"/><path d="M3 19c.6-3.2 3-5 6-5s5.4 1.800 6 5M16 5.200a3.200 3.200 0 010 5.600M18 14.300c1.600.800 2.700 2.400 3 4.700"/></svg>',
  me: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="3.600"/><path d="M4.500 20c1-3.800 4-6 7.500-6s6.500 2.200 7.500 6"/></svg>',
  mark: '<svg class="mark" viewBox="0 0 40 40" aria-hidden="true"><circle cx="20" cy="20" r="19" fill="#dcf03a"/><path d="M6.500 8.500c6 5 6 18 0 23M33.500 8.500c-6 5-6 18 0 23" fill="none" stroke="#0f2440" stroke-width="2.600" stroke-linecap="round"/></svg>',
};

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
  const logo = TN.logo ? `<img class="mark" src="${esc(TN.logo)}" alt="">` : IC.mark;
  $('#top').innerHTML = (opts.back ? `<button class="ib" id="tb-back" aria-label="Back">${IC.back}</button>` : logo)
    + `<h1>${esc(title)}${sub ? `<small>${esc(sub)}</small>` : ''}</h1>` + (opts.right || '');
  if (opts.back) $('#tb-back').onclick = () => go(opts.back);
  document.title = title + ' · ' + TN.name;
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

/* ---------- score pieces ---------- */
function setCell(s, side, cls = 's') {
  const mine = side ? s.b : s.a, theirs = side ? s.a : s.b;
  const tb = s.tb && mine < theirs ? `<sup>${Math.min(s.tb[0], s.tb[1])}</sup>` : '';
  return `<span class="${cls} ${mine > theirs ? 'hi' : 'lo'}">${mine}${tb}</span>`;
}
/** A match in a list */
function matchRow(m, extra = '') {
  const mini = m.mini || { sets: [], games: [0, 0], label: ['', ''] };
  const live = m.status === 'live', done = m.status === 'done';
  const ctx = [m.tour, m.round_name, m.court].filter(Boolean).join(', ');
  const right = live ? '<span class="live">Live</span>' : done ? day(m.finished_at) : (m.sched_at ? when(m.sched_at) : 'Not started');
  const line = side => {
    const nm = side ? m.b : m.a;
    const cls = done ? (m.winner === side ? 'won' : 'lost') : '';
    let cells = mini.sets.map(s => setCell(s, side)).join('');
    if (live) cells += `<span class="s hi">${mini.games[side]}</span><span class="pt">${mini.label[side] || (mini.label[1 - side] === 'Ad' ? '–' : '0')}</span>`;
    if (done && !mini.sets.length && m.winner === side) cells = `<span class="small muted">${esc(m.summary || '')}</span>`;
    return `<div class="line ${cls}"><span class="srv ${live && mini.server === side ? 'on' : ''}"></span><span class="nm ${nm ? '' : 'tbd'}">${esc(nm || 'To be decided')}</span>${cells}${done ? `<span class="tick">${m.winner === side ? '✓' : ''}</span>` : ''}</div>`;
  };
  return `<a class="mrow" href="#/m/${m.id}"><div class="meta"><span>${esc(ctx || (m.doubles ? 'Doubles' : 'Singles'))}</span><span>${right}</span></div>${line(0)}${line(1)}${extra}</a>`;
}

/* ---------- Matches (home) ---------- */
async function vHome() {
  setTop(TN.name, TN.club);
  setTabs('home');
  const d = await api('home');
  const sec = (title, list, empty) => list.length || empty ? `<h2 class="sec">${title} <span class="n">${list.length || ''}</span></h2>${list.map(m => matchRow(m)).join('') || `<p class="empty">${empty}</p>`}` : '';
  const none = !d.live.length && !d.next.length && !d.done.length;
  view().innerHTML = (none ? `<div class="card"><h2 class="sec">No matches yet</h2><p class="muted">Start the first one: tap “New match”, type the players' names, and score each point by tapping the winner's side of the court.</p></div>` :
    sec('Live now', d.live, '') + sec('Up next', d.next, '') + sec('Results', d.done, d.live.length || d.next.length ? '' : 'No finished matches yet.'))
    + `<a class="btn fab" href="#/new">${IC.plus} New match</a>`;
  S.poll = setInterval(async () => { if (document.hidden) return; try { const n = await api('home'); if (JSON.stringify(n) !== JSON.stringify(d) && location.hash.replace(/^#?\/?$/, '') === '') route(); } catch (e) { /* next time */ } }, 15000);
}

/* ---------- New match ---------- */
function formatFields(sel = 'bo3') {
  return `<label class="f">Match format<select id="f-preset">${Object.entries(S.boot.presets).map(([k, l]) => `<option value="${k}" ${k === sel ? 'selected' : ''}>${esc(l)}</option>`).join('')}</select></label>
    <label class="chk"><input type="checkbox" id="f-noad"><span>No-ad scoring <span class="muted small">(at deuce, the next point wins the game)</span></span></label>`;
}
const readFormat = () => ({ preset: $('#f-preset').value, noad: $('#f-noad').checked });
async function vNew() {
  setTop('New match', '', { back: '#/' });
  setTabs(null);
  if (!S.user) { view().innerHTML = `<div class="card"><p>Sign in to start and score a match.</p><p style="margin-top:12px"><a class="btn pri" href="#/me">Sign in</a></p></div>`; return; }
  const pl = (await api('players')).players;
  let dbl = false;
  const draw = () => {
    const inp = (id, ph) => `<input id="${id}" list="pl" autocomplete="off" autocapitalize="words" placeholder="${ph}" maxlength="80" value="${esc($('#' + id) ? $('#' + id).value : '')}">`;
    $('#sides').innerHTML = `<label class="f">${dbl ? 'Pair A' : 'Player A'}${inp('a1', 'Name')}${dbl ? inp('a2', 'Partner') : ''}</label>
      <label class="f">${dbl ? 'Pair B' : 'Player B'}${inp('b1', 'Name')}${dbl ? inp('b2', 'Partner') : ''}</label>`;
  };
  view().innerHTML = `<datalist id="pl">${pl.map(p => `<option value="${esc(p.name)}">`).join('')}</datalist>
    <div class="seg" role="group" aria-label="Match type"><button id="sg" aria-pressed="true">Singles</button><button id="db" aria-pressed="false">Doubles</button></div>
    <div id="sides"></div>
    ${formatFields()}
    <label class="f">Serving first<select id="first"><option value="0">Side A</option><option value="1">Side B</option></select><span class="hint">You can change this on the match screen until the first point.</span></label>
    <label class="f">Court <span class="muted">(optional)</span><input id="court" maxlength="40" placeholder="Court 1"></label>
    <p style="margin-top:22px"><button class="btn pri wide" id="go">Create match</button></p>`;
  draw();
  const pick = d => { dbl = d; $('#sg').setAttribute('aria-pressed', !d); $('#db').setAttribute('aria-pressed', d); draw(); };
  $('#sg').onclick = () => pick(false); $('#db').onclick = () => pick(true);
  $('#go').onclick = () => run(async () => {
    const v = id => ($('#' + id) ? $('#' + id).value.trim() : '');
    if (!v('a1') || !v('b1') || (dbl && (!v('a2') || !v('b2')))) return toast('Fill in every player\'s name', true);
    const r = await api('match_new', { doubles: dbl, a: dbl ? v('a1') + ' / ' + v('a2') : v('a1'), b: dbl ? v('b1') + ' / ' + v('b2') : v('b1'),
      format: readFormat(), first_srv: +$('#first').value, court: v('court') });
    go('#/m/' + r.id);
  });
}

/* ---------- A match: scoreboard, court, stats ---------- */
function say(text) {
  if (!S.voice || !('speechSynthesis' in window) || !text) return;
  try { speechSynthesis.cancel(); const u = new SpeechSynthesisUtterance(text); u.rate = 1.05; u.lang = 'en-IN'; speechSynthesis.speak(u); } catch (e) { /* no voice on this phone */ }
}
function callText(m) {
  const st = m.state, nm = side => (side ? m.b : m.a);
  if (st.over) return 'Game, set and match, ' + nm(st.winner);
  let t = st.call;
  if (t === 'Game') { const g = st.games, sets = st.sets; t = (g[0] + g[1] === 0 && sets.length ? 'Game and set. ' : 'Game. ') + (g[0] === g[1] ? `${g[0]} all` : `${nm(g[0] > g[1] ? 0 : 1)} leads ${Math.max(...g)} ${Math.min(...g)}`); }
  t = t.replace('Advantage server', 'Advantage ' + nm(st.server[0])).replace('Advantage receiver', 'Advantage ' + nm(1 - st.server[0]));
  if (st.flag && st.flag.k !== 'bp') t += st.flag.k === 'mp' ? '. Match point' : '. Set point';
  return t;
}
async function vMatch(id) {
  setTabs(null);
  let m = (await api('match', null, { id })).match, tab = 'stats', busy = false;
  const back = () => (m.tour_id ? '#/t/' + m.tour_id : '#/');
  const shareBtn = `<button class="ib" id="share" aria-label="Share this match">${IC.share}</button>`;
  const paint = () => {
    const st = m.state, live = !st.over, can = m.can_score;
    setTop(`${m.a} v ${m.b}`, [m.tour, m.round_name, m.format_label].filter(Boolean).join(', '), { back: back(), right: shareBtn + (can ? `<button class="ib" id="menu" aria-label="Match options">${IC.more}</button>` : '') });
    const nSets = st.sets.length;
    const bl = side => `<div class="bl ${st.over && st.winner === side ? 'won' : ''}"><span class="srv ${live && st.server && st.server[0] === side ? 'on' : ''}"></span>
      <span class="nm">${esc(side ? m.b : m.a)}</span>${st.sets.map(s => setCell(s, side)).join('')}
      ${live ? `<span class="s cur">${st.games[side]}</span><span class="pt">${st.label[side] || (st.label[1 - side] === 'Ad' ? '' : '0')}</span>` : ''}</div>`;
    const status = m.status === 'live' ? '<span class="live">Live</span>' : m.status === 'done' ? 'Finished ' + day(m.finished_at) : (m.sched_at ? when(m.sched_at) : 'Not started');
    let flag = '';
    if (live && m.status === 'live') {
      const f = st.flag, nm = f ? (f.side ? m.b : m.a) : '';
      if (f) flag = `<div class="flag ${f.k === 'bp' ? 'warm' : 'hot'}">${{ bp: 'Break point', sp: 'Set point', mp: 'Match point' }[f.k]}, ${esc(nm)}</div>`;
      else if (st.ends) flag = '<div class="flag warm">Change ends</div>';
      else if (st.deuce) flag = `<div class="flag">${st.format.noad ? 'Deuce: deciding point' : 'Deuce'}</div>`;
      else if (st.tb && st.pts[0] + st.pts[1] === 0) flag = `<div class="flag">${st.mtb ? `Match tiebreak, first to ${st.format.final_to}` : `Tiebreak, first to ${st.format.tb_to}`}</div>`;
      else flag = `<div class="flag">${nSets ? `Set ${nSets + 1}` : 'First set'}${m.court ? ', ' + esc(m.court) : ''}</div>`;
    }
    const result = st.over ? `<div class="result"><div class="who">${esc(st.winner ? m.b : m.a)} won</div><div class="sum">${esc(m.summary || '')}${st.outcome === 'ret' ? ' (opponent retired)' : st.outcome === 'wo' ? ' (walkover)' : ''}</div></div>` : '';
    // the court: side B plays from the top, side A from the bottom
    let court = '';
    const waiting = !m.a || !m.b;
    if (can && live && !waiting) {
      const srv = st.server, sName = m.names[srv[0]][srv[1]] || (srv[0] ? m.b : m.a);
      const right = st.court === 'deuce';                       // server stands to the right of the centre mark, seen from their own end
      const x = srv[0] === 0 ? (right ? 68 : 32) : (right ? 32 : 68);
      const half = (side, cls) => `<button class="half ${cls}" data-w="${side}"><span class="nm">${esc(side ? m.b : m.a)}</span><span class="do">${srv[0] === side ? esc(sName) + ' serving' : 'Tap: point won'}</span></button>`;
      court = `<div class="court" id="court"><div class="in">${half(1, 't')}<div class="net"></div>${half(0, 'b')}</div><span class="ball ${srv[0] ? 't' : 'b'}" style="left:calc(${x}% - 10px)"></span></div>
        <div class="acts"><button class="btn" data-k="ace">Ace</button><button class="btn" data-k="df">Double fault</button><button class="btn" id="undo" ${st.n || st.over ? '' : 'disabled'}>Undo</button></div>`;
    } else if (can && st.over) court = `<div class="acts" style="grid-template-columns:1fr"><button class="btn" id="undo">${st.manual ? 'Clear this result' : 'Undo the last point'}</button></div>`;
    else if (waiting) court = '<div class="card setup"><p class="muted">This match starts once the previous round is finished.</p></div>';
    const setup = can && m.status === 'scheduled' && !waiting ? `<div class="card setup"><h3>Before the first point</h3>
        <div class="row"><span class="grow">Serving first</span><div class="seg" style="flex:1.4"><button data-first="0" aria-pressed="${m.first_srv === 0}">${esc(m.names[0][0])}</button><button data-first="1" aria-pressed="${m.first_srv === 1}">${esc(m.names[1][0])}</button></div></div>
        ${m.doubles ? `<p class="small muted" style="margin-top:8px">In each pair the first name serves first. <a href="#" data-swap="a">Swap ${esc(m.a)}</a>, <a href="#" data-swap="b">swap ${esc(m.b)}</a>.</p>` : ''}
        <p class="small muted" style="margin-top:8px">Tap a side of the court each time that side wins a point. Played already? <a href="#" id="typed">Enter the final score</a>.</p></div>` : '';
    // stats / point by point
    const s = st.stats, pct = (a, b) => b ? `${Math.round(100 * a / b)}%` : '–';
    const stats = st.manual ? '<p class="empty">This result was entered after the match, so there are no point-by-point stats.</p>' : !st.n ? '<p class="empty">Stats appear once the match starts.</p>' :
      `<table class="st"><thead><tr><td>${esc(m.a)}</td><td class="k"></td><td>${esc(m.b)}</td></tr></thead><tbody>${[
        ['Points won', s[0].pts, s[1].pts], ['Aces', s[0].aces, s[1].aces], ['Double faults', s[0].df, s[1].df],
        ['Service points won', pct(s[0].sw, s[0].sp), pct(s[1].sw, s[1].sp)], ['Break points won', `${s[0].bpw}/${s[0].bpc}`, `${s[1].bpw}/${s[1].bpc}`],
        ['Games won', s[0].games, s[1].games], ['Most points in a row', s[0].run, s[1].run]].map(r => `<tr><td>${r[1]}</td><td class="k">${r[0]}</td><td>${r[2]}</td></tr>`).join('')}</tbody></table>`;
    let pbp = '';
    st.log.forEach((games, si) => {
      if (!games.length) return;
      let g = [0, 0];
      pbp += `<h4>${st.log.length > 1 || st.sets.length ? 'Set ' + (si + 1) : 'Games'}</h4>` + games.map(x => {
        const open = x.w === undefined;
        if (!open) g[x.w]++;
        const srvName = x.s ? m.b : m.a, brk = !open && !x.tb && x.w !== x.s;
        const what = open ? `${esc(srvName)} serving` : x.tb ? `Tiebreak to ${esc(x.w ? m.b : m.a)}` : brk ? `${esc(x.w ? m.b : m.a)} <span class="brk">broke</span>` : `${esc(srvName)} held`;
        return `<div class="g"><span class="sc">${open ? '' : g[0] + '–' + g[1]}</span><span class="who">${what}</span><span class="dots" aria-label="${x.p.length} points">${[...x.p].map(c => `<i class="${+c === x.s ? '' : 'o'}"></i>`).join('')}</span></div>`;
      }).join('');
    });
    if (pbp) pbp += '<p class="small muted" style="margin-top:10px">Filled dot: point won by the server. Ring: won by the receiver.</p>';
    view().innerHTML = `<div class="mgrid"><div><div class="board"><div class="ctx"><span>${esc(m.doubles ? 'Doubles' : 'Singles')}${m.court ? ', ' + esc(m.court) : ''}</span><span>${status}</span></div>${bl(0)}${bl(1)}</div>
      ${result}${flag}${setup}${court}</div>
      <div><div class="tabs2" role="tablist"><button role="tab" aria-selected="${tab === 'stats'}" data-tab="stats">Stats</button><button role="tab" aria-selected="${tab === 'pbp'}" data-tab="pbp">Game by game</button></div>
      <div class="${tab === 'pbp' ? 'pbp' : ''}">${tab === 'stats' ? stats : (pbp || '<p class="empty">Games appear here as they are played.</p>')}</div>
      ${!can && !S.user && live ? '<p class="small muted" style="margin-top:18px">Scoring this match? <a href="#/me">Sign in</a>.</p>' : ''}</div></div>`;
  };
  const act = async (a, body) => {
    if (busy) return; busy = true;
    const c = $('#court'); if (c) c.classList.add('busy');
    try { m = (await api(a, { id, n: m.state.n + (m.state.outcome ? 1 : 0), ...body })).match; if (navigator.vibrate) navigator.vibrate(12); if (a === 'point') say(callText(m)); }
    catch (e) { toast(e.message, true); if (e.status === 401) S.user = null; try { m = (await api('match', null, { id })).match; } catch (x) { /* keep what we have */ } }
    busy = false; paint();
  };
  view().onclick = e => {
    const t = e.target.closest('button,a'); if (!t) return;
    if (t.dataset.w !== undefined) return act('point', { w: +t.dataset.w });
    if (t.dataset.k) { const srv = m.state.server[0]; return act('point', { w: t.dataset.k === 'ace' ? srv : 1 - srv, k: t.dataset.k }); }
    if (t.id === 'undo') return act('undo', {});
    if (t.dataset.tab) { tab = t.dataset.tab; return paint(); }
    if (t.dataset.first !== undefined) return act('match_edit', { first_srv: +t.dataset.first });
    if (t.dataset.swap) { e.preventDefault(); return act('match_edit', { ['swap_' + t.dataset.swap]: 1 }); }
    if (t.id === 'typed') { e.preventDefault(); return typedResult(); }
  };
  const typedResult = () => {
    const n = m.state.format.best;
    const d = sheet(`<h2>Enter the final score</h2><p class="muted small">${esc(m.format_label)}. Leave unused sets empty.${m.state.format.final === 'tb' && n > 1 ? ' For a match tiebreak, enter its points (for example 10 and 8).' : ''}</p>
      <div class="sets-in"><span></span><span class="hd">${esc(m.a)}</span><span class="hd">${esc(m.b)}</span>
      ${Array.from({ length: n }, (_, i) => `<span>${n === 1 ? 'Score' : 'Set ' + (i + 1)}</span><input inputmode="numeric" maxlength="2" data-s="${i}a" aria-label="Set ${i + 1}, ${esc(m.a)}"><input inputmode="numeric" maxlength="2" data-s="${i}b" aria-label="Set ${i + 1}, ${esc(m.b)}">`).join('')}</div>
      <div class="btns"><button class="btn" data-close>Cancel</button><button class="btn pri" id="save">Save result</button></div>`);
    $('#save', d).onclick = () => run(async () => {
      const sets = [];
      for (let i = 0; i < n; i++) { const a = $(`[data-s="${i}a"]`, d).value.trim(), b = $(`[data-s="${i}b"]`, d).value.trim(); if (a !== '' || b !== '') sets.push([a === '' ? -1 : +a, b === '' ? -1 : +b]); }
      m = (await api('match_result', { id, sets, replace: true })).match; d.close(); paint();
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
          <button data-o="wo0">Walkover to ${esc(m.a)}</button><button data-o="wo1">Walkover to ${esc(m.b)}</button>` : ''}
        <button data-o="court">Court and start time</button><button data-o="scorer">Hand scoring to someone else</button>
        <button data-o="voice">${S.voice ? 'Stop reading the score aloud' : 'Read the score aloud'}</button>
        ${m.tour_id ? '' : '<button data-o="del" class="danger">Delete this match</button>'}</div>`);
      d.onclick = ev => run(async () => {
        const o = (ev.target.closest('[data-o]') || {}).dataset; if (!o) return;
        d.close();
        if (o.o === 'typed') return typedResult();
        if (/^(ret|wo)/.test(o.o)) { const w = +o.o.slice(-1); if (await confirmSheet('End the match?', `${w ? m.b : m.a} is recorded as the winner. You can undo this.`, 'End match')) { m = (await api('match_end', { id, w, why: o.o.slice(0, -1) })).match; paint(); } return; }
        if (o.o === 'voice') { S.voice = !S.voice; localStorage.setItem('tn_voice', S.voice ? '1' : '0'); if (S.voice) say('Score announcements on'); return toast(S.voice ? 'The score is read aloud after each point' : 'Voice off'); }
        if (o.o === 'del') { if (await confirmSheet('Delete this match?', 'The score and stats are removed for everyone. This cannot be undone.', 'Delete match', true)) { await api('match_delete', { id }); go('#/'); } return; }
        if (o.o === 'court') {
          const s2 = sheet(`<h2>Court and start time</h2><label class="f">Court<input id="e-court" maxlength="40" value="${esc(m.court || '')}"></label>
            <label class="f">Start time<input id="e-when" type="datetime-local" value="${esc((m.sched_at || '').replace(' ', 'T').slice(0, 16))}"></label>
            <div class="btns"><button class="btn" data-close>Cancel</button><button class="btn pri" id="e-save">Save</button></div>`);
          $('#e-save', s2).onclick = () => run(async () => { m = (await api('match_edit', { id, court: $('#e-court', s2).value, sched_at: $('#e-when', s2).value })).match; s2.close(); paint(); });
        }
        if (o.o === 'scorer') {
          const s2 = sheet(`<h2>Hand scoring to someone else</h2><p class="muted small">${m.scorer ? esc(m.scorer) + ' can score this match now. ' : ''}They need to have signed in to RV Tennis once. You can still score too.</p>
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
async function vTours() {
  setTop('Tournaments', TN.club); setTabs('tours');
  const t = (await api('tours')).tours;
  view().innerHTML = (t.length ? t.map(x => `<a class="mrow" href="#/t/${x.id}"><div class="meta"><span>${x.kind === 'ko' ? 'Knockout' : 'Round robin'}, ${x.doubles ? 'doubles' : 'singles'}</span><span>${x.done} of ${x.total} played</span></div>
      <div class="line"><span class="nm" style="font-size:17px;font-weight:800">${esc(x.name)}</span><span class="muted small">${x.entries} ${x.doubles ? 'pairs' : 'players'}</span></div></a>`).join('')
    : `<div class="card"><h2 class="sec">No tournaments yet</h2><p class="muted">${S.user && S.user.admin ? 'Set one up: name it, list the players, and the fixtures are drawn for you.' : 'Club tournaments appear here once an admin sets one up.'}</p></div>`)
    + (S.user && S.user.admin ? `<a class="btn fab" href="#/tour-new">${IC.plus} New tournament</a>` : '');
}
async function vTour(id) {
  setTabs(null);
  const d = await api('tour', null, { id }), t = d.tour;
  setTop(t.name, `${t.kind === 'ko' ? 'Knockout' : 'Round robin'}, ${t.format_label}`, { back: '#/tours', right: t.can_edit ? `<button class="ib" id="tdel" aria-label="Tournament options">${IC.more}</button>` : '' });
  let html = '';
  if (t.kind === 'rr') {
    for (let g = 0; g < t.groups; g++) {
      const rows = d.table.filter(r => r.grp === g);
      html += `<h2 class="sec">${t.groups > 1 ? 'Group ' + String.fromCharCode(65 + g) : 'Table'}</h2><div class="card" style="padding:6px 10px"><table class="tb"><thead><tr><th></th><th class="nm">${t.doubles ? 'Pair' : 'Player'}</th><th>P</th><th>W</th><th>L</th><th>Sets</th><th>Games</th></tr></thead><tbody>
        ${rows.map((r, i) => `<tr><td class="pos">${i + 1}</td><td class="nm">${esc(r.name)}</td><td>${r.p}</td><td class="w">${r.w}</td><td>${r.l}</td><td>${r.sf}–${r.sa}</td><td>${r.gf}–${r.ga}</td></tr>`).join('')}</tbody></table></div>`;
    }
    html += '<p class="small muted" style="margin:8px 2px 0">Ranked by wins, then sets won minus lost, then games.</p>';
    const rounds = [...new Set(d.matches.map(m => m.round))];
    html += rounds.map(r => `<h2 class="sec">Round ${r}</h2>` + d.matches.filter(m => m.round === r).map(m => matchRow({ ...m, round_name: t.groups > 1 ? 'Group ' + String.fromCharCode(65 + m.grp) : '' })).join('')).join('');
  } else {
    html += '<div class="draw">';
    for (let r = 1; r <= t.rounds; r++) {
      const ms = d.matches.filter(m => m.round === r && (m.status !== 'bye'));
      const byes = d.matches.filter(m => m.round === r && m.status === 'bye').length;
      html += `<div class="col"><h4>${esc((d.matches.find(m => m.round === r) || {}).round_name || '')}</h4><div class="ms">${ms.map(m => matchRow({ ...m, round_name: '' })).join('')}${byes ? `<p class="small muted">${byes} ${byes === 1 ? 'entry has' : 'entries have'} a bye</p>` : ''}</div></div>`;
    }
    html += '</div><p class="small muted">Swipe sideways to see every round. Winners move on automatically.</p>';
  }
  view().innerHTML = html;
  const b = $('#tdel');
  if (b) b.onclick = () => run(async () => { if (await confirmSheet('Delete this tournament?', 'All its matches and scores are removed for everyone. This cannot be undone.', 'Delete tournament', true)) { await api('tour_delete', { id }); go('#/tours'); } });
  S.poll = setInterval(() => { if (!document.hidden && !document.querySelector('dialog[open]') && d.matches.some(m => m.status === 'live')) route(); }, 20000);
}
async function vTourNew() {
  setTop('New tournament', '', { back: '#/tours' }); setTabs(null);
  if (!S.user || !S.user.admin) { view().innerHTML = '<div class="card"><p>Only a club admin can set up a tournament.</p></div>'; return; }
  let kind = 'rr', dbl = false;
  view().innerHTML = `<label class="f">Tournament name<input id="t-name" maxlength="120" placeholder="RV Club Tennis Cup 2026"></label>
    <label class="f">Type</label><div class="seg" style="margin-top:6px"><button data-kind="rr" aria-pressed="true">Round robin</button><button data-kind="ko" aria-pressed="false">Knockout</button></div>
    <p class="small muted" id="t-kind-hint" style="margin-top:6px"></p>
    <div class="seg" style="margin-top:14px"><button data-dbl="0" aria-pressed="true">Singles</button><button data-dbl="1" aria-pressed="false">Doubles</button></div>
    ${formatFields('bo3mtb')}
    <label class="f" id="t-groups-l">Groups<select id="t-groups">${[1, 2, 3, 4, 6, 8].map(n => `<option value="${n}">${n === 1 ? 'One group: everyone plays everyone' : n + ' groups'}</option>`).join('')}</select></label>
    <label class="f"><span id="t-ent-l"></span><textarea id="t-entries" autocapitalize="words" spellcheck="false"></textarea><span class="hint" id="t-ent-h"></span></label>
    <p style="margin-top:22px"><button class="btn pri wide" id="t-go">Create tournament and fixtures</button></p>`;
  const sync = () => {
    view().querySelectorAll('[data-kind]').forEach(b => b.setAttribute('aria-pressed', b.dataset.kind === kind));
    view().querySelectorAll('[data-dbl]').forEach(b => b.setAttribute('aria-pressed', (b.dataset.dbl === '1') === dbl));
    $('#t-kind-hint').textContent = kind === 'rr' ? 'Everyone in a group plays everyone else; a table ranks them.' : 'Lose and you are out. The draw is seeded in the order you list the entries; byes go to the top seeds.';
    $('#t-groups-l').style.display = kind === 'rr' ? '' : 'none';
    $('#t-ent-l').textContent = dbl ? 'Pairs, one per line' : 'Players, one per line';
    $('#t-entries').placeholder = dbl ? 'Asha / Meera\nRavi / Kiran' : 'Asha\nRavi\nKiran';
    $('#t-ent-h').textContent = (dbl ? 'Write each pair as "Name / Name". ' : '') + 'List the strongest first: that order is the seeding.';
  };
  sync();
  view().onclick = e => { const b = e.target.closest('[data-kind],[data-dbl]'); if (!b) return; if (b.dataset.kind) kind = b.dataset.kind; else dbl = b.dataset.dbl === '1'; sync(); };
  $('#t-go').onclick = () => run(async () => {
    const r = await api('tour_new', { name: $('#t-name').value, kind, doubles: dbl, format: readFormat(), groups: +$('#t-groups').value, entries: $('#t-entries').value });
    go('#/t/' + r.id);
  });
}

/* ---------- Players ---------- */
async function vPlayers() {
  setTop('Players', TN.club); setTabs('players');
  const p = (await api('players')).players;
  view().innerHTML = (p.length ? `<div class="card plist" style="padding:2px 14px">${p.map((x, i) => `<a href="#/p/${x.id}"><span class="rk">${x.played ? i + 1 : ''}</span><span class="nm">${esc(x.name)}</span>
      <span class="wl">${x.played ? `${x.won} won, ${x.played - x.won} lost` : '<span class="muted" style="font-weight:500">No matches yet</span>'}</span></a>`).join('')}</div>`
    : '<div class="card"><h2 class="sec">No players yet</h2><p class="muted">Players are added automatically when you type their name into a new match or tournament.</p></div>')
    + (S.user ? `<button class="btn fab" id="p-add">${IC.plus} Add player</button>` : '');
  const b = $('#p-add'); if (b) b.onclick = () => playerSheet(null);
}
function playerSheet(p) {
  const d = sheet(`<h2>${p ? 'Edit player' : 'Add a player'}</h2><label class="f">Name<input id="p-name" maxlength="80" autocapitalize="words" value="${esc(p ? p.name : '')}"></label>
    <label class="f">Plays in<select id="p-g"><option value="">Not set</option><option value="M" ${p && p.gender === 'M' ? 'selected' : ''}>Men's events</option><option value="F" ${p && p.gender === 'F' ? 'selected' : ''}>Women's events</option></select></label>
    <div class="btns"><button class="btn" data-close>Cancel</button><button class="btn pri" id="p-save">${p ? 'Save' : 'Add player'}</button></div>
    ${p ? '<p style="margin-top:14px"><button class="btn danger wide sm" id="p-del">Delete player</button></p>' : ''}`);
  $('#p-save', d).onclick = () => run(async () => { await api('player_save', { id: p ? p.id : 0, name: $('#p-name', d).value, gender: $('#p-g', d).value }); d.close(); route(); });
  if (p) $('#p-del', d).onclick = () => run(async () => { await api('player_delete', { id: p.id }); d.close(); go('#/players'); });
}
async function vPlayer(id) {
  setTabs(null);
  const d = await api('player', null, { id }), r = d.record;
  setTop(d.player.name, 'Player record', { back: '#/players', right: d.player.can_edit ? `<button class="ib" id="p-edit" aria-label="Edit player">${IC.more}</button>` : '' });
  view().innerHTML = `<div class="stats3"><div><b>${r.played}</b><span>Played</span></div><div><b>${r.won}</b><span>Won</span></div><div><b>${r.played ? Math.round(100 * r.won / r.played) + '%' : '–'}</b><span>Win rate</span></div></div>
    <div class="stats3"><div><b>${r.singles[0]}–${r.singles[1]}</b><span>Singles</span></div><div><b>${r.doubles[0]}–${r.doubles[1]}</b><span>Doubles</span></div><div><b>${r.aces}</b><span>Aces in singles</span></div></div>
    <h2 class="sec">Matches</h2>${d.matches.map(m => matchRow(m)).join('') || '<p class="empty">No matches played yet.</p>'}`;
  const b = $('#p-edit'); if (b) b.onclick = () => playerSheet(d.player);
}

/* ---------- Me / sign in ---------- */
function loadScript(src) { return new Promise((ok, no) => { const s = document.createElement('script'); s.src = src; s.async = true; s.onload = ok; s.onerror = no; document.head.appendChild(s); }); }
function signedIn(r, msg) { S.user = r.user; toast(msg || `Signed in as ${r.user.name}`); go(S.after || '#/'); S.after = null; }
async function vMe() {
  setTabs('me');
  if (S.user) return vAccount();
  setTop('Sign in', TN.club);
  const b = S.boot;
  view().innerHTML = `<div class="signin"><h2>Sign in to score</h2><p class="lead">Anyone can follow matches. Sign in to start a match and keep score. Your Cric-Scorer account works here too.</p>
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
    <h2 class="sec">More</h2><div class="card"><p><a href="${esc(S.boot.main_site)}" target="_blank" rel="noopener"><b>Cric-Scorer</b></a> <span class="muted">cricket scoring for the club, same account</span></p>
      <p class="small muted" style="margin-top:8px"><a href="${esc(S.boot.main_site)}/terms" target="_blank" rel="noopener">Terms of Use</a>, <a href="${esc(S.boot.main_site)}/privacy" target="_blank" rel="noopener">Privacy Policy</a>. RV Tennis ${esc(TN.v)}</p></div>
    <p style="margin-top:16px"><button class="btn wide" id="out">Sign out</button></p>`;
  $('#voice').onchange = e => { S.voice = e.target.checked; localStorage.setItem('tn_voice', S.voice ? '1' : '0'); if (S.voice) say('Score announcements on'); };
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
  const routes = { '': vHome, m: () => vMatch(+id), new: vNew, tours: vTours, t: () => vTour(+id), 'tour-new': vTourNew, players: vPlayers, p: () => vPlayer(+id), me: vMe };
  const fn = routes[p] || vHome;
  $('#app').toggleAttribute('data-wide', ['', 'm', 't', 'tours', 'players', 'p'].includes(routes[p] ? p : ''));   // lists and matches use the whole screen on a laptop or TV
  if ((p === 'new' || p === 'tour-new') && !S.user) S.after = '#/' + p;
  try { await fn(); window.scrollTo(0, 0); }
  catch (e) { setTop(TN.name, TN.club, { back: p ? '#/' : null }); view().innerHTML = `<div class="card"><h2 class="sec">This page could not load</h2><p class="muted">${esc(e.message)}</p><p style="margin-top:12px"><button class="btn" onclick="location.reload()">Try again</button></p></div>`; }
}
(async function start() {
  setTop(TN.name, TN.club);
  try { S.boot = await api('boot'); S.user = S.boot.user; if (S.boot.logo) TN.logo = S.boot.logo; }
  catch (e) { view().innerHTML = `<div class="card"><h2 class="sec">RV Tennis could not start</h2><p class="muted">${esc(e.message)}</p><p style="margin-top:12px"><button class="btn" onclick="location.reload()">Try again</button></p></div>`; return; }
  if (TN.open && !location.hash) history.replaceState(null, '', new URL('./#/m/' + TN.open, document.baseURI));
  window.addEventListener('hashchange', route);
  route();
})();
