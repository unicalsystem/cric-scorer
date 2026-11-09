/* ---------- v1.23 Quick Toss: a stand-alone coin toss, no match or account needed (#/toss) ---------- */
/* State lives in S.qt; the team names are remembered on this phone. */
const QT_KEY = 'cs_toss';
const qtLoad = () => { try { return JSON.parse(localStorage.getItem(QT_KEY) || '{}'); } catch (e) { return {}; } };
const qtSave = () => { try { localStorage.setItem(QT_KEY, JSON.stringify({ a: S.qt.a, b: S.qt.b, hist: S.qt.hist.slice(0, 10) })); } catch (e) { /* private mode */ } };
const qtName = k => (S.qt[k] || '').trim() || (k === 'a' ? 'Team A' : 'Team B');
function qtRandom() {                      // a fair 50/50 using the browser's crypto source
  if (window.crypto && crypto.getRandomValues) { const u = new Uint8Array(1); crypto.getRandomValues(u); return u[0] & 1; }
  return Math.random() < .5 ? 0 : 1;
}
function viewToss() {
  if (!S.qt) { const s = qtLoad(); S.qt = { a: s.a || '', b: s.b || '', hist: s.hist || [], caller: 'a', call: 'heads', step: 'set', coin: null, winner: null, decision: null, spinning: false }; }
  const q = S.qt;
  const nameIn = (k, label) => `<div class="field grow"><label>${label}</label><input class="input" value="${esc(q[k])}" placeholder="${k === 'a' ? 'Team A' : 'Team B'}" maxlength="30" oninput="S.qt.${k}=this.value;qtSave();qtDraw(true)"></div>`;
  const pick = (cls, k, v, html) => `<button class="${q[k] === v ? 'on' : ''}" onclick="S.qt.${k}='${v}';qtDraw(true)">${html}</button>`;
  const other = q.caller === 'a' ? 'b' : 'a';
  const coinFace = q.spinning ? '…' : q.coin ? q.coin.toUpperCase() : 'TOSS';
  let body = '';
  if (q.step === 'set') body = `<div class="card">
      <div class="row" style="gap:10px;align-items:flex-end">${nameIn('a', 'Team A')}<div style="font-weight:800;padding-bottom:22px;color:var(--muted)">vs</div>${nameIn('b', 'Team B')}</div>
      <div class="field"><label>Who calls?</label><div class="toss-pick">${pick('', 'caller', 'a', `<span style="font-size:26px">🗣️</span>${esc(qtName('a'))}`)}${pick('', 'caller', 'b', `<span style="font-size:26px">🗣️</span>${esc(qtName('b'))}`)}</div></div>
      <div class="field"><label>${esc(qtName(q.caller))} calls</label><div class="toss-pick">${pick('', 'call', 'heads', '<span style="font-size:26px">🪙</span>Heads')}${pick('', 'call', 'tails', '<span style="font-size:26px">🔄</span>Tails')}</div></div>
      <button class="btn block lime" style="margin-top:4px" onclick="App.qtFlip()">🪙 Flip the coin</button></div>`;
  else if (q.step === 'flip') body = `<div class="card" style="text-align:center"><div class="sub">${esc(qtName(q.caller))} called <b>${q.call}</b>…</div>
      <div class="qt-coin-wrap"><div class="coin qt-coin ${q.spinning ? 'spin' : ''}" id="qtcoin" onclick="if(!S.qt.spinning)App.qtFlip()">${coinFace}</div></div>
      ${q.spinning ? '<div class="sub">Flipping…</div>' : `<div class="qt-res">It's <b>${esc(q.coin.toUpperCase())}</b>!</div>
      <div class="result-card" style="margin-top:12px"><div class="tr">🏆</div><b>${esc(qtName(q.winner))} won the toss</b><div class="small" style="margin-top:6px">${q.winner === q.caller ? `${esc(qtName(q.caller))} called it right` : `${esc(qtName(q.caller))} called ${q.call} - wrong!`}</div></div>
      <div class="field"><label>${esc(qtName(q.winner))} chose to</label><div class="toss-pick">
        <button class="${q.decision === 'bat' ? 'on' : ''}" onclick="App.qtDecide('bat')"><span style="font-size:30px">🏏</span>Bat first</button>
        <button class="${q.decision === 'bowl' ? 'on' : ''}" onclick="App.qtDecide('bowl')"><span style="font-size:30px">🎯</span>Bowl first</button></div></div>
      <button class="btn light block" onclick="App.qtFlip()">🪙 Flip again</button>`}</div>`;
  else body = `<div class="result-card"><div class="tr">🪙</div><b>${esc(qtName(q.winner))} won the toss</b><div style="margin-top:6px">and chose to <b>${q.decision} first</b></div>
      <div class="small" style="margin-top:8px;opacity:.8">${esc(qtName(q.winner === 'a' ? 'b' : 'a'))} will ${q.decision === 'bat' ? 'bowl' : 'bat'} first · coin showed ${q.coin}</div></div>
    <div class="grid2" style="margin-bottom:10px"><button class="btn" style="background:#25d366" onclick="App.qtShare('wa')">📲 WhatsApp</button><button class="btn light" onclick="App.qtShare()">${navigator.share ? '📤 Share' : '📋 Copy'}</button></div>
    ${S.user ? `<button class="btn block" onclick="go('#/match/new')">🏏 Score this match in Cric-Scorer →</button>` : `<a class="btn block" href="#/register">🏏 Score this match ball by ball - free</a>`}
    <button class="btn light block" style="margin-top:8px" onclick="App.qtReset()">🔁 New toss</button>`;
  const hist = q.hist.length ? `<div class="section"><h2>Recent tosses</h2><a onclick="S.qt.hist=[];qtSave();qtDraw(true)" style="cursor:pointer">Clear</a></div>
    <div class="card flush list">${q.hist.map(h => `<div class="item" style="cursor:default"><span class="tile ic" style="box-shadow:none;padding:0;width:38px;height:38px;justify-content:center">🪙</span><div class="grow"><div class="t">${esc(h.w)} won · ${h.d} first</div><div class="s">${esc(h.a)} vs ${esc(h.b)} · ${h.coin} · ${fmtDate(h.at)}</div></div></div>`).join('')}</div>` : '';
  mount(topbar('Quick Toss', { back: true, refresh: false, sub: 'no match needed' }) + `<div class="content">${body}${hist}
    <p class="small muted" style="text-align:center;margin-top:16px">A fair 50/50 flip. Works offline · Cric-Scorer by Aaryush 🏏</p></div>`, S.user ? 'more' : null);
}
function qtDraw(keep) { if ((location.hash || '') === '#/toss') { const y = window.scrollY; viewToss(); if (keep) window.scrollTo(0, y); } }
App.qtFlip = () => {
  const q = S.qt; if (q.spinning) return;
  q.step = 'flip'; q.spinning = true; q.coin = null; q.winner = null; q.decision = null; qtDraw(true);
  if (window.Sound) Sound.fx('coin'); if (navigator.vibrate) navigator.vibrate(20);
  const face = qtRandom() ? 'heads' : 'tails';
  setTimeout(() => {
    q.coin = face; q.winner = (face === q.call) ? q.caller : (q.caller === 'a' ? 'b' : 'a'); q.spinning = false;
    if (window.Sound) Sound.fx('correct'); if (navigator.vibrate) navigator.vibrate([15, 30, 15]);
    qtDraw(true);
  }, 1500);
};
App.qtDecide = d => {
  const q = S.qt; q.decision = d; q.step = 'done';
  q.hist.unshift({ a: qtName('a'), b: qtName('b'), w: qtName(q.winner), d, coin: q.coin, at: new Date().toISOString() }); q.hist = q.hist.slice(0, 10); qtSave();
  if (window.Sound) Sound.fx('cheer'); qtDraw(false);
};
App.qtReset = () => { const q = S.qt; q.step = 'set'; q.coin = null; q.winner = null; q.decision = null; q.caller = q.caller === 'a' ? 'b' : 'a'; qtDraw(false); };
const qtText = () => { const q = S.qt; return `🪙 Toss: ${qtName(q.winner)} won the toss and chose to ${q.decision} first.\n${qtName('a')} vs ${qtName('b')} · coin: ${q.coin}\n\nScored with Cric-Scorer 🏏 ${typeof SITE !== 'undefined' ? SITE : location.origin}`; };
App.qtShare = async how => {
  const text = qtText();
  if (how === 'wa') return window.open('https://wa.me/?text=' + encodeURIComponent(text), '_blank', 'noopener');
  if (navigator.share) { try { await navigator.share({ title: 'Toss result', text }); } catch (e) { /* cancelled */ } return; }
  try { await navigator.clipboard.writeText(text); toast('Toss result copied ✓'); } catch (e) { toast('Could not copy', true); }
};
ROUTES.unshift([/^#\/toss$/, viewToss, true]);

/* entry points: Home tile, More menu, login page, and the in-match toss screen */
const _viewHomeQt = viewHome;
viewHome = async () => {
  await _viewHomeQt();
  const tiles = document.querySelector('.tiles'); if (!tiles) return;
  tiles.insertAdjacentHTML('afterbegin', `<div class="tile" onclick="go('#/toss')"><span class="ic">🪙</span>Quick Toss</div>`);
};
ROUTES.forEach(r => { if (r[1] === _viewHomeQt) r[1] = viewHome; });
const _viewMoreQt = viewMore;
viewMore = () => {
  _viewMoreQt();
  const list = document.querySelector('.card.flush.list'); if (!list) return;
  const anchor = [...list.children].find(x => x.textContent.includes('Match types')) || list.firstElementChild;
  anchor.insertAdjacentHTML('beforebegin', `<div class="item" onclick="go('#/toss')"><span class="tile ic" style="box-shadow:none;padding:0;width:38px;height:38px;justify-content:center">🪙</span><div class="grow"><div class="t">Quick Toss</div><div class="s">Flip a fair coin for any match - no setup needed</div></div><span class="chev">${I.chev}</span></div>`);
};
ROUTES.forEach(r => { if (r[1] === _viewMoreQt) r[1] = viewMore; });
const _viewLoginQt = viewLogin;
viewLogin = () => {
  _viewLoginQt();
  const sw = document.querySelector('.auth .switch-link'); if (sw) sw.insertAdjacentHTML('afterend', `<div class="switch-link">Just need a coin? <a href="#/toss">🪙 Quick Toss</a></div>`);
};
ROUTES.forEach(r => { if (r[1] === _viewLoginQt) r[1] = viewLogin; });
