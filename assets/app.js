/* Cric-Scorer by Aaryush - mobile web app */
'use strict';

const App = {};
const S = { user: null, m: null, mTab: 'card', cardInn: null, timers: [], busy: false, deferredInstall: null, mtab: 'live', mine: false, teamCat: null, tTab: 'matches', statTab: 'runs' };
const $ = (s, el = document) => el.querySelector(s);
const esc = v => String(v ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const go = h => { if (location.hash === h) render(); else location.hash = h; };
const plural = (n, w) => `${n} ${w}${n === 1 ? '' : (/(ch|sh|s|x)$/.test(w) ? 'es' : 's')}`;

/* ---------- Icons ---------- */
const sv = p => `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">${p}</svg>`;
const I = {
  home: sv('<path d="M3 11l9-7 9 7"/><path d="M5 10v10h14V10"/><path d="M10 20v-6h4v6"/>'),
  ball: sv('<circle cx="12" cy="12" r="9"/><path d="M7 4.5c3 4 3 11 0 15M17 4.5c-3 4-3 11 0 15"/>'),
  team: sv('<circle cx="9" cy="8" r="3.2"/><path d="M3 20c0-3.5 2.7-6 6-6s6 2.5 6 6"/><circle cx="17" cy="9" r="2.6"/><path d="M16 14c3 0 5 2.2 5 5"/>'),
  trophy: sv('<path d="M8 4h8v5a4 4 0 01-8 0V4z"/><path d="M8 6H5a3 3 0 003 4M16 6h3a3 3 0 01-3 4"/><path d="M12 13v4M8 21h8M9 17h6"/>'),
  more: sv('<circle cx="5" cy="12" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="19" cy="12" r="1.6"/>'),
  back: sv('<path d="M15 18l-6-6 6-6"/>'),
  plus: sv('<path d="M12 5v14M5 12h14"/>'),
  search: sv('<circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/>'),
  share: sv('<circle cx="18" cy="5" r="2.5"/><circle cx="6" cy="12" r="2.5"/><circle cx="18" cy="19" r="2.5"/><path d="M8.2 10.8l7.6-4.4M8.2 13.2l7.6 4.4"/>'),
  dots: sv('<circle cx="12" cy="5" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="12" cy="19" r="1.6"/>'),
  chev: sv('<path d="M9 6l6 6-6 6"/>'),
  edit: sv('<path d="M4 20h4L19 9l-4-4L4 16v4z"/>'),
  refresh: sv('<path d="M20 11a8 8 0 10-2.3 5.7"/><path d="M20 5v6h-6"/>'),
  learn: sv('<path d="M2 9l10-5 10 5-10 5z"/><path d="M6 11v5c3 2.5 9 2.5 12 0v-5"/><path d="M22 9v6"/>'),
};
const LOGO = `<svg class="logo" viewBox="0 0 64 64" aria-label="Cric-Scorer logo"><rect width="64" height="64" rx="18" fill="#0d4a36"/><rect x="2" y="2" width="60" height="60" rx="16" fill="none" stroke="#b6e94a" stroke-opacity=".35" stroke-width="1.5" stroke-dasharray="3 3"/>
<g fill="#f6ecd2"><rect x="13" y="17" width="4.5" height="32" rx="2.2"/><rect x="21" y="17" width="4.5" height="32" rx="2.2"/><rect x="29" y="17" width="4.5" height="32" rx="2.2"/><rect x="12" y="13.5" width="10" height="3" rx="1.5"/><rect x="24.5" y="13.5" width="10" height="3" rx="1.5"/></g>
<circle cx="44" cy="40" r="12.5" fill="#d62839"/><path d="M36 31.5c5 5 5 12 0 17.5M52 31.5c-5 5-5 12 0 17.5" stroke="#fff" stroke-width="1.6" fill="none" stroke-dasharray="2.2 2.2"/><circle cx="40" cy="35" r="3" fill="#fff" opacity=".25"/></svg>`;

/* ---------- Helpers ---------- */
function textOn(hex) {
  const h = String(hex || '#0d4a36').replace('#', '');
  const r = parseInt(h.substr(0, 2), 16), g = parseInt(h.substr(2, 2), 16), b = parseInt(h.substr(4, 2), 16);
  return (0.299 * r + 0.587 * g + 0.114 * b) / 255 > 0.62 ? '#13241b' : '#ffffff';
}
function badge(t, cls = '') {
  if (!t) return '';
  return `<span class="badge ${cls}" style="background:${esc(t.color)};color:${textOn(t.color)}">${esc(String(t.short_name || t.name || '?').slice(0, 4))}</span>`;
}
const ROLE_ICON = { 'Batter': '🏏', 'Wicket-keeper': '🧤', 'All-rounder': '⭐', 'Bowler': '🎯' };
const roleIcon = r => ROLE_ICON[r] || '🏏';
function fmtDate(s, withTime = true) {
  if (!s) return '';
  const d = new Date(String(s).replace(' ', 'T'));
  if (isNaN(d)) return s;
  const o = { day: 'numeric', month: 'short' };
  if (d.getFullYear() !== new Date().getFullYear()) o.year = 'numeric';
  return d.toLocaleDateString('en-IN', o) + (withTime ? ', ' + d.toLocaleTimeString('en-IN', { hour: 'numeric', minute: '2-digit' }) : '');
}
function localDT(d = new Date()) {
  const p = n => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())}T${p(d.getHours())}:${p(d.getMinutes())}`;
}
const sk = n => '<div class="skeleton"></div>'.repeat(n);
function formData(form) {
  const o = {};
  for (const el of form.elements) {
    if (!el.name) continue;
    if (el.type === 'checkbox') o[el.name] = el.checked ? 1 : 0;
    else o[el.name] = el.value;
  }
  return o;
}

async function api(action, data = {}) {
  const tok = S.routeTok;       // which screen asked - used to drop answers that arrive after you've moved on
  let r;
  try {
    r = await fetch('api.php?action=' + action, {
      method: 'POST', credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'CricScorer', 'X-App-Version': window.CS_VERSION || '' },
      body: JSON.stringify(data),
    });
  } catch (e) { throw new Error('No internet connection. Please try again.'); }
  let j;
  try { j = await r.json(); } catch (e) { throw new Error('Server error (' + r.status + ')'); }
  if (tok !== S.routeTok) { const st = new Error(''); st.stale = true; throw st; }
  if (r.status === 401 && !['me', 'login'].includes(action)) { S.user = null; S.after = location.hash; go('#/login'); }
  if (j.error) throw new Error(j.error);
  return j;
}
let toastTimer;
function toast(msg, err = false) {
  if (!msg) return;
  const t = $('#toast');
  t.textContent = msg; t.className = 'show' + (err ? ' err' : '');
  clearTimeout(toastTimer); toastTimer = setTimeout(() => t.className = '', 2600);
}
async function run(fn, btn) {
  try { if (btn) btn.disabled = true; return await fn(); }
  catch (e) { if (!e.stale) toast(e.message || 'Something went wrong', true); }
  finally { if (btn) btn.disabled = false; }
}

/* ---------- Shell ---------- */
function topbar(title, { back = false, actions = '', sub = '', refresh = true } = {}) {
  return `<header class="topbar">${back ? `<button class="icon-btn" aria-label="Back" onclick="App.back()">${I.back}</button>` : ''}
    <h1 data-testid="page-title">${esc(title)}${sub ? `<span class="sub">${esc(sub)}</span>` : ''}</h1>${refresh ? `<button class="icon-btn refresh-btn" aria-label="Refresh" onclick="App.refresh()">${I.refresh}</button>` : ''}${actions}</header>`;
}
let navDepth = -1;
App.back = () => { if (navDepth > 0) history.back(); else go('#/'); };
function mount(html, tab, { keepScroll = false } = {}) {
  const app = $('#app');
  const y = window.scrollY;
  app.innerHTML = html;
  app.classList.toggle('no-tabbar', !tab);
  renderTabbar(tab);
  window.scrollTo(0, keepScroll ? y : 0);
}
function renderTabbar(tab) {
  const bar = $('#tabbar');
  if (!tab || !S.user) { bar.classList.add('hidden'); return; }
  bar.classList.remove('hidden');
  const items = [['home', '#/', 'Home', I.home], ['matches', '#/matches', 'Matches', I.ball], ['learn', '#/learn', 'Learn', I.learn], ['teams', '#/teams', 'Teams', I.team], ['tournaments', '#/tournaments', 'Cups', I.trophy], ['more', '#/more', 'More', I.more]];
  bar.innerHTML = `<div class="wrap">${items.map(([k, h, l, ic]) => `<a href="${h}" class="${k === tab ? 'on' : ''}">${ic}<span>${l}</span><i class="pip"></i></a>`).join('')}</div>`;
}
function sheet(html, { locked = false } = {}) {
  $('#sheet-root').innerHTML = `<div class="sheet-bg" ${locked ? '' : 'onclick="closeSheet()"'}></div><div class="sheet" role="dialog"><div class="grab"></div>${html}</div>`;
}
function closeSheet() { $('#sheet-root').innerHTML = ''; }
function confirmSheet(title, text, label, fn, danger = true) {
  App._confirm = fn;
  sheet(`<h3>${esc(title)}</h3><div class="sub">${esc(text)}</div>
    <div class="grid2"><button class="btn light" onclick="closeSheet()">Cancel</button><button class="btn ${danger ? 'red' : ''}" onclick="App._confirm()">${esc(label)}</button></div>`);
}

/* ---------- Router ---------- */
const ROUTES = [
  [/^#\/login$/, viewLogin, true],
  [/^#\/register$/, viewRegister, true],
  [/^#\/consent$/, viewConsent, true],
  [/^#\/?$/, viewHome],
  [/^#\/matches$/, viewMatches],
  [/^#\/match\/new(?:\?t=(\d+))?$/, viewMatchNew],
  [/^#\/match\/(\d+)$/, viewMatch, true],
  [/^#\/teams(?:\?c=(\w+))?$/, viewTeams],
  [/^#\/team\/new$/, () => viewTeamForm(null)],
  [/^#\/team\/(\d+)\/edit$/, viewTeamForm],
  [/^#\/team\/(\d+)$/, viewTeam],
  [/^#\/player\/(\d+)$/, viewPlayer],
  [/^#\/tournaments$/, viewTournaments],
  [/^#\/tournament\/new$/, () => viewTournamentForm(null)],
  [/^#\/tournament\/(\d+)\/edit$/, viewTournamentForm],
  [/^#\/tournament\/(\d+)$/, viewTournament],
  [/^#\/stats$/, viewStats],
  [/^#\/news$/, viewNews],
  [/^#\/types$/, viewTypes],
  [/^#\/profile$/, viewProfile],
  [/^#\/more$/, viewMore],
  [/^#\/people(?:\?t=(\w+))?$/, viewPeople],
  [/^#\/academies$/, viewAcademies],
  [/^#\/academy\/new$/, () => viewAcademyForm(0)],
  [/^#\/academy\/(\d+)\/edit$/, viewAcademyForm],
  [/^#\/academy\/(\d+)$/, viewAcademy],
  [/^#\/wa$/, viewWaLogin, true],
  [/^#\/user\/(\d+)\/(followers|following)$/, viewFollowList],
  [/^#\/user\/(\d+)$/, viewUser],
  [/^#\/apl$/, () => apl('home')],
  [/^#\/apl\/new$/, () => apl('create')],
  [/^#\/apl\/join(?:\/([A-Za-z0-9]{1,8}))?$/, code => apl('join', code || '')],
  [/^#\/apl\/room\/([A-Za-z0-9]{8})$/, code => apl('room', code)],
  [/^#\/apl\/teams$/, () => apl('teams')],
  [/^#\/apl\/players$/, () => apl('gallery')],
  [/^#\/apl\/rules$/, () => apl('rules')],
  [/^#\/apl\/help$/, () => apl('help')],
  [/^#\/play$/, () => duel('lobby')],
  [/^#\/play\/squad$/, () => duel('squad')],
  [/^#\/play\/room\/(\d+)$/, code => duel('room', code)],
  [/^#\/play\/join\/(\d+)$/, code => duel('join', code)],
  [/^#\/admin$/, () => withScript('Admin', 'assets/admin.js').then(() => Admin.show())],
  [/^#\/help$/, viewHelp],
  [/^#\/learn$/, () => academy('home')],
  [/^#\/learn\/lesson\/(\w+)$/, id => academy('lesson', id)],
  [/^#\/learn\/quiz\/(\w+)$/, id => academy('quiz', id)],
  [/^#\/learn\/certificate$/, () => academy('certificate')],
];
/* Cricket Academy code loads only when the Learn tab is first opened */
function loadAcademy() {
  if (window.Academy) return Promise.resolve();
  return new Promise((ok, bad) => {
    const s = document.createElement('script');
    s.src = 'assets/academy.js?v=' + (window.CS_VERSION || '');
    s.onload = ok; s.onerror = () => bad(new Error('Could not load the Academy. Check your connection.'));
    document.head.appendChild(s);
  });
}
async function academy(fn, ...args) {
  if (!window.Academy) mount(topbar('Cricket Academy') + `<div class="content">${sk(4)}</div>`, 'learn');
  await loadAcademy();
  return Academy[fn](...args);
}
/* Gully Duel and the admin portal load only when first opened */
function withScript(name, src, css) {
  if (window[name]) return Promise.resolve();
  if (css && !document.querySelector(`link[data-css="${css}"]`)) { const l = document.createElement('link'); l.rel = 'stylesheet'; l.href = css + '?v=' + (window.CS_VERSION || ''); l.dataset.css = css; document.head.appendChild(l); }
  return new Promise((ok, bad) => {
    const s = document.createElement('script');
    s.src = src + '?v=' + (window.CS_VERSION || '');
    s.onload = ok; s.onerror = () => bad(new Error('Could not load this page. Check your connection.'));
    document.head.appendChild(s);
  });
}
async function duel(fn, ...args) {
  if (!S.user.features || !S.user.features.game) {
    mount(topbar('Gully Duel', { back: true }) + `<div class="content"><div class="card empty"><div class="big">🎮</div><b>Coming soon!</b><br>Gully Duel is being tried out by a few players first. It will appear here when it's switched on for you.</div></div>`, 'more');
    return;
  }
  if (!window.Duel) mount(topbar('Gully Duel', { back: true, refresh: false }) + `<div class="content">${sk(3)}</div>`, 'more');
  await withScript('GE', 'assets/game-engine.js', 'assets/game.css');
  await withScript('Duel', 'assets/game.js');
  return Duel[fn](...args);
}
async function apl(fn, ...args) {
  if (!S.user.features || !S.user.features.auction) {
    mount(topbar('APL Auction', { back: true }) + `<div class="content"><div class="card empty"><div class="big">🔨</div><b>Coming soon!</b><br>The APL Auction is being tried out by a few players first. It will appear here when it's switched on for you.</div></div>`, 'more');
    return;
  }
  if (!window.Auction) mount(topbar('APL Auction', { back: true, refresh: false }) + `<div class="content">${sk(3)}</div>`, 'more');
  await withScript('Auction', 'assets/auction.js', 'assets/auction.css');
  return Auction[fn](...args);
}
function clearTimers() { S.timers.forEach(clearInterval); S.timers = []; if (window.Auction) Auction.stop(); }
async function render() {
  clearTimers(); closeSheet();
  if (window.Duel) Duel.leave();
  const h = location.hash || '#/';
  const tok = S.routeTok = (S.routeTok || 0) + 1;
  // Visitors who are not signed in see the home page (#landing) instead of a login box
  if (!S.user && (h === '#/' || h === '#')) { document.body.classList.remove('app-mode'); if (S.landing && !document.getElementById('landing')) document.body.insertBefore(S.landing, $('#app')); $('#app').innerHTML = ''; window.scrollTo(0, 0); return; }
  document.body.classList.add('app-mode');
  const lp = document.getElementById('landing'); if (lp) { S.landing = lp; lp.remove(); }
  for (const [re, fn, pub] of ROUTES) {
    const mm = h.match(re);
    if (!mm) continue;
    if (!pub && !S.user) { S.after = h; go('#/login'); return; }
    try { await fn(...mm.slice(1)); }
    catch (e) { if (e.stale || tok !== S.routeTok) return; mount(topbar('Oops', { back: true }) + `<div class="content"><div class="empty"><div class="big">🏏</div>${esc(e.message)}</div></div>`, S.user ? 'home' : null); }
    return;
  }
  go('#/');
}

/* ---------- Auth: username/password, Sign in with Google, reCAPTCHA ---------- */
const scriptCache = {};
function loadScript(src) {
  if (!scriptCache[src]) scriptCache[src] = new Promise((ok, bad) => {
    const el = document.createElement('script');
    el.src = src; el.async = true; el.defer = true;
    el.onload = ok; el.onerror = () => { delete scriptCache[src]; bad(new Error('Could not load ' + new URL(src).hostname)); };
    document.head.appendChild(el);
  });
  return scriptCache[src];
}
const darkMode = () => window.matchMedia && matchMedia('(prefers-color-scheme: dark)').matches;
const iosStandalone = () => window.navigator.standalone === true;

/* Google Identity Services: one initialise, callback routed to login or link */
let gisReady = null;
function initGoogle() {
  const cid = S.auth && S.auth.google_client_id;
  if (!cid) return Promise.reject(new Error('Google sign-in is off'));
  if (!gisReady) gisReady = loadScript('https://accounts.google.com/gsi/client').then(() => {
    const opts = { client_id: cid, callback: r => App.onGoogle(r), auto_select: false, cancel_on_tap_outside: true, itp_support: true };
    if (iosStandalone()) { opts.ux_mode = 'redirect'; opts.login_uri = location.origin + location.pathname.replace(/[^/]*$/, '') + 'google-callback.php'; }
    google.accounts.id.initialize(opts);
  }).catch(e => { gisReady = null; throw e; });
  return gisReady;
}
async function renderGoogleButton(id, text) {
  const el = document.getElementById(id);
  if (!el) return;
  try {
    await initGoogle();
    if (!document.getElementById(id)) return;
    el.innerHTML = '';
    const w = Math.max(220, Math.min(400, Math.floor(el.getBoundingClientRect().width || 300)));
    google.accounts.id.renderButton(el, { type: 'standard', theme: darkMode() ? 'filled_black' : 'outline', size: 'large', text, shape: 'pill', logo_alignment: 'left', width: w });
  } catch (e) {
    el.innerHTML = `<div class="small muted" style="text-align:center">Google sign-in could not load. Check your internet connection.</div>`;
  }
}
App.onGoogle = resp => {
  if (!resp || !resp.credential) return toast('Google sign-in was cancelled', true);
  const linking = S.gMode === 'link';
  run(async () => {
    const r = await api(linking ? 'google_link' : 'google_login', { credential: resp.credential });
    if (r.needs_consent) { S.gPending = { email: r.email, name: r.name }; go('#/consent'); return; }
    S.user = r.user;
    if (linking) { toast('Google account linked ✓'); viewProfile(); return; }
    toast(r.created ? `Welcome to Cric-Scorer, ${r.user.display_name}! 🎉` : `Welcome back, ${r.user.display_name}! 👋`);
    const a = S.after; S.after = null; go(a && !/^#\/(login|register)/.test(a) ? a : '#/');
  });
};

/* reCAPTCHA v2 (tick box) or v3 (invisible) */
let capLoad = null;
function loadCaptcha() {
  const rc = S.auth && S.auth.recaptcha;
  if (!rc) return Promise.resolve(null);
  if (!capLoad) capLoad = (rc.version === 'v3'
      ? loadScript('https://www.google.com/recaptcha/api.js?render=' + encodeURIComponent(rc.site_key))
      : new Promise((ok, bad) => { window.__capReady = ok; loadScript('https://www.google.com/recaptcha/api.js?render=explicit&onload=__capReady').catch(bad); })
    ).then(() => new Promise(ok => grecaptcha.ready(ok))).catch(e => { capLoad = null; throw e; });
  return capLoad;
}
const CAP_FAIL = 'The security check (Google reCAPTCHA) could not load. Check your internet connection, VPN or ad-blocker and try again.';
const withTimeout = (p, ms) => Promise.race([p, new Promise((_, bad) => setTimeout(() => bad(new Error(CAP_FAIL)), ms))]);
async function renderCaptcha() {
  const rc = S.auth && S.auth.recaptcha, el = document.getElementById('cap');
  S.capId = null;
  if (!rc || !el) return;
  if (rc.version === 'v3') { el.innerHTML = '<div class="cap-note">Protected by reCAPTCHA · Google <a href="https://policies.google.com/privacy" target="_blank" rel="noopener">Privacy</a> & <a href="https://policies.google.com/terms" target="_blank" rel="noopener">Terms</a></div>'; loadCaptcha().catch(() => {}); return; }
  el.innerHTML = '<div class="small muted">Loading security check…</div>';
  try {
    await withTimeout(loadCaptcha(), 10000);
    const box = document.getElementById('cap');
    if (!box) return;
    box.innerHTML = '';
    const holder = document.createElement('div'); box.appendChild(holder);
    S.capId = grecaptcha.render(holder, { sitekey: rc.site_key, theme: darkMode() ? 'dark' : 'light', size: box.getBoundingClientRect().width < 304 ? 'compact' : 'normal' });
  } catch (e) {
    el.innerHTML = '<div class="small" style="color:var(--red)">' + CAP_FAIL + ' <a class="link" href="#" onclick="event.preventDefault();capLoad=null;renderCaptcha()">Retry</a></div>';
  }
}
async function captchaToken(action) {
  const rc = S.auth && S.auth.recaptcha;
  if (!rc) return '';
  if (rc.version === 'v3') {
    try { await withTimeout(loadCaptcha(), 10000); return await withTimeout(grecaptcha.execute(rc.site_key, { action }), 10000); }
    catch (e) { capLoad = null; throw new Error(CAP_FAIL); }
  }
  if (S.capId === null || !window.grecaptcha) throw new Error(CAP_FAIL);
  const t = grecaptcha.getResponse(S.capId);
  if (!t) throw new Error('Please tick "I\'m not a robot"');
  return t;
}
function resetCaptcha() { try { if (S.capId !== null && window.grecaptcha) grecaptcha.reset(S.capId); } catch (e) { /* ignore */ } }

function authPage(inner) {
  return `<div class="auth"><a href="#/" class="auth-home" aria-label="Home">← Home</a>${LOGO}<h1 class="brand" data-testid="app-title">Cric-Scorer</h1><div class="tag">by Aaryush</div>${inner}
    <div class="legal-links"><a href="learn/">Learn cricket</a> · <a href="privacy">Privacy Policy</a> · <a href="terms">Terms of Use</a></div>
    <span class="seam">● ● ● LIVE CRICKET SCORING ● ● ●</span></div>`;
}
function googleBlock(id, signup) {
  if (!S.auth || !S.auth.google_client_id || (signup && !S.auth.google_signup)) return '';
  return `<div id="${id}" class="gbtn"></div><div class="or"><span>or ${signup ? 'make a username' : 'use your username'}</span></div>`;
}
const consentBox = () => `<label class="consent"><input type="checkbox" name="consent" required><span>I agree to the <a href="terms" target="_blank">Terms of Use</a> and <a href="privacy" target="_blank">Privacy Policy</a>. If I'm under 18, my parent or guardian agrees too.</span></label>`;
/* A brand-new Google player agrees to the Terms first - nothing is saved until they do */
function viewConsent() {
  if (S.user) return go('#/');
  const g = S.gPending || {};
  mount(authPage(`<form class="panel" onsubmit="App.consentGo(event)"><h2>One last step ✋</h2>
    <p class="small muted" style="margin-top:0">${g.email ? `You're joining with <b>${esc(g.email)}</b>. ` : ''}Before we create your account, please read and agree:</p>
    ${consentBox()}
    <button class="btn block">Create my account</button></form>
    <div class="switch-link"><a href="#/login">Cancel</a></div>`), null);
}
App.consentGo = e => {
  e.preventDefault();
  run(async () => {
    const r = await api('google_login', { pending: 1, consent: 1 });
    S.user = r.user; S.gPending = null;
    toast(`Welcome to Cric-Scorer, ${r.user.display_name}! 🎉`);
    const a = S.after; S.after = null; go(a && !/^#\/(login|register|consent)/.test(a) ? a : '#/');
  }, e.submitter);
};
function viewLogin() {
  if (S.user) return go('#/');
  S.gMode = 'login';
  mount(authPage(`<form class="panel" onsubmit="App.login(event)"><h2>Welcome back! 👋</h2>
    ${googleBlock('gbtn', false)}
    <div class="field"><label>Username</label><input class="input" name="username" autocapitalize="none" autocomplete="username" required></div>
    <div class="field"><label>Password</label><input class="input" type="password" name="password" autocomplete="current-password" required></div>
    <div id="cap" class="cap"></div>
    <button class="btn block">Log in</button></form>
    ${S.auth && S.auth.whatsapp ? `<a class="btn block" href="#/wa" onclick="S.waMode='login'" style="background:#25d366;margin-top:10px">📲 Sign in with WhatsApp</a>` : ''}
    <div class="switch-link">New player? <a href="#/register">Create an account</a></div>`), null);
  renderGoogleButton('gbtn', 'signin_with'); renderCaptcha();
}
function viewRegister() {
  if (S.user) return go('#/');
  S.gMode = 'login';
  mount(authPage(`<form class="panel" onsubmit="App.register(event)"><h2>Join the team 🏆</h2>
    ${googleBlock('gbtn', true)}
    <div class="field"><label>Your name</label><input class="input" name="display_name" placeholder="e.g. Aarush" required></div>
    <div class="field"><label>Username</label><input class="input" name="username" autocapitalize="none" autocomplete="username" placeholder="letters & numbers" required></div>
    <div class="field"><label>Password <span class="muted">(6+ characters)</span></label><div class="pw"><input class="input" type="password" name="password" autocomplete="new-password" minlength="6" required><button type="button" class="eye" aria-label="Show password" onclick="App.togglePw(this)">👁</button></div></div>
    ${consentBox()}
    <div id="cap" class="cap"></div>
    <button class="btn block">Create account</button></form>
    <div class="switch-link">Already have an account? <a href="#/login">Log in</a></div>`), null);
  renderGoogleButton('gbtn', 'signup_with'); renderCaptcha();
}
App.login = e => {
  e.preventDefault();
  const f = formData(e.target);
  run(async () => {
    try {
      f.captcha = await captchaToken('login');
      const r = await api('login', f); S.user = r.user; const a = S.after; S.after = null; go(a && a !== '#/login' ? a : '#/');
    } catch (err) { resetCaptcha(); throw err; }
  }, e.submitter);
};
App.togglePw = b => { const i = b.previousElementSibling; const show = i.type === 'password'; i.type = show ? 'text' : 'password'; b.textContent = show ? '🙈' : '👁'; b.setAttribute('aria-label', show ? 'Hide password' : 'Show password'); };
App.register = e => {
  e.preventDefault();
  const f = formData(e.target);
  if (String(f.password || '').length < 6) return toast('Password needs at least 6 characters', true);
  run(async () => {
    try {
      f.captcha = await captchaToken('register');
      const r = await api('register', f); S.user = r.user; toast('Welcome, ' + r.user.display_name + '! 🎉'); go('#/');
    } catch (err) { resetCaptcha(); throw err; }
  }, e.submitter);
};
App.logout = () => run(async () => { await api('logout'); S.user = null; try { if (window.google && google.accounts) google.accounts.id.disableAutoSelect(); } catch (e) { /* ignore */ } go('#/login'); });

/* ---------- Match card ---------- */
function matchCard(m) {
  const st = m.status === 'live' ? '<span class="pill live">Live</span>' : m.status === 'scheduled' ? '<span class="pill up">Upcoming</span>'
    : m.status === 'abandoned' ? '<span class="pill">No result</span>' : '<span class="pill done">Result</span>';
  const row = (t, sc) => `<div class="tm ${t && m.winner_team_id === t.id ? 'win' : ''}">${badge(t, 'sm')}<span class="n">${esc(t?.name)}</span><span class="sc">${esc(sc || '')}</span></div>`;
  const foot = m.result_text ? `<div class="foot">${esc(m.result_text)}</div>`
    : `<div class="foot m">${esc(fmtDate(m.match_date))}${m.venue ? ' · ' + esc(m.venue) : ''} · ${m.format === 'test' ? plural(m.days, 'day') + ' Test' : m.overs + ' ov'}</div>`;
  const by = m.owner && S.user && m.owner.id !== S.user.id ? ` · by ${esc(m.owner.name)}` : '';
  const lock = m.visibility === 'private' ? '<span class="pill lock" title="Private match">🔒 Private</span>' : '';
  return `<div class="mcard ${m.status}" onclick="go('#/match/${m.id}')"><div class="top"><span class="ellipsis">${esc(m.title || 'Match')}${by}</span><span class="row" style="gap:5px;flex:none">${lock}${st}</span></div>${row(m.team1, m.score1)}${row(m.team2, m.score2)}${foot}</div>`;
}

/* ---------- Home ---------- */
async function viewHome() {
  const name = S.user.display_name.split(' ')[0];
  const hour = new Date().getHours();
  const hi = hour < 12 ? 'Good morning' : hour < 17 ? 'Good afternoon' : 'Good evening';
  const hero = `<div class="hero"><div class="brand">${LOGO}<div class="grow"><b>Cric-Scorer</b><span>by Aaryush</span></div><button class="icon-btn refresh-btn" aria-label="Refresh" onclick="App.refresh()">${I.refresh}</button></div>
    <h2>${hi}, ${esc(name)}! 👋</h2><p>Ready to score today's big match?</p>
    <div class="cta"><button class="btn lime" onclick="go('#/match/new')">${I.plus} New Match</button><button class="btn ghost" onclick="go('#/tournaments')">${I.trophy} Tournaments</button></div></div>`;
  mount(hero + `<div class="content">${sk(3)}</div>`, 'home');
  const d = await api('home');
  if (d.announcements) { S.extras = S.extras || {}; S.extras.announcements = d.announcements; }
  const install = S.deferredInstall ? `<div class="install-banner"><span style="font-size:28px">📲</span><div class="grow"><b>Install Cric-Scorer</b><div class="small muted">Add it to your home screen like an app</div></div><button class="btn sm" onclick="App.install()">Install</button></div>` : '';
  const sec = (t, list, more, empty) => `<div class="section"><h2>${t}</h2>${more ? `<a href="${more}">See all</a>` : ''}</div>` + (list.length ? list.map(matchCard).join('') : (empty || ''));
  mount(hero + `<div class="stats-row"><div class="stat"><b>${d.counts.matches}</b><span>My matches</span></div><div class="stat"><b>${d.counts.teams}</b><span>My teams</span></div><div class="stat"><b>${d.counts.tournaments}</b><span>My cups</span></div></div>
    <div class="content">${announcementsHtml()}${install}
    ${d.live.length ? sec('🔴 Live now', d.live, '#/matches') : ''}
    ${d.upcoming.length ? sec('Up next', d.upcoming) : ''}
    ${homeTours(d.tournaments || [])}
    <div id="home-news">${S.newsCache ? newsBlock(S.newsCache.items.slice(0, 4), true) : ''}</div>
    ${d.following && d.following.length ? sec('👥 From players you follow', d.following, '#/matches') : ''}
    ${sec('Recent results', d.recent, '#/matches', `<div class="card empty"><div class="big">🏏</div>No matches yet.<br>Tap <b>New Match</b> to score your first game!</div>`)}
    ${S.user.features && (S.user.features.auction || S.user.features.game) ? '<div class="section"><h2>🎮 Games</h2></div>' : ''}
    ${S.user.features && S.user.features.auction ? `<div class="duel-tile apl-tile" onclick="go('#/apl')"><span class="ic">🔨</span><div class="grow"><b>APL Auction</b><span>Aaryush Premier League - bid virtual coins for IPL stars with friends</span></div><span class="go">Play</span></div>` : ''}
    ${S.user.features && S.user.features.game ? `<div class="duel-tile" onclick="go('#/play')"><span class="ic">🎮</span><div class="grow"><b>Gully Duel</b><span>Bat vs bowl with a friend - or the computer!</span></div><span class="go">Play</span></div>` : ''}
    <div class="section"><h2>Explore</h2></div>
    <div class="tiles">
      <div class="tile" onclick="go('#/teams?c=ipl')"><span class="ic">🏟️</span>IPL Teams</div>
      <div class="tile" onclick="go('#/teams?c=international')"><span class="ic">🌍</span>International</div>
      <div class="tile" onclick="go('#/teams?c=custom')"><span class="ic">🧢</span>Our Teams</div>
      <div class="tile" onclick="go('#/stats')"><span class="ic">📊</span>Leaderboard</div>
      <div class="tile" style="grid-column:1/-1;background:linear-gradient(135deg,#fff7df,#ffeec2);color:#5b3d00" onclick="go('#/learn')"><span class="ic" style="background:#fff">🎓</span><div class="grow">Cricket Academy<div class="small" style="font-weight:700;opacity:.8">Lessons, quizzes & your certificate</div></div></div>
    </div></div>`, 'home');
  App.loadNews(4);
}
/* v1.28: tournaments that haven't finished - swipeable cards on Home */
function homeTours(list) {
  if (!list.length) return '';
  return `<div class="section"><h2>🏆 Upcoming tournaments</h2><a href="#/tournaments">See all</a></div>
    <div class="h-scroll">${list.map(t => `<div class="card tour-card" onclick="go('#/tournament/${t.id}')"><div class="row" style="gap:10px"><span style="font-size:28px">🏆</span><div class="grow" style="min-width:0"><b class="ellipsis" style="display:block">${esc(t.name)}</b><div class="small muted">${plural(+t.teams, 'team')} · ${plural(+t.matches, 'match')}</div></div></div>
      <div class="small" style="margin-top:8px">📅 ${t.start_date ? esc(fmtDate(t.start_date, false)) : 'Dates to be announced'}${t.end_date && t.end_date !== t.start_date ? ' – ' + esc(fmtDate(t.end_date, false)) : ''}</div>${t.venue ? `<div class="small muted ellipsis">📍 ${esc(t.venue)}</div>` : ''}
      <div style="margin-top:8px">${stPill(t.state)}</div></div>`).join('')}</div>`;
}
/* v1.28: real-world cricket news (server-cached RSS) */
function newsAgo(ts) {
  if (!ts) return '';
  const m = Math.max(1, Math.round((Date.now() / 1000 - ts) / 60));
  return m < 60 ? m + 'm ago' : m < 1440 ? Math.round(m / 60) + 'h ago' : Math.round(m / 1440) + 'd ago';
}
function newsBlock(items, home) {
  if (!items || !items.length) return '';
  const row = n => `<a class="item" href="${esc(n.link)}" target="_blank" rel="noopener noreferrer" style="color:inherit;text-decoration:none">
      <span class="news-thumb">${n.image ? `<img src="${esc(n.image)}" alt="" loading="lazy" referrerpolicy="no-referrer" onerror="this.remove()">` : ''}🏏</span>
      <div class="grow" style="min-width:0"><div class="t" style="white-space:normal;line-height:1.25">${esc(n.title)}</div><div class="s">${esc(n.source)}${n.ts ? ' · ' + newsAgo(n.ts) : ''}</div></div><span class="chev">${I.chev}</span></a>`;
  return (home ? `<div class="section"><h2>📰 Cricket news</h2><a href="#/news">More</a></div>` : '')
    + `<div class="card flush list">${items.map(row).join('')}</div>`
    + `<div class="small muted" style="margin:-4px 2px 8px">Headlines from ESPNcricinfo &amp; Google News · tap to read on the publisher's site</div>`;
}
App.loadNews = async limit => {
  try {
    const d = await api('cricket_news', { limit: 20 }); S.newsCache = d;
    const box = document.getElementById('home-news'); if (box) box.innerHTML = newsBlock(d.items.slice(0, limit), true);
  } catch (e) { /* news is a nice-to-have - stay quiet */ }
};
async function viewNews() {
  mount(topbar('Cricket news', { back: true }) + `<div class="content">${S.newsCache ? newsBlock(S.newsCache.items, false) : sk(5)}</div>`, 'home');
  try {
    const d = await api('cricket_news', { limit: 25 }); S.newsCache = d;
    mount(topbar('Cricket news', { back: true, sub: d.updated ? 'Updated ' + newsAgo(d.updated) : '' }) + `<div class="content">${newsBlock(d.items, false) || '<div class="card empty"><div class="big">📰</div>No news right now - try again in a few minutes.</div>'}</div>`, 'home');
  } catch (e) { toast(e.message || 'Could not load the news', true); }
}
/* Announcements from the admin, shown on Home until closed */
function announcementsHtml() {
  const list = (S.extras && S.extras.announcements) || [];
  let gone = [];
  try { gone = JSON.parse(localStorage.getItem('cs_news_closed') || '[]'); } catch (e) { /* private mode */ }
  return list.filter(a => !gone.includes(a.id)).map(a => `<div class="news news-${esc(a.kind)}"><div class="grow"><b>${esc(a.title)}</b>${a.body ? `<div>${esc(a.body)}</div>` : ''}</div>
    <button class="icon-btn" aria-label="Close" onclick="App.closeNews(${a.id}, this)">✕</button></div>`).join('');
}
App.closeNews = (id, btn) => {
  try { const g = JSON.parse(localStorage.getItem('cs_news_closed') || '[]'); g.push(id); localStorage.setItem('cs_news_closed', JSON.stringify(g.slice(-50))); } catch (e) { /* private mode */ }
  const el = btn.closest('.news'); if (el) el.remove();
};
App.install = async () => {
  if (!S.deferredInstall) return App.installHelp();
  S.deferredInstall.prompt();
  await S.deferredInstall.userChoice;
  S.deferredInstall = null; render();
};
App.installHelp = () => sheet(`<h3>Install as an app 📲</h3><div class="sub">Cric-Scorer works like a real app from your home screen.</div>
  ${S.deferredInstall ? `<button class="btn block" onclick="App.install()">Install now</button><div class="divider"></div>` : ''}
  <div class="card" style="box-shadow:none;border:1px solid var(--line)"><b>Android (Chrome)</b><div class="small muted">Tap the ⋮ menu → <b>Add to Home screen</b> (or <b>Install app</b>).</div></div>
  <div class="card" style="box-shadow:none;border:1px solid var(--line)"><b>iPhone / iPad (Safari)</b><div class="small muted">Tap the Share button → <b>Add to Home Screen</b>.</div></div>`);

/* ---------- Matches list ---------- */
async function viewMatches() {
  const tabs = [['live', 'Live'], ['scheduled', 'Upcoming'], ['completed', 'Completed']];
  mount(topbar('Matches') + `<div class="content"><div class="seg">${tabs.map(([k, l]) => `<button class="${S.mtab === k ? 'on' : ''}" onclick="S.mtab='${k}';render()">${l}</button>`).join('')}</div>
    <div class="chips" style="margin-bottom:12px">${[['all', '🌍 Everyone'], ['mine', '🧢 Mine'], ['following', '👥 Following']].map(([k, l]) => `<button class="chip ${(S.mscope || 'all') === k ? 'on' : ''}" onclick="S.mscope='${k}';render()">${l}</button>`).join('')}</div>
    <div id="ml">${sk(3)}</div></div><button class="fab" aria-label="New match" onclick="go('#/match/new')">${I.plus}</button>`, 'matches');
  const d = await api('matches_list', { status: S.mtab, scope: S.mscope || 'all' });
  $('#ml').innerHTML = d.matches.length ? d.matches.map(matchCard).join('')
    : `<div class="card empty"><div class="big">${S.mtab === 'live' ? '📡' : S.mtab === 'scheduled' ? '📅' : '🏁'}</div>No ${S.mtab === 'scheduled' ? 'upcoming' : S.mtab} matches${S.mscope === 'following' ? ' from players you follow' : ''}.
      ${S.mscope === 'following' ? `<br><button class="btn sm" style="margin-top:10px" onclick="go('#/people')">👥 Find players to follow</button>` : ''}</div>`;
}

/* ---------- New match ---------- */
function teamOptions(teams, selected, only = null) {
  let list = teams;
  if (only) list = teams.filter(t => only.includes(t.id));
  const groups = [['My teams', t => t.category === 'custom' && t.mine], ['Other custom teams', t => t.category === 'custom' && !t.mine], ['IPL', t => t.category === 'ipl'], ['International', t => t.category === 'international']];
  let html = '<option value="">- Choose team -</option>';
  for (const [label, f] of groups) {
    const g = list.filter(f);
    if (g.length) html += `<optgroup label="${label}">${g.map(t => `<option value="${t.id}" ${+selected === t.id ? 'selected' : ''} ${t.can_use === false ? 'disabled' : ''}>${t.can_use === false ? '🔒 ' : ''}${esc(t.name)}${t.can_use === false ? (t.my_access === 'pending' ? ' (asked)' : ' (ask owner)') : ''}</option>`).join('')}</optgroup>`;
  }
  return html;
}
async function viewMatchNew(tid) {
  mount(topbar('New Match', { back: true }) + `<div class="content">${sk(4)}</div>`, 'matches');
  const [ty, tm, to] = await Promise.all([api('types_list'), api('teams_list'), api('tournaments_list')]);
  let tour = null;
  if (tid) tour = await api('tournament_get', { id: +tid });
  S.nm = { types: ty.types, teams: tm.teams, only: tour ? tour.teams.map(t => +t.id) : null };
  const typeId = tour?.tournament.match_type_id || (ty.types.find(t => t.name === 'T20') || ty.types[0]).id;
  const t = ty.types.find(x => x.id === +typeId) || ty.types[0];
  const myTours = to.tournaments.filter(x => x.mine);
  mount(topbar(tour ? 'Add Match' : 'New Match', { back: true, sub: tour ? tour.tournament.name : '' }) + `<form class="content" onsubmit="App.createMatch(event)">
    <div class="card"><div class="vs" id="vs"></div>
      <div class="field"><label>Team A</label><select class="input" name="team1_id" onchange="App.vsPreview()" required>${teamOptions(tm.teams, null, S.nm.only)}</select></div>
      <div class="field" style="margin-bottom:4px"><label>Team B</label><select class="input" name="team2_id" onchange="App.vsPreview()" required>${teamOptions(tm.teams, null, S.nm.only)}</select></div>
      ${tour ? '' : `<div class="small muted" style="margin-top:6px">Want your own team? <a class="link" href="#/team/new">Create a team</a></div>`}
      ${tm.teams.some(x => x.can_use === false) ? `<div class="small muted" style="margin-top:6px">🔒 Locked teams belong to other players. Open the team and tap <b>Ask to use this team</b> - you can pick it once the owner says yes. <a class="link" href="#/teams?c=custom">See teams</a></div>` : ''}</div>
    <div class="section"><h2>Format</h2></div>
    <input type="hidden" name="format" value="limited">
    <div class="toss-pick vis-pick" style="margin-bottom:4px">
      <button type="button" class="on" data-f="limited" onclick="App.pickFormat(this)"><span style="font-size:26px">⚡</span>Limited overs<small>T20, ODI, your own</small></button>
      <button type="button" data-f="test" onclick="App.pickFormat(this)"><span style="font-size:26px">🏛️</span>Test match<small>2 innings each, follow-on, declarations</small></button></div>
    <div id="testopts" class="card" style="display:none;margin-top:10px"><div class="grid2"><div class="field"><label>Days</label><input class="input" type="number" name="days" min="1" max="7" value="5"></div>
      <div class="field"><label>Follow-on lead</label><input class="input" type="number" name="follow_on" min="0" max="500" value="200"></div></div>
      <div class="small muted">No over limit. Unlimited overs per bowler. Declare an innings from the match menu; end a drawn match from there too.</div></div>
    <div id="limopts">
    <div class="section"><h2>Match type</h2></div>
    <div class="chips" id="typechips">${ty.types.map(x => `<button type="button" class="chip ${x.id === t.id ? 'on' : ''}" data-id="${x.id}" onclick="App.pickType(${x.id})">${esc(x.name)} <small>${x.overs}ov</small></button>`).join('')}</div>
    <div class="card" style="margin-top:10px">
      <div class="grid3"><div class="field"><label>Overs</label><input class="input" type="number" name="overs" min="1" max="100" value="${t.overs}"></div>
      <div class="field"><label>Players</label><input class="input" type="number" name="players_per_side" min="1" max="15" value="${t.players_per_side}"></div>
      <div class="field"><label>Max ov/bowler</label><input class="input" type="number" name="max_overs_bowler" min="0" max="50" value="${t.max_overs_bowler}"></div></div>
      <div class="grid2"><div class="field"><label>…but up to (bowlers)</label><input class="input" type="number" name="extra_bowlers" min="0" max="11" placeholder="0" value="${t.extra_bowlers || ''}"></div><div class="field"><label>…may bowl (overs)</label><input class="input" type="number" name="extra_overs" min="0" max="50" placeholder="-" value="${t.extra_overs || ''}"></div></div>
      <input type="hidden" name="balls_per_over" value="${t.balls_per_over}"><input type="hidden" name="match_type_id" value="${t.id}">
      <label class="switch"><span><b>Last man stands</b><div class="small muted">Last batter can bat alone</div></span><input type="checkbox" name="last_man_stands" ${t.last_man_stands ? 'checked' : ''}><span class="knob"></span></label>
      <div class="field" style="margin-top:10px"><label>Bouncers allowed per over</label><select class="input" name="bouncers_per_over">${[0, 1, 2, 3].map(n => `<option value="${n}" ${(t.bouncers_per_over ?? 1) == n ? 'selected' : ''}>${n === 0 ? 'No limit' : n === 1 ? '1 - the 2nd bouncer is a no-ball' : n + ' - the next one is a no-ball'}</option>`).join('')}</select></div>
      <div class="small muted" style="margin-top:8px">Max overs per bowler: 0 = no limit. Mark a bouncer with the ⬆️ Bouncer button while scoring.</div></div></div>
    <div class="section"><h2>Who can see this match?</h2></div>
    <input type="hidden" name="visibility" value="public">
    <div class="toss-pick vis-pick" style="margin-bottom:4px">
      <button type="button" class="on" data-v="public" onclick="App.pickVis(this)"><span style="font-size:26px">🌍</span>Public<small>Everyone can watch</small></button>
      <button type="button" data-v="private" onclick="App.pickVis(this)"><span style="font-size:26px">🔒</span>Private<small>Only you</small></button></div>
    <div class="section"><h2>Details</h2></div>
    <div class="card">
      <div class="field"><label>Match title</label><input class="input" name="title" placeholder="e.g. Sunday Showdown" value="${tour ? 'Match ' + (tour.matches.length + 1) : ''}"></div>
      <div class="field"><label>Venue</label><input class="input" name="venue" placeholder="e.g. Society ground" value="${esc(tour?.tournament.venue || '')}"></div>
      <div class="field"><label>Date & time</label><input class="input" type="datetime-local" name="match_date" value="${localDT()}"></div>
      ${tour ? `<input type="hidden" name="tournament_id" value="${tour.tournament.id}">` : myTours.length ? `<div class="field"><label>Tournament (optional)</label><select class="input" name="tournament_id"><option value="">- None (friendly) -</option>${myTours.map(x => `<option value="${x.id}">${esc(x.name)}</option>`).join('')}</select></div>` : ''}
    </div>
    <button class="btn block" style="margin:6px 0 20px">Create match →</button></form>`, 'matches');
  App.vsPreview();
}
App.pickType = id => {
  const t = S.nm.types.find(x => x.id === id); const f = $('form.content');
  f.overs.value = t.overs; f.players_per_side.value = t.players_per_side; f.max_overs_bowler.value = t.max_overs_bowler; f.extra_bowlers.value = +t.extra_bowlers || ''; f.extra_overs.value = +t.extra_overs || '';
  f.balls_per_over.value = t.balls_per_over; f.match_type_id.value = t.id; f.last_man_stands.checked = !!t.last_man_stands; if (f.bouncers_per_over) f.bouncers_per_over.value = t.bouncers_per_over ?? 1;
  document.querySelectorAll('#typechips .chip').forEach(c => c.classList.toggle('on', +c.dataset.id === id));
};
App.pickFormat = b => {
  const f = b.dataset.f, form = $('form.content'); form.format.value = f;
  b.parentElement.querySelectorAll('button').forEach(x => x.classList.toggle('on', x === b));
  $('#testopts').style.display = f === 'test' ? '' : 'none'; $('#limopts').style.display = f === 'test' ? 'none' : '';
  const g = $('#testopts'); g.querySelectorAll('input').forEach(i => i.disabled = f !== 'test');
};
App.vsPreview = () => {
  const f = $('form.content'); const find = id => S.nm.teams.find(t => t.id === +id);
  const a = find(f.team1_id.value), b = find(f.team2_id.value);
  const side = t => `<div class="side">${t ? badge(t, 'lg') : '<span class="badge lg" style="background:var(--soft);color:var(--muted)">?</span>'}<span>${esc(t?.name || 'Pick a team')}</span></div>`;
  $('#vs').innerHTML = side(a) + '<div class="v">VS</div>' + side(b);
};
App.pickVis = btn => {
  const f = btn.closest('form');
  f.visibility.value = btn.dataset.v;
  f.querySelectorAll('.vis-pick button').forEach(b => b.classList.toggle('on', b === btn));
};
App.createMatch = e => {
  e.preventDefault();
  const f = formData(e.target);
  if (f.team1_id === f.team2_id) return toast('Pick two different teams', true);
  run(async () => { const r = await api('match_create', f); S.m = null; go('#/match/' + r.id); }, e.submitter);
};

/* =====================================================================
   MATCH SCREEN - setup, live scoring, scorecard
   ===================================================================== */
async function viewMatch(id) {
  id = +id;
  if (!S.m || S.m.match.id !== id) {
    S.mTab = 'card'; S.cardInn = null; S.squads = null; S.toss = {}; S.op = {}; S.bnc = false;
    mount(topbar('Match', { back: true }) + `<div class="content">${sk(3)}</div>`, S.user ? 'matches' : null);
  }
  S.m = await api('match_get', { id });
  drawMatch(true);
  if (S.m.match.status === 'live' && !S.m.can_edit) {
    S.timers.push(setInterval(async () => {
      if (document.hidden || location.hash !== '#/match/' + id) return;
      try { const prev = S.m; S.m = await api('match_get', { id }); drawMatch(false); announce(prev, S.m); } catch (e) { /* keep last */ }
    }, 6000));
  }
}
const curInn = () => S.m.innings[S.m.innings.length - 1];
const teamById = id => (S.m.team1.id === id ? S.m.team1 : S.m.team2);
function pname(id) {
  for (const k in S.m.xi) { const p = S.m.xi[k].find(x => x.id === id); if (p) return p.name; }
  return '?';
}
function ballChip(label0) {
  const bnc = label0.endsWith('°'); const label = bnc ? label0.slice(0, -1) : label0;
  let c = '';
  if (label.includes('W')) c = 'w';
  else if (label === '4') c = 'b4';
  else if (label === '6') c = 'b6';
  else if (/wd|nb|lb|b|RH/.test(label)) c = 'ex';
  else if (label === '0') c = 'dot';
  return `<span class="ball ${c}${bnc ? ' bnc' : ''}" ${bnc ? 'title="Bouncer"' : ''}>${label === '0' ? '•' : esc(label)}${bnc ? '<i>↑</i>' : ''}</span>`;
}

function drawMatch(first) {
  const st = S.m, m = st.match, t1 = st.team1, t2 = st.team2;
  const editing = st.can_edit && st.needs !== 'completed';
  const actions = `<button class="icon-btn" id="spk" aria-label="Sound and commentary" onclick="App.soundSheet()">${spkIcon()}</button><button class="icon-btn" aria-label="Share" onclick="App.shareMatch()">${I.share}</button>` +
    (st.can_edit ? `<button class="icon-btn" aria-label="Match options" onclick="App.matchMenu()">${I.dots}</button>` :
      '');
  const sub = [m.visibility === 'private' ? '🔒 Private' : '', m.scorer && st.can_edit && !st.is_owner ? '✍️ You are the scorer' : m.scorer ? '✍️ Scorer: ' + m.scorer.display_name : '', m.title, m.tournament?.name].filter(Boolean).join(' · ');
  let html = topbar(`${t1.short_name} vs ${t2.short_name}`, { back: true, actions, sub });
  const inn = st.innings.length ? curInn() : null;
  html += inn ? scoreBoard(st, inn) : preBoard(st);

  if (st.needs === 'completed') html += resultCard(st);
  else if (st.can_edit) {
    if (st.needs === 'players') html += xiSetup();
    else if (st.needs === 'toss') html += tossSetup();
    else if (st.needs === 'openers') html += openersSetup();
    else html += `<div style="padding:0 0 0">${creasePanel(st, inn)}</div><div class="content" style="padding-top:0">${scorePad()}</div>`;
  } else {
    if (!inn) html += `<div class="content"><div class="card empty"><div class="big">⏳</div>The match hasn't started yet.<br>${m.toss_winner_id ? esc(teamById(m.toss_winner_id).name) + ' won the toss and chose to ' + m.toss_decision + '.' : 'Waiting for the toss.'}</div></div>`;
    else if (st.needs === 'openers') html += `<div class="content"><div class="card empty"><div class="big">☕</div>Innings break</div></div>`;
    else html += creasePanel(st, inn) + (m.status === 'live' ? `<div class="content" style="padding-top:0"><button class="btn light block" onclick="App.soundSheet()">🔊 Live commentary & crowd sounds</button></div>` : '');
  }
  if (st.innings.length) html += matchTabs(st);
  const stage = ['ball', 'new_batter', 'new_bowler'].includes(st.needs) ? 'live' : st.needs;
  const sameStage = S.lastStage === stage + ':' + m.id;
  S.lastStage = stage + ':' + m.id;
  mount(html, editing && stage === 'live' ? null : (S.user ? 'matches' : null), { keepScroll: !first && sameStage });
  if (st.can_edit) {
    if (st.needs === 'new_batter') batterSheet();
    else if (st.needs === 'new_bowler') App.bowlerSheet(false);
  }
}

function preBoard(st) {
  const m = st.match;
  const side = t => `<div class="side">${badge(t, 'lg')}<span>${esc(t.name)}</span></div>`;
  return `<div class="board"><div class="vs">${side(st.team1)}<div class="v" style="color:var(--lime)">VS</div>${side(st.team2)}</div>
    <div class="other" style="text-align:center">${m.format === 'test' ? `🏛️ Test match · ${plural(m.days, 'day')}` : m.overs + ' overs'} · ${m.players_per_side} a side${m.last_man_stands ? ' · Last man stands' : ''}${m.max_overs_bowler ? ` · ${m.max_overs_bowler} ov/bowler` : ''}</div>
    <div class="other" style="text-align:center">${esc(fmtDate(m.match_date))}${m.venue ? ' · ' + esc(m.venue) : ''}</div></div>`;
}

function scoreBoard(st, inn) {
  const m = st.match, bt = teamById(inn.batting_team_id);
  if (m.format === 'test') return testBoard(st, inn);
  const first = st.innings.length === 2 ? st.innings[0] : null;
  let meta = `<span><i>CRR</i> ${Number(inn.crr).toFixed(2)}</span>`;
  if (inn.target) meta += `<span><i>Target</i> ${inn.target}${inn.dls ? ' <b class="dls-tag">DLS</b>' : ''}</span>` + (inn.status === 'live' ? `<span><i>RRR</i> ${Number(inn.rrr).toFixed(2)}</span>` : '');
  else if (inn.status === 'live') meta += `<span><i>Projected</i> ${inn.projected}</span>`;
  if (inn.no === 2 && inn.status === 'live' && st.dls && st.dls.par !== undefined && st.dls.active) meta += `<span><i>DLS par</i> ${st.dls.par}</span>`;
  if (inn.extras) meta += `<span><i>Extras</i> ${inn.extras}</span>`;
  let need = '';
  if (m.result_text) need = `<div class="need">🏆 ${esc(m.result_text)}</div>`;
  else if (inn.target && inn.status === 'live') need = `<div class="need">${esc(bt.name)} need ${plural(inn.need, 'run')} from ${plural(inn.balls_left, 'ball')}</div>`;
  else if (inn.no === 1 && inn.status === 'completed') need = `<div class="need">Innings break · ${esc(teamById(inn.bowling_team_id).name)} need ${inn.runs + 1} to win</div>`;
  const toss = m.toss_winner_id ? `${teamById(m.toss_winner_id).name} won the toss & chose to ${m.toss_decision}` : '';
  return `<div class="board"><div class="bt">${badge(bt, 'sm')}<span class="grow ellipsis">${esc(bt.name)}</span><span class="pill">${inn.no === 1 ? '1st' : '2nd'} innings</span></div>
    <div class="score"><b>${inn.runs}/${inn.wickets}</b><span>(${inn.overs}/${inn.max_overs} ov)</span></div>
    <div class="meta">${meta}</div>${need}
    ${first ? `<div class="other">${esc(teamById(first.batting_team_id).name)}: ${first.runs}/${first.wickets} (${first.overs} ov)</div>` : `<div class="other">${esc(toss)}</div>`}</div>`;
}

function testBoard(st, inn) {
  const m = st.match, bt = teamById(inn.batting_team_id), ot = teamById(inn.bowling_team_id);
  const tot = m.totals || {}; const mine = tot[inn.batting_team_id] || 0, theirs = tot[inn.bowling_team_id] || 0;
  const ord = n => n === 1 ? '1st' : '2nd';
  let meta = `<span><i>RR</i> ${Number(inn.crr).toFixed(2)}</span>`;
  if (inn.target && inn.status === 'live') meta += `<span><i>Target</i> ${inn.target}</span>`;
  if (inn.extras) meta += `<span><i>Extras</i> ${inn.extras}</span>`;
  let need = '';
  if (m.result_text) need = `<div class="need">🏆 ${esc(m.result_text)}</div>`;
  else if (inn.target && inn.status === 'live') need = `<div class="need">${esc(bt.name)} need ${plural(inn.need, 'run')} to win</div>`;
  else if (inn.status === 'live' && st.innings.length > 1) need = `<div class="need">${esc(bt.name)} ${mine >= theirs ? 'lead by' : 'trail by'} ${plural(Math.abs(mine - theirs), 'run')}${inn.follow_on ? ' · following on' : ''}</div>`;
  else if (inn.status === 'completed' && !m.result_text) need = `<div class="need">Innings break${inn.declared ? ' · declared' : ''}</div>`;
  const others = st.innings.filter(i => i.id !== inn.id).map(i => `<div class="other">${esc(teamById(i.batting_team_id).name)} ${i.team_inns === 1 ? '1st' : '2nd'} inns: ${i.runs}/${i.wickets}${i.declared ? 'd' : ''} (${i.overs} ov)</div>`).join('');
  const toss = m.toss_winner_id ? `${teamById(m.toss_winner_id).name} won the toss & chose to ${m.toss_decision}` : '';
  return `<div class="board"><div class="bt">${badge(bt, 'sm')}<span class="grow ellipsis">${esc(bt.name)}</span><span class="pill">${ord(inn.team_inns || 1)} innings${inn.declared ? ' · dec' : ''}</span></div>
    <div class="score"><b>${inn.runs}/${inn.wickets}${inn.declared ? '<small style="font-size:.45em">d</small>' : ''}</b><span>(${inn.overs} ov)</span></div>
    <div class="meta">${meta}</div>${need}
    ${others || `<div class="other">${esc(toss)}</div>`}</div>`;
}

function thisOverBalls(st, inn) {
  const bpo = st.match.balls_per_over;
  let cur = Math.floor(inn.legal_balls / bpo);
  if (inn.legal_balls > 0 && inn.legal_balls % bpo === 0 && (!inn.bowler_id || inn.status === 'completed')) cur--;
  return { cur, balls: inn.timeline.filter(b => b.over_no === cur) };
}

function creasePanel(st, inn) {
  const batRow = id => {
    const b = inn.batting.find(x => x.id === id) || { name: pname(id), r: 0, b: 0, f4: 0, f6: 0, sr: 0 };
    return `<tr onclick="go('#/player/${id}')"><td>${id === inn.striker_id ? '<span class="on-strike">●</span> ' : ''}${esc(b.name)}</td><td><b>${b.r}</b></td><td>${b.b}</td><td>${b.f4}</td><td>${b.f6}</td><td>${b.sr}</td></tr>`;
  };
  const bats = [inn.striker_id, inn.non_striker_id].filter(Boolean);
  const bw = inn.bowling.find(x => x.id === inn.bowler_id);
  const { cur, balls } = thisOverBalls(st, inn);
  const p = inn.partnership;
  return `<div class="crease">
    <table><tr><th>Batter</th><th>R</th><th>B</th><th>4s</th><th>6s</th><th>SR</th></tr>
      ${bats.length ? bats.map(batRow).join('') : '<tr><td colspan="6" class="muted">Waiting for batters…</td></tr>'}
      <tr><th>Bowler</th><th>O</th><th>M</th><th>R</th><th>W</th><th>Econ</th></tr>
      ${inn.bowler_id ? `<tr class="bowl"><td>${esc(bw ? bw.name : pname(inn.bowler_id))}</td><td>${bw ? bw.o : '0.0'}</td><td>${bw ? bw.m : 0}</td><td>${bw ? bw.r : 0}</td><td><b>${bw ? bw.w : 0}</b></td><td>${bw ? bw.econ : '0.00'}</td></tr>` : '<tr class="bowl"><td colspan="6" class="muted">New over - choose the bowler</td></tr>'}
    </table>
    ${bw && bw.streak >= 2 && inn.status === 'live' ? `<div class="hat-banner">🎩 <b>${esc(bw.name)}</b> is on a HAT-TRICK!${bw.streak > 2 ? ` (${bw.streak} in ${bw.streak} - going for ${bw.streak + 1})` : ''}</div>` : ''}
    <div class="over-strip"><span class="lbl">Over ${cur + 1}</span>${lastBallBadge(st, inn)}${balls.length ? balls.map(b => ballChip(b.label)).join('') : '<span class="muted small">No balls yet</span>'}</div>
    <div class="partner"><span>Partnership: <b>${p.runs}</b> (${p.balls})</span><span>${inn.status === 'live' ? (st.match.format === 'test' ? `${inn.overs} overs bowled` : `${inn.max_overs * st.match.balls_per_over - inn.legal_balls} balls left`) : 'Innings over'}</span></div></div>`;
}

function lastBallBadge(st, inn) {
  const bpo = st.match.balls_per_over, total = inn.max_overs * bpo;
  if (inn.status !== 'live' || !inn.bowler_id || inn.legal_balls % bpo !== bpo - 1) return '';
  if (st.match.format === 'test') return `<span class="pill live" style="flex:none;margin-right:4px">Last ball</span>`;
  return `<span class="pill live" style="flex:none;margin-right:4px">${inn.legal_balls === total - 1 ? 'Last ball of innings' : 'Last ball'}</span>`;
}
function scorePad() {
  return `<div class="pad">
    ${[0, 1, 2, 3].map(r => `<button onclick="App.ball(${r})">${r}</button>`).join('')}
    <button class="four" onclick="App.ball(4)">4</button><button class="six" onclick="App.ball(6)">6</button>
    <button class="ext" onclick="App.extraSheet('wd')">WD</button><button class="ext" onclick="App.extraSheet('nb')">NB</button>
    <button class="ext" onclick="App.extraSheet('b')">BYE</button><button class="ext" onclick="App.extraSheet('lb')">LB</button>
    <button class="undo" onclick="App.undo()">↶ Undo</button><button class="out" onclick="App.wicketSheet(null)">OUT</button></div>
    <div class="pad-tools"><button class="bnc-btn ${S.bnc ? 'on' : ''}" onclick="App.toggleBouncer()">${S.bnc ? '⬆️ Bouncer ON - tap runs' : '⬆️ Bouncer'}</button>${bouncerInfo()}</div>
    <div class="pad-tools"><button onclick="App.swap()">⇄ Swap ends</button><button onclick="App.bowlerSheet(true)">🔁 Bowler</button>
    <button onclick="App.moreRuns()">5 / 7 runs</button><button onclick="App.retireSheet()">🩹 Retire</button></div>
    <div class="pad-tools"><button onclick="App.readScore()">🎙️ Read score</button><button onclick="App.soundSheet()">${Sound.sfx || Sound.voice ? '🔊' : '🔇'} Sound & voice</button></div>`;
}

/* ----- scoring actions ----- */
async function scoreAction(action, data = {}) {
  if (S.busy) return;
  S.busy = true;
  const before = S.m;
  try {
    const bncBefore = action === 'match_ball' && S.bnc ? bouncerStats() : null;
    if (bncBefore) data = { ...data, bouncer: 1 };
    S.m = await api(action, { id: S.m.match.id, ...data });
    if (action === 'match_ball') S.bnc = false;
    closeSheet();
    if (bncBefore && bncBefore.limit && bncBefore.n >= bncBefore.limit) { toast(`Bouncer ${bncBefore.n + 1} of the over - NO BALL! +1 run, ball to be re-bowled`, true); Sound.fx('wide'); }
    celebrate(before, S.m, action);
    drawMatch(false);
    if (navigator.vibrate) navigator.vibrate(12);
    return true;
  } catch (e) { toast(e.message, true); return false; }
  finally { S.busy = false; }
}
App.ball = (runs, extra = null, wicket = null) => scoreAction('match_ball', { runs, extra, wicket });
/* Bouncer: arm the toggle, then score the ball as usual; past the per-over limit the server records a no-ball */
App.toggleBouncer = () => { S.bnc = !S.bnc; drawMatch(false); if (S.bnc) toast('Bouncer armed - now tap the runs, extra or OUT for this ball'); };
function bouncerStats() {
  const st = S.m, inn = st.innings.length ? curInn() : null; if (!inn) return null;
  const bpo = st.match.balls_per_over; const cur = Math.floor(inn.legal_balls / bpo);
  const n = (inn.timeline || []).filter(b => b.over_no === cur && b.bnc).length;
  return { n, limit: st.match.bouncers_per_over || 0 };
}
function bouncerInfo() {
  const b = bouncerStats(); if (!b) return '';
  const over = b.limit && b.n >= b.limit;
  return `<span class="bnc-info ${over ? 'over' : ''}">↑ ${b.n}${b.limit ? '/' + b.limit : ''} this over${over ? ' · next = no-ball' : ''}</span>`;
}
App.swap = () => scoreAction('match_swap');
App.undo = () => { Sound.stopVoice(); return scoreAction('match_undo').then(() => toast('Last action undone')); };
App.setBatter = id => scoreAction('match_batter', { player_id: id });
App.setBowler = id => scoreAction('match_bowler', { player_id: id });

function celebrate(before, after, action) {
  if (action === 'match_ball' || action === 'match_end_innings') announce(before, after);
}

/* Pop-ups, stadium sounds and spoken commentary for each new ball */
function announce(before, after) {
  const inns = after.innings;
  if (!inns.length) return;
  const inn = inns[inns.length - 1];
  const bi = before ? before.innings.find(i => i.no === inn.no) : null;
  const newBall = inn.timeline.length > (bi ? bi.timeline.length : 0);
  const finished = after.match.status === 'completed' && before && before.match.status !== 'completed';
  const inningsOver = inn.status === 'completed' && bi && bi.status === 'live';
  if (!newBall && !finished && !inningsOver) return;
  const team = (after.team1.id === inn.batting_team_id ? after.team1 : after.team2);
  const b = newBall ? inn.timeline[inn.timeline.length - 1] : null;
  const say = []; let fx = null;
  if (b) {
    const outBat = b.out_id ? inn.batting.find(x => x.id === b.out_id) : null;
    const d = { bat: b.bat, bowl: b.bowl, fld: b.fld || 'the fielder', out: b.out, r: outBat ? outBat.r : 0 };
    if (b.w) {
      fx = ['bowled', 'hit wicket'].includes(b.wt) ? 'bowled' : 'out';
      const key = b.wt === 'caught' && b.fld && b.fld === b.bowl ? 'caught&' : b.wt;
      popup('OUT!', `${b.out} ${outBat ? outBat.r + ' (' + outBat.b + ')' : ''}`, 'w', '☝️');
      say.push(Sound.line(key, d));
      say.push(`${team.name} are ${inn.runs} for ${inn.wickets}.`);
    } else if (b.wt === 'retired hurt') {
      say.push(`${b.out} retires hurt.`);
    } else if (b.ex === 'wd') {
      fx = 'wide'; say.push(Sound.line('wide'));
    } else if (b.ex === 'nb') {
      fx = b.rb === 6 ? 'six' : b.rb === 4 ? 'four' : 'wide';
      say.push(Sound.line('noball'));
      if (b.rb === 6) { popup('SIX!', b.bat + ' · off a no-ball', 'b6', '🚀'); say.push(Sound.line('six', d)); }
      else if (b.rb === 4) { popup('FOUR!', b.bat + ' · off a no-ball', 'b4', '🏏'); say.push(Sound.line('four', d)); }
    } else if (b.ex === 'b' || b.ex === 'lb') {
      fx = 'run'; say.push(`${b.xr} ${b.ex === 'b' ? 'bye' : 'leg bye'}${b.xr > 1 ? 's' : ''}.`);
    } else if (b.rb === 6) {
      fx = 'six'; popup('SIX!', b.bat, 'b6', '🚀'); say.push(Sound.line('six', d));
    } else if (b.rb === 4) {
      fx = 'four'; popup('FOUR!', b.bat, 'b4', '🏏'); say.push(Sound.line('four', d));
    } else if (b.rb === 0) {
      fx = 'dot'; if (Math.random() < 0.6) say.push(Sound.line('dot', d));
    } else {
      fx = 'run'; say.push(Sound.line('run' + b.rb, d) || `${b.rb} runs.`);
    }
    // Hat-trick!
    if (b.ht) {
      setTimeout(() => { popup(b.ht === 3 ? 'HAT-TRICK!' : `${b.ht} IN ${b.ht}!`, b.bowl, 'ms', '🎩'); Sound.fx('win'); confetti(); }, 1700);
      say.push(b.ht === 3 ? `Hat-trick! ${b.bowl} takes three wickets in three balls!` : `${b.ht} wickets in ${b.ht} balls for ${b.bowl}! Unbelievable!`);
    } else if (b.w) {
      const sb = inn.bowling.find(x => x.name === b.bowl);
      if (sb && sb.streak === 2 && inn.status === 'live') { say.push(`${b.bowl} is on a hat-trick!`); setTimeout(() => toast(`🎩 ${b.bowl} is on a hat-trick!`), 1800); }
    }
    // Batting milestones
    const nb = inn.batting.find(x => x.name === b.bat), ob = bi && bi.batting.find(x => x.name === b.bat);
    if (nb && ob) {
      const ms = [100, 50].find(v => ob.r < v && nb.r >= v);
      if (ms) {
        setTimeout(() => { popup(ms === 100 ? 'CENTURY!' : 'FIFTY!', `${b.bat} · ${nb.r} (${nb.b})`, 'ms', ms === 100 ? '💯' : '⭐'); Sound.fx('milestone'); confetti(); }, 1700);
        say.push(Sound.line(ms === 100 ? 'hundred' : 'fifty', d));
      }
    }
    // Close finish tension
    if (inn.target && inn.status === 'live' && inn.need > 0 && inn.need <= 12 && inn.balls_left > 1) say.push(`${inn.need} needed from ${inn.balls_left} ${inn.balls_left === 1 ? 'ball' : 'balls'}!`);
    // Last ball of the over / innings coming up
    const bpo = after.match.balls_per_over, total = inn.max_overs * bpo;
    if (inn.status === 'live' && after.needs === 'ball' && inn.legal_balls % bpo === bpo - 1) {
      const lastOfInnings = inn.legal_balls === total - 1;
      if (inn.target && lastOfInnings) say.push(inn.need === 1 ? 'One run needed off the last ball!' : `${inn.need} needed off the very last ball!`);
      else if (lastOfInnings) say.push('Here comes the last ball of the innings!');
      else say.push(b.ex === 'wd' || b.ex === 'nb' ? 'Still the last ball of the over to come.' : 'Last ball of the over.');
    }
    // End of over summary
    if (after.needs === 'new_bowler' && before && before.needs === 'ball' && inn.status === 'live') {
      const o = inn.overs_list[inn.overs_list.length - 1];
      if (o) toast(`End of over ${o.over}: ${plural(o.runs, 'run')}${o.wkts ? ', ' + plural(o.wkts, 'wicket') : ''}`);
      say.push(`End of the over. ${team.name} ${inn.runs} for ${inn.wickets}.` + (inn.target ? ` They need ${inn.need} from ${inn.balls_left} balls.` : ''));
    }
  }
  if (inningsOver && inn.no === 1) {
    toast('Innings over! ' + inn.runs + '/' + inn.wickets);
    fx = fx === 'six' || fx === 'four' || fx === 'bowled' || fx === 'out' ? fx : 'cheer';
    setTimeout(() => Sound.fx('cheer'), 1200);
    say.push(`That's the end of the innings. ${team.name} finish on ${inn.runs} for ${inn.wickets} in ${String(inn.overs).replace(/\.0$/, '')} ${String(inn.overs).replace(/\.0$/, '') === '1' ? 'over' : 'overs'}. The target is ${inn.runs + 1}.`);
  }
  if (finished) {
    confetti();
    setTimeout(() => { popup('WINNER!', after.match.result_text || '', 'ms', '🏆'); Sound.fx('win'); }, b && (b.rb >= 4 || b.w) ? 1500 : 200);
    say.push(`${after.match.result_text}! ` + (after.match.player_of_match ? `Player of the match is ${after.match.player_of_match.name}. ` : '') + 'What a game!');
  }
  Sound.fx(fx);
  Sound.say(say.filter(Boolean).join(' '));
}
function popup(text, sub, cls, emoji) {
  document.querySelectorAll('.burst').forEach(x => x.remove());
  const d = document.createElement('div');
  d.className = 'burst ' + cls;
  d.innerHTML = `<div class="pop"><div class="emo">${emoji || ''}</div><span>${esc(text)}</span>${sub ? `<div class="psub">${esc(sub)}</div>` : ''}</div>`;
  document.body.appendChild(d); setTimeout(() => d.remove(), 1700);
}
function scoreReadout(st) {
  if (!st.innings.length) return `${st.team1.name} versus ${st.team2.name}. The match has not started yet.`;
  const inn = st.innings[st.innings.length - 1], m = st.match;
  const team = teamById(inn.batting_team_id);
  if (m.result_text) return `${m.result_text}.`;
  const ov = String(inn.overs).replace(/\.0$/, '');
  const parts = [`${team.name} are ${inn.runs} for ${inn.wickets} after ${ov} ${ov === '1' ? 'over' : 'overs'}.`];
  for (const id of [inn.striker_id, inn.non_striker_id].filter(Boolean)) {
    const b = inn.batting.find(x => x.id === id);
    if (b) parts.push(`${b.name} on ${b.r} off ${b.b} ${b.b === 1 ? 'ball' : 'balls'}.`);
  }
  const bw = inn.bowling.find(x => x.id === inn.bowler_id);
  if (bw) parts.push(`${bw.name} has ${bw.w} for ${bw.r}.`);
  if (inn.target && inn.status === 'live') parts.push(`They need ${inn.need} runs from ${inn.balls_left} balls.`);
  else if (inn.status === 'live' && inn.legal_balls) parts.push(`Run rate ${Number(inn.crr).toFixed(1)}.`);
  return parts.join(' ');
}
const SPK = sv('<path d="M4 9v6h4l5 4V5L8 9H4z"/><path d="M16 9a4 4 0 010 6M18.5 6.5a8 8 0 010 11"/>');
const SPK_OFF = sv('<path d="M4 9v6h4l5 4V5L8 9H4z"/><path d="M17 9l5 5M22 9l-5 5"/>');
const spkIcon = () => (Sound.sfx || Sound.voice ? SPK : SPK_OFF);
App.soundSheet = () => {
  Sound.unlock();
  const sw = (label, sub, on, fn) => `<label class="switch" style="margin-bottom:10px"><span><b>${label}</b><div class="small muted">${sub}</div></span><input type="checkbox" ${on ? 'checked' : ''} onchange="${fn}(this.checked)"><span class="knob"></span></label>`;
  sheet(`<h3>Sound & commentary 🔊</h3><div class="sub">Stadium sounds and a live commentator for every ball.</div>
    ${sw('Sound effects', 'Bat sound, crowd cheering, clapping, stumps', Sound.sfx, 'App.setSfx')}
    ${Sound.hasSpeech ? sw('Commentary voice', 'Reads out every ball and the score', Sound.voice, 'App.setVoice') : '<div class="notice">This browser has no text-to-speech voice.</div>'}
    ${S.m && location.hash.startsWith('#/match/') ? `<button class="btn block" onclick="App.readScore()">🎙️ Read the score now</button>` : ''}
    <div class="grid3" style="margin-top:10px"><button class="btn sm light" onclick="App.testFx('four')">Test 4</button><button class="btn sm light" onclick="App.testFx('six')">Test 6</button><button class="btn sm light" onclick="App.testFx('bowled')">Test OUT</button></div>
    <div class="small muted" style="margin-top:10px">Tip: turn up the phone volume and switch off silent mode. On iPhone, the ring/silent switch must be off for sound.</div>`);
};
App.setSfx = v => { Sound.setSfx(v); refreshSpk(); };
App.setVoice = v => { Sound.setVoice(v); refreshSpk(); if (v) Sound.say('Commentary is on. Let\'s play!', { delay: 50 }); };
App.readScore = () => { Sound.unlock(); if (!Sound.voice) { Sound.setVoice(true); refreshSpk(); } Sound.say(scoreReadout(S.m), { delay: 50 }); };
App.testFx = k => {
  Sound.unlock();
  const t = { four: ['FOUR!', 'b4', '🏏', 'four'], six: ['SIX!', 'b6', '🚀', 'six'], bowled: ['OUT!', 'w', '☝️', 'bowled'] }[k];
  closeSheet(); popup(t[0], 'Test', t[1], t[2]); Sound.fx(k); Sound.say(Sound.line(t[3], { bat: 'Aarush', bowl: 'Aayush', out: 'Aarush', fld: 'Aayush', r: 50 }));
};
function refreshSpk() { const b = document.getElementById('spk'); if (b) b.innerHTML = spkIcon(); }
function confetti() {
  const em = ['🎉', '🏆', '⭐', '🏏', '🎊', '✨'];
  for (let i = 0; i < 36; i++) {
    const s = document.createElement('span');
    s.className = 'confetti'; s.textContent = em[i % em.length];
    s.style.left = Math.random() * 100 + 'vw';
    s.style.animationDuration = (2 + Math.random() * 2.5) + 's';
    s.style.animationDelay = (Math.random() * .8) + 's';
    document.body.appendChild(s); setTimeout(() => s.remove(), 5500);
  }
}

App.extraSheet = type => {
  const cfg = {
    wd: ['Wide ball', 'Extra runs the batters ran (the wide itself adds 1)', [0, 1, 2, 3, 4], 'Wide + stumped / run out'],
    nb: ['No ball', 'Runs scored off the bat (the no-ball adds 1)', [0, 1, 2, 3, 4, 6], 'No ball + run out'],
    b: ['Byes', 'Runs taken', [1, 2, 3, 4], 'Byes + run out'],
    lb: ['Leg byes', 'Runs taken', [1, 2, 3, 4], 'Leg byes + run out'],
  }[type];
  sheet(`<h3>${cfg[0]}</h3><div class="sub">${cfg[1]}</div>
    <div class="opts">${cfg[2].map(r => `<button onclick="App.ball(${r},'${type}')">${(type === 'wd' || type === 'nb') ? '+' + r : r}</button>`).join('')}</div>
    <button class="btn light block" onclick="App.wicketSheet('${type}')">${cfg[3]}</button>`);
};
App.moreRuns = () => sheet(`<h3>More runs</h3><div class="sub">Overthrows or all-run boundaries</div><div class="opts">${[5, 7].map(r => `<button onclick="App.ball(${r})">${r}</button>`).join('')}</div>`);

App.wicketSheet = extra => { const inn = curInn(); S.w = { extra, type: null, fielder: null, dismissed: inn.striker_id, runs: 0 }; drawWicket(); };
App.wSet = (k, v) => { S.w[k] = v; drawWicket(); };
function drawWicket() {
  const st = S.m, inn = curInn(), w = S.w;
  let types = [['bowled', 'Bowled'], ['caught', 'Caught'], ['lbw', 'LBW'], ['run out', 'Run out'], ['stumped', 'Stumped'], ['hit wicket', 'Hit wicket']];
  if (w.extra === 'wd') types = types.filter(t => ['stumped', 'run out', 'hit wicket'].includes(t[0]));
  if (['nb', 'b', 'lb'].includes(w.extra)) types = types.filter(t => t[0] === 'run out');
  if (!w.type && types.length === 1) w.type = types[0][0];
  const chip = (label, fn, on) => `<button class="chip ${on ? 'on' : ''}" onclick="${fn}">${esc(label)}</button>`;
  const exName = { wd: 'wide', nb: 'no ball', b: 'bye', lb: 'leg bye' }[w.extra];
  let h = `<h3>Wicket! ☝️</h3><div class="sub">${exName ? 'Out on a ' + exName : 'How did the batter get out?'}</div>
    <div class="chips wrap">${types.map(([k, l]) => chip(l, `App.wSet('type','${k}')`, w.type === k)).join('')}</div>`;
  if (w.type === 'run out') {
    h += `<div class="field" style="margin-top:14px"><label>Who is run out?</label><div class="chips wrap">${[inn.striker_id, inn.non_striker_id].filter(Boolean).map(id => chip(pname(id) + (id === inn.striker_id ? ' (striker)' : ''), `App.wSet('dismissed',${id})`, w.dismissed === id)).join('')}</div></div>
      <div class="field"><label>${w.extra === 'nb' ? 'Runs off the bat' : 'Runs completed'} before the run out</label><div class="chips wrap">${[0, 1, 2, 3].map(r => chip(String(r), `App.wSet('runs',${r})`, w.runs === r)).join('')}</div></div>`;
  }
  if (['caught', 'run out', 'stumped'].includes(w.type)) {
    const label = w.type === 'caught' ? 'Caught by' : w.type === 'stumped' ? 'Stumped by (keeper)' : 'Run out by (optional)';
    h += `<div class="field" style="margin-top:14px"><label>${label}</label><div class="chips wrap">${st.xi[inn.bowling_team_id].map(p => chip((p.role === 'Wicket-keeper' ? '🧤 ' : '') + p.name + (p.id === inn.bowler_id ? ' (bowler)' : ''), `App.wSet('fielder',${p.id})`, w.fielder === p.id)).join('')}</div></div>`;
  }
  h += `<button class="btn red block" style="margin-top:8px" ${!w.type ? 'disabled' : ''} onclick="App.confirmWicket()">Confirm OUT</button>`;
  sheet(h);
}
App.confirmWicket = () => {
  const w = S.w;
  if (w.type === 'caught' && !w.fielder) return toast('Who took the catch?', true);
  let extra = w.extra, runs = w.type === 'run out' ? w.runs : 0;
  if (['b', 'lb'].includes(extra) && runs === 0) extra = null;
  scoreAction('match_ball', { runs, extra, wicket: { type: w.type, fielder_id: w.fielder, dismissed_id: w.dismissed } });
};
App.retireSheet = () => {
  const inn = curInn();
  sheet(`<h3>Retired hurt 🩹</h3><div class="sub">The batter leaves without being out and can bat again later.</div>
    <div class="plist">${[inn.striker_id, inn.non_striker_id].filter(Boolean).map(id => `<button class="pbtn" onclick="App.ball(0,null,{type:'retired hurt',dismissed_id:${id}})">${esc(pname(id))}</button>`).join('')}</div>`);
};
function batterSheet() {
  const st = S.m, inn = curInn();
  const end = !inn.striker_id ? "striker's end - on strike" : "non-striker's end";
  const players = st.xi[inn.batting_team_id].filter(p => st.available_batters.includes(p.id));
  const retired = new Set(inn.batting.filter(b => b.how === 'retired hurt').map(b => b.id));
  sheet(`<h3>Next batter 🏏</h3><div class="sub">Coming in at the ${end}</div>
    <div class="plist">${players.map(p => `<button class="pbtn" onclick="App.setBatter(${p.id})">${roleIcon(p.role)} ${esc(p.name)}<span class="s">${retired.has(p.id) ? 'retired hurt - returning' : esc(p.role)}</span></button>`).join('')}</div>
    <button class="btn light block" onclick="App.undo()">↶ Undo last ball</button>`, { locked: true });
}
/* v1.27: "max N overs each, but up to X bowlers may bowl Y" - same rule as bowler_cap() on the server */
function bowlerCap(m, balls, pid) {
  const max = m.max_overs_bowler, xb = m.extra_bowlers || 0, xo = m.extra_overs || 0;
  if (!max || !xb || xo <= max) return max;
  const lim = max * m.balls_per_over;
  if ((balls[pid] || 0) > lim) return xo;
  const used = Object.entries(balls || {}).filter(([id, b]) => +id !== +pid && b > lim).length;
  return used < xb ? xo : max;
}
App.bowlerSheet = (change = false) => {
  const st = S.m, inn = curInn(), m = st.match, bpo = m.balls_per_over;
  const start = inn.legal_balls % bpo === 0;
  const xi = st.xi[inn.bowling_team_id];
  const list = xi.map(p => {
    const f = inn.bowling.find(b => b.id === p.id);
    let dis = false, why = f ? `${f.o}-${f.m}-${f.r}-${f.w}` : 'yet to bowl';
    if (start && xi.length > 1 && p.id === st.prev_over_bowler) { dis = true; why = 'bowled last over'; }
    else if (m.max_overs_bowler && start && (st.bowler_balls[p.id] || 0) >= bowlerCap(m, st.bowler_balls, p.id) * bpo) { dis = true; why = 'overs quota done'; }
    if (change && p.id === inn.bowler_id) { dis = true; why = 'bowling now'; }
    return `<button class="pbtn" ${dis ? 'disabled' : ''} onclick="App.setBowler(${p.id})">${roleIcon(p.role)} ${esc(p.name)}<span class="s">${why}</span></button>`;
  });
  const overNo = Math.floor(inn.legal_balls / bpo) + 1;
  sheet(`<h3>${change ? 'Change bowler' : `Over ${overNo} - who bowls? 🎯`}</h3>
    <div class="sub">${change ? 'Use this if a bowler is injured mid-over.' : 'Figures shown as Overs-Maidens-Runs-Wickets'}${m.max_overs_bowler ? ` · max ${m.max_overs_bowler} over${m.max_overs_bowler === 1 ? '' : 's'} each${m.extra_bowlers ? ` (${m.extra_bowlers} bowlers may bowl ${m.extra_overs})` : ''}` : ''}</div>
    <div class="plist">${list.join('')}</div>${change ? '' : '<button class="btn light block" onclick="App.undo()">↶ Undo last ball</button>'}`, { locked: !change });
};

App.matchMenu = () => {
  const st = S.m, m = st.match;
  const live = st.innings.length && curInn().status === 'live' && m.status === 'live';
  sheet(`<h3>Match options</h3><div class="sub">${esc(st.team1.name)} vs ${esc(st.team2.name)}</div><div class="plist">
    ${st.needs === 'completed' && m.status !== 'abandoned' ? '<button class="pbtn" onclick="App.undo()">↶ Undo last ball (re-open match)</button>' : ''}
    ${live ? '<button class="pbtn" onclick="App.endInnings()">⏹️ End this innings now</button>' : ''}
    ${['live', 'scheduled'].includes(m.status) || m.result_method === 'rain' ? '<button class="pbtn" onclick="App.rainSheet()">🌧️ Rain / DLS - reduce overs, revised target</button>' : ''}
    ${['live', 'scheduled'].includes(m.status) ? '<button class="pbtn" onclick="App.abandon()">⛔ Abandon match (no result)</button>' : ''}
    ${st.is_owner ? `<button class="pbtn" onclick="App.scorerSheet()">✍️ Scorer <span class="s">${m.scorer ? esc(m.scorer.display_name) + ' (@' + esc(m.scorer.username) + ')' : 'only you'}</span></button>` : ''}
    ${st.is_owner ? (m.visibility === 'private'
      ? '<button class="pbtn" onclick="App.setVis(\'public\')">🌍 Make match public <span class="s">everyone can watch</span></button>'
      : '<button class="pbtn" onclick="App.setVis(\'private\')">🔒 Make match private <span class="s">only you can see it</span></button>') : ''}
    ${m.visibility === 'private' ? '' : '<button class="pbtn" onclick="App.shareMatch()">🔗 Share live score link</button>'}
    ${st.is_owner ? '<button class="pbtn" style="color:var(--red)" onclick="App.deleteMatch()">🗑️ Delete match</button>' : ''}</div>`);
};
/* ----- Who scores this match ----- */
App.scorerSheet = () => {
  const m = S.m.match;
  sheet(`<h3>✍️ Who scores this match?</h3><div class="sub">Only you can score your match. You can also hand scoring to one other player by their username - for example a friend at the ground. You can take it back any time.</div>
    ${m.scorer ? `<div class="card" style="box-shadow:none;border:1px solid var(--line)"><div class="row"><div class="grow"><b>${esc(m.scorer.display_name)}</b><div class="small muted">@${esc(m.scorer.username)} can score this match</div></div>
      <button class="btn sm light" onclick="App.setScorer('')">Take back</button></div></div>` : '<div class="small muted" style="margin-bottom:10px">Nobody else can score it right now.</div>'}
    <form class="row" onsubmit="event.preventDefault();App.setScorer(this.u.value)"><input class="input grow" name="u" placeholder="Username, e.g. aayush" autocapitalize="none" autocomplete="off" required><button class="btn sm">${m.scorer ? 'Change' : 'Allow'}</button></form>`);
};
App.setScorer = u => run(async () => {
  const r = await api('match_scorer', { id: S.m.match.id, username: String(u || '').trim() });
  S.m.match.scorer = r.scorer; closeSheet(); toast(r.scorer ? `${r.scorer.display_name} can now score this match ✓` : 'Only you can score this match now'); render();
});
/* ----- Rain / DLS (Duckworth-Lewis-Stern, ICC Standard Edition) ----- */
App.rainSheet = () => {
  const st = S.m, m = st.match, d = st.dls || {};
  const inn = st.innings.length ? curInn() : null;
  const bpo = m.balls_per_over;
  let stage, cur, minO;
  if (!inn) { stage = 'pre'; cur = m.overs; minO = 1; }
  else if (inn.status === 'completed' && inn.no === 1) { stage = 'break'; cur = d.inn2_overs ?? inn.max_overs; minO = 1; }
  else if (inn.status === 'live') { stage = inn.no === 1 ? 'inn1' : 'inn2'; cur = inn.max_overs; minO = Math.max(1, Math.ceil(inn.legal_balls / bpo)); }
  else stage = 'done';
  const intr = (d.interruptions || []).map(x => `<div class="kv"><span>Inns ${x.innings_no} · at ${x.at} ov, ${x.wickets} wkt</span><span>${x.overs_before} → ${x.overs_after} overs</span></div>`).join('');
  const level = `<div class="field" style="margin-top:12px"><label>Level (G50 - average 50-over score)</label><div class="chips wrap">
      <button class="chip ${d.g50 !== 245 ? 'on' : ''}" onclick="App.dlsLevel(200)">Junior / club - 200</button>
      <button class="chip ${d.g50 === 245 ? 'on' : ''}" onclick="App.dlsLevel(245)">International - 245</button></div></div>`;
  let body = '';
  if (stage === 'done') {
    body = m.result_method === 'rain'
      ? `<div class="notice">Result decided by rain. Play resumed? Undo it to continue the chase.</div><button class="btn block" onclick="App.undo()">↶ Undo rain result - resume play</button>`
      : `<div class="notice">The match is finished.</div>`;
  } else {
    const batName = stage === 'break' ? teamById(st.next.batting_team_id).name : stage === 'pre' ? '' : teamById(inn.batting_team_id).name;
    const what = { pre: 'Overs per side for this match', break: `Overs for ${esc(batName)}'s chase`, inn1: `Overs for ${esc(batName)} now`, inn2: `Overs for ${esc(batName)} now` }[stage];
    const sub = { pre: 'Rain before the start: both teams simply get fewer overs.', break: 'Rain at the innings break: the chasing team gets fewer overs and DLS sets the target.',
      inn1: `Rain in the 1st innings at ${inn ? inn.overs : ''} overs, ${inn ? inn.wickets : ''} down. The team batting second will get the same overs, and DLS sets the target at the break.`,
      inn2: `Rain in the chase at ${inn ? inn.overs : ''} overs, ${inn ? inn.wickets : ''} down. DLS revises the target.` }[stage];
    body = `<div class="small muted" style="margin-bottom:10px">${sub}</div>
      <div class="field"><label>${what}</label>
        <div class="row"><button class="btn sm light" onclick="App.rainStep(-1)">−</button><input class="input" id="rainOv" type="number" inputmode="numeric" min="${minO}" max="${cur}" value="${Math.max(minO, cur - 1)}" style="text-align:center;font-size:22px;font-weight:900" oninput="App.rainPreview()"><button class="btn sm light" onclick="App.rainStep(1)">+</button></div></div>
      <div id="rainPrev" class="notice" style="display:none"></div>
      <button class="btn block" onclick="App.rainApply(this)">🌧️ Apply reduced overs</button>`;
    if (stage === 'inn2') {
      body += `<div class="divider"></div>
        <div class="kv" style="border:0;padding:4px 0"><span>DLS par score now</span><span class="big-num">${d.par}</span></div>
        <div class="small muted">If no more play is possible now, ${esc(teamById(inn.batting_team_id).name)} ${inn.runs > d.par ? 'would WIN' : inn.runs === d.par ? 'would TIE' : 'would LOSE'} (score ${inn.runs}${d.can_result ? '' : `; but at least ${d.min_overs} overs are needed for a result`}).</div>
        <button class="btn red block" style="margin-top:10px" onclick="App.rainEnd()">⛈️ Rain stopped play - decide by DLS</button>
        ${d.par_table && d.par_table.length ? `<details style="margin-top:12px"><summary class="link">Par score at the end of each over (${inn.wickets} down)</summary>
          <div class="card flush" style="margin-top:8px;box-shadow:none;border:1px solid var(--line)">${d.par_table.map(r => `<div class="kv"><span>After ${r.over} overs</span><span>${r.par}</span></div>`).join('')}</div></details>` : ''}`;
    }
  }
  sheet(`<h3>Rain & DLS 🌧️</h3><div class="sub">Duckworth-Lewis-Stern method (ICC Standard Edition)</div>${body}
    ${d.R1 !== undefined ? `<div class="divider"></div><div class="kv" style="border:0;padding:3px 0"><span>Resources - team 1</span><span>${d.R1}%</span></div>${d.R2 !== undefined ? `<div class="kv" style="border:0;padding:3px 0"><span>Resources - team 2</span><span>${d.R2}%</span></div>` : ''}` : ''}
    ${intr ? `<div class="section" style="margin-top:12px"><h2 style="font-size:15px">Interruptions</h2><button class="link" onclick="App.rainUndo()">Undo last</button></div><div class="card flush" style="box-shadow:none;border:1px solid var(--line)">${intr}</div>` : (d.inn2_overs ? `<button class="link" onclick="App.rainUndo()">Undo chase overs change</button>` : '')}
    ${stage !== 'done' ? level : ''}
    <button class="link" style="margin-top:10px" onclick="App.dlsHelp()">How does DLS work?</button>`);
  if (stage !== 'done') App.rainPreview();
};
App.rainStep = dlt => { const i = $('#rainOv'); i.value = Math.min(+i.max, Math.max(+i.min, (+i.value || 0) + dlt)); App.rainPreview(); };
let rainT;
App.rainPreview = () => {
  clearTimeout(rainT);
  rainT = setTimeout(async () => {
    const el = $('#rainPrev'), ov = +($('#rainOv') || {}).value;
    if (!el || !ov) return;
    try {
      const r = await api('match_dls_preview', { id: S.m.match.id, overs: ov });
      el.style.display = 'block';
      el.innerHTML = r.target ? `Revised DLS target: <b>${r.target}</b> from ${r.overs} overs` : esc(r.note || '');
    } catch (e) { el.style.display = 'none'; }
  }, 200);
};
App.rainApply = btn => {
  const ov = +$('#rainOv').value;
  const before = S.m.innings.length ? curInn().target : null;
  run(async () => {
    if (!(await scoreAction('match_rain', { overs: ov }))) return;
    const st = S.m, inn = st.innings.length ? curInn() : null;
    let msg = `Rain! The match is reduced to ${ov} overs.`;
    if (inn && inn.no === 2 && inn.target !== before) msg = `Rain has reduced the chase to ${ov} overs. The revised DLS target is ${inn.target}.`;
    else if (st.needs === 'openers' && st.next && st.next.no === 2) msg = `Rain! ${teamById(st.next.batting_team_id).name} will chase ${st.next.target} in ${st.next.overs} overs, by the DLS method.`;
    toast(msg); Sound.say(msg, { delay: 50 });
  }, btn);
};
App.rainEnd = () => confirmSheet('Rain stopped play?', 'The match will be decided on the DLS par score right now. You can undo this if play resumes.', 'Decide by DLS', () => scoreAction('match_rain_end'), false);
App.rainUndo = () => scoreAction('match_rain_undo').then(() => toast('Rain change undone'));
App.dlsLevel = g => scoreAction('match_dls_level', { g50: g }).then(() => App.rainSheet());
App.dlsHelp = () => sheet(`<h3>How DLS works ☔</h3><div class="small" style="line-height:1.6">
  A team has two <b>resources</b> to score runs: <b>overs left</b> and <b>wickets in hand</b>. The ICC table gives the % of resources left for every combination (50 overs & 0 wickets down = 100%).<br><br>
  When rain takes overs away, each team's resources (R1, R2) are worked out. Then:<br>
  • If team 2 has <b>fewer</b> resources: target = team 1 score × R2 ÷ R1, plus 1.<br>
  • If team 2 has <b>more</b>: target = team 1 score + G50 × (R2 − R1) ÷ 100, plus 1 (G50 = 245 internationals, 200 junior/club).<br><br>
  If rain ends the match during the chase, the batting side wins if it is <b>ahead of the par score</b>, ties if level, and loses if behind - provided enough overs were bowled (20 in a 50-over game, 5 in a T20).</div>
  <button class="btn light block" style="margin-top:12px" onclick="App.rainSheet()">← Back</button>`);
App.setVis = v => run(async () => {
  S.m = await api('match_visibility', { id: S.m.match.id, visibility: v });
  closeSheet(); drawMatch(false);
  toast(v === 'private' ? '🔒 Match is now private - only you can see it' : '🌍 Match is now public');
});
App.endInnings = () => S.m.match.format === 'test'
  ? confirmSheet('Declare the innings?', `${teamById(curInn().batting_team_id).name} declare at ${curInn().runs}/${curInn().wickets}. The other side bats next.`, 'Declare', () => scoreAction('match_end_innings'))
  : confirmSheet('End innings?', 'Use this for rain, bad light or a declaration. The innings will close at the current score.', 'End innings', () => scoreAction('match_end_innings'));
App.drawMatch = () => confirmSheet('End as a draw?', 'Time is up - the match is drawn. Both innings close at the current scores. You can undo this from the menu.', 'Match drawn', () => scoreAction('match_draw'));
App.abandon = () => confirmSheet('Abandon match?', 'The match will be recorded as "No result".', 'Abandon', () => scoreAction('match_abandon'));
App.deleteMatch = () => confirmSheet('Delete this match?', 'All scores for this match will be removed. This cannot be undone.', 'Delete', () => run(async () => { await api('match_delete', { id: S.m.match.id }); closeSheet(); S.m = null; toast('Match deleted'); go('#/matches'); }));
App.shareMatch = async () => {
  const st = S.m, base = location.origin + location.pathname.replace(/[^/]*$/, '');
  // Public matches get the /m/ID page (shows the score as a WhatsApp/Facebook preview); private ones open in the app
  const url = st.match.visibility === 'private' ? base + '#/match/' + st.match.id : base + 'm/' + st.match.id;
  const inn = st.innings.length ? curInn() : null;
  const text = `🏏 ${st.team1.name} vs ${st.team2.name}` + (inn ? ` - ${teamById(inn.batting_team_id).short_name} ${inn.runs}/${inn.wickets} (${inn.overs})` : '') + (st.match.result_text ? ` - ${st.match.result_text}` : '') + ' · Cric-Scorer';
  closeSheet();
  if (navigator.share) { try { await navigator.share({ title: 'Cric-Scorer', text, url }); } catch (e) { /* cancelled */ } }
  else { try { await navigator.clipboard.writeText(text + '\n' + url); toast('Link copied!'); } catch (e) { toast(url); } }
};

/* ----- setup: playing XI ----- */
function xiSetup() {
  const st = S.m, m = st.match;
  if (!S.squads || S.squads.mid !== m.id) { loadSquads(); return `<div class="content">${sk(3)}</div>`; }
  const tid = S.xiTeam, team = S.squads[tid], sel = S.xiSel[tid];
  const tabs = [st.team1, st.team2].map(t => `<button class="${t.id === tid ? 'on' : ''}" onclick="S.xiTeam=${t.id};drawMatch(false)">${esc(t.short_name)} · ${S.xiSel[t.id].size}/${m.players_per_side}</button>`).join('');
  const list = team.players.map(p => `<button class="pbtn ${sel.has(+p.id) ? 'on' : ''}" onclick="App.xiToggle(${p.id})"><span class="chk">${sel.has(+p.id) ? '✓' : ''}</span>${roleIcon(p.role)} ${esc(p.name)}<span class="s">${esc(p.role)}</span></button>`).join('');
  return `<div class="content"><div class="section" style="margin-top:6px"><h2>Pick playing teams</h2><span class="small muted">up to ${m.players_per_side} each</span></div>
    <div class="seg">${tabs}</div>
    <div class="row" style="margin-bottom:10px;flex-wrap:wrap"><button class="btn sm light" onclick="App.xiFirst()">Pick first ${m.players_per_side}</button><button class="btn sm light" onclick="App.xiClear()">Clear</button>
    ${team.team.can_edit ? '<button class="btn sm light" onclick="App.quickPlayer()">+ New player</button>' : ''}</div>
    ${team.players.length ? `<div class="plist">${list}</div>` : `<div class="card empty"><div class="big">🧢</div>This team has no players yet.${team.team.can_edit ? '<br>Tap <b>+ New player</b> to add some.' : ''}</div>`}
    <button class="btn block" style="margin:8px 0 20px" onclick="App.saveXI(this)">Save teams & go to toss →</button></div>`;
}
async function loadSquads() {
  const st = S.m;
  try {
    const [a, b] = await Promise.all([api('team_get', { id: st.team1.id }), api('team_get', { id: st.team2.id })]);
    S.squads = { mid: st.match.id, [st.team1.id]: a, [st.team2.id]: b };
    S.xiSel = {};
    for (const [t, d] of [[st.team1, a], [st.team2, b]]) {
      const saved = (st.xi[t.id] || []).map(p => p.id);
      S.xiSel[t.id] = new Set(saved.length ? saved : (d.players.length <= st.match.players_per_side ? d.players.map(p => +p.id) : []));
    }
    S.xiTeam = S.xiTeam && S.squads[S.xiTeam] ? S.xiTeam : st.team1.id;
    drawMatch(false);
  } catch (e) { toast(e.message, true); }
}
App.xiToggle = id => {
  const sel = S.xiSel[S.xiTeam], max = S.m.match.players_per_side;
  if (sel.has(id)) sel.delete(id);
  else { if (sel.size >= max) return toast(`Only ${max} players allowed`, true); sel.add(id); }
  drawMatch(false);
};
App.xiFirst = () => { S.xiSel[S.xiTeam] = new Set(S.squads[S.xiTeam].players.slice(0, S.m.match.players_per_side).map(p => +p.id)); drawMatch(false); };
App.xiClear = () => { S.xiSel[S.xiTeam] = new Set(); drawMatch(false); };
App.saveXI = btn => {
  const st = S.m, min = st.match.last_man_stands ? 1 : 2;
  for (const t of [st.team1, st.team2]) if (S.xiSel[t.id].size < min) { S.xiTeam = t.id; drawMatch(false); return toast(`Pick at least ${min} players for ${t.name}`, true); }
  const xi = { [st.team1.id]: [...S.xiSel[st.team1.id]], [st.team2.id]: [...S.xiSel[st.team2.id]] };
  run(() => scoreAction('match_xi', { xi }), btn);
};
App.quickPlayer = () => {
  sheet(`<h3>New player</h3><div class="sub">Added to ${esc(S.squads[S.xiTeam].team.name)}</div>
    <form onsubmit="App.saveQuickPlayer(event)"><div class="field"><label>Name</label><input class="input" name="name" required autofocus></div>
    <div class="field"><label>Role</label><select class="input" name="role">${Object.keys(ROLE_ICON).map(r => `<option>${r}</option>`).join('')}</select></div>
    <button class="btn block">Add player</button></form>`);
};
App.saveQuickPlayer = e => {
  e.preventDefault();
  run(async () => {
    const f = formData(e.target); f.team_id = S.xiTeam;
    const r = await api('player_save', f);
    S.squads[S.xiTeam] = await api('team_get', { id: S.xiTeam });
    if (S.xiSel[S.xiTeam].size < S.m.match.players_per_side) S.xiSel[S.xiTeam].add(r.id);
    closeSheet(); drawMatch(false);
  }, e.submitter);
};

/* ----- setup: toss ----- */
function tossSetup() {
  const st = S.m, t = S.toss || {};
  const tb = x => `<button class="${t.w === x.id ? 'on' : ''}" onclick="S.toss.w=${x.id};drawMatch(false)">${badge(x)}<span>${esc(x.name)}</span></button>`;
  return `<div class="content"><div class="card">
    <div class="coin" id="coin">${esc(t.coin || 'TOSS')}</div>
    <div style="text-align:center;margin-bottom:12px"><button class="btn sm light" onclick="App.flip()">🪙 Flip the coin</button></div>
    <div class="field"><label>Who won the toss?</label><div class="toss-pick">${tb(st.team1)}${tb(st.team2)}</div></div>
    <div class="field"><label>They chose to</label><div class="toss-pick">
      <button class="${t.d === 'bat' ? 'on' : ''}" onclick="S.toss.d='bat';drawMatch(false)"><span style="font-size:30px">🏏</span>Bat first</button>
      <button class="${t.d === 'bowl' ? 'on' : ''}" onclick="S.toss.d='bowl';drawMatch(false)"><span style="font-size:30px">🎯</span>Bowl first</button></div></div>
    <button class="btn block" onclick="App.saveToss(this)">Continue →</button>
    <button class="btn light block" style="margin-top:8px" onclick="App.editXI()">← Change playing teams</button></div></div>`;
}
App.flip = () => {
  const c = $('#coin'); if (!c) return;
  c.classList.remove('flip'); void c.offsetWidth; c.classList.add('flip'); c.textContent = '…';
  setTimeout(() => { S.toss.coin = Math.random() < .5 ? 'HEADS' : 'TAILS'; c.textContent = S.toss.coin; }, 1200);
};
App.saveToss = btn => {
  if (!S.toss.w) return toast('Who won the toss?', true);
  if (!S.toss.d) return toast('Bat or bowl first?', true);
  run(() => scoreAction('match_toss', { winner_id: S.toss.w, decision: S.toss.d }), btn);
};
App.editXI = () => { S.m.needs = 'players'; S.squads = null; S.toss = {}; drawMatch(false); };

/* ----- setup: openers ----- */
function openersSetup() {
  const st = S.m, nx = st.next, op = S.op || (S.op = {});
  if (op.no !== nx.no) { S.op = { no: nx.no }; }
  const o = S.op;
  if (nx.swapped) { const t = nx.batting_team_id; nx.batting_team_id = nx.bowling_team_id; nx.bowling_team_id = t; nx.swapped = false; }
  const chips = (list, key, other) => list.map(p => `<button class="chip ${o[key] === p.id ? 'on' : ''}" ${other && o[other] === p.id ? 'disabled' : ''} onclick="S.op['${key}']=${p.id};drawMatch(false)">${roleIcon(p.role)} ${esc(p.name)}</button>`).join('');
  let brk = '';
  let bt = teamById(nx.batting_team_id), bwt = teamById(nx.bowling_team_id);
  if (st.match.format === 'test' && nx.no > 1) {
    const tot = st.match.totals || {}; const a = tot[nx.batting_team_id] || 0, b = tot[nx.bowling_team_id] || 0;
    const last = st.innings[st.innings.length - 1];
    const btn = teamById(nx.batting_team_id);
    let line = nx.no === 4 ? `${esc(btn.name)} need ${nx.target} to win` : nx.no === 2 ? `${esc(btn.name)} trail by ${plural(b - a, 'run')}` : (nx.lead >= 0 ? `${esc(teamById(nx.batting_team_id).name)} lead by ${plural(nx.lead, 'run')}` : `${esc(teamById(nx.bowling_team_id).name)} lead by ${plural(-nx.lead, 'run')}`);
    if (nx.no === 3 && o.fo) line = `${esc(teamById(nx.follow_on_team).name)} follow on, trailing by ${plural(nx.lead, 'run')}`;
    brk = `<div class="card" style="text-align:center"><div style="font-size:34px">☕</div><b class="big-num">Innings break</b>
      <div class="muted">${esc(teamById(last.batting_team_id).name)} ${last.declared ? 'declared at' : 'made'} ${last.runs}/${last.wickets} in ${last.overs} overs</div>
      <div style="margin-top:6px;font-weight:800;color:var(--green)">${line}</div>
      ${nx.no === 3 && nx.can_follow_on ? `<label class="switch" style="margin-top:10px"><span><b>Enforce the follow-on</b><div class="small muted">${esc(teamById(nx.follow_on_team).name)} bat again (lead ≥ ${st.match.follow_on})</div></span><input type="checkbox" ${o.fo ? 'checked' : ''} onchange="S.op.fo=this.checked;S.op.s=S.op.ns=S.op.b=null;drawMatch(false)"><span class="knob"></span></label>` : ''}</div>`;
    if (nx.no === 3 && o.fo) { const t = nx.batting_team_id; nx.batting_team_id = nx.bowling_team_id; nx.bowling_team_id = t; nx.swapped = true; }
    bt = teamById(nx.batting_team_id); bwt = teamById(nx.bowling_team_id);
  } else if (nx.no === 2) {
    const i1 = st.innings[0];
    brk = `<div class="card" style="text-align:center"><div style="font-size:34px">☕</div><b class="big-num">Innings break</b>
      <div class="muted">${esc(teamById(i1.batting_team_id).name)} scored ${i1.runs}/${i1.wickets} in ${i1.overs} overs</div>
      <div style="margin-top:6px;font-weight:800;color:var(--green)">${esc(bt.name)} need ${nx.target} runs from ${nx.overs * st.match.balls_per_over} balls${nx.dls ? ` <span class="dls-tag">DLS</span>` : ''}</div>
      ${nx.dls ? `<div class="small muted" style="margin-top:4px">Target revised by the DLS method (${nx.overs} overs)</div>` : ''}</div>`;
  }
  const bat = st.xi[nx.batting_team_id], bowl = st.xi[nx.bowling_team_id];
  const single = st.match.last_man_stands && bat.length === 1;
  return `<div class="content">${brk}<div class="card">
    <div class="row" style="margin-bottom:10px">${badge(bt, 'sm')}<b class="grow">${esc(bt.name)} - opening batters</b></div>
    <div class="field"><label>Striker (faces the first ball)</label><div class="chips wrap">${chips(bat, 's', 'ns')}</div></div>
    ${single ? '' : `<div class="field"><label>Non-striker</label><div class="chips wrap">${chips(bat, 'ns', 's')}</div></div>`}
    <div class="divider"></div>
    <div class="row" style="margin:10px 0">${badge(bwt, 'sm')}<b class="grow">${esc(bwt.name)} - opening bowler</b></div>
    <div class="chips wrap" style="margin-bottom:14px">${chips(bowl, 'b')}</div>
    <button class="btn block" onclick="App.startInnings(this)">Play ball! 🏏</button>
    ${nx.no >= 2 ? '<button class="btn light block" style="margin-top:8px" onclick="App.undo()">↶ Undo last ball of previous innings</button>' : '<button class="btn light block" style="margin-top:8px" onclick="App.undoToss()">← Redo the toss</button>'}
  </div></div>`;
}
App.startInnings = btn => {
  const o = S.op, st = S.m, single = st.match.last_man_stands && st.xi[st.next.batting_team_id].length === 1;
  if (!o.s || (!single && !o.ns) || !o.b) return toast('Pick both batters and the bowler', true);
  run(() => scoreAction('match_start_innings', { striker_id: o.s, non_striker_id: single ? null : o.ns, bowler_id: o.b, follow_on: o.fo ? 1 : 0 }), btn);
};
App.undoToss = () => { S.m.needs = 'toss'; S.toss = {}; drawMatch(false); };

/* ----- result ----- */
function resultCard(st) {
  const m = st.match;
  return `<div class="content"><div class="result-card"><div class="tr">${m.status === 'abandoned' ? '🌧️' : '🏆'}</div><b>${esc(m.result_text || 'Match finished')}</b>
    ${m.player_of_match ? `<div class="pom" onclick="go('#/player/${m.player_of_match.id}')"><span class="avatar">⭐</span><div><div class="small">Player of the Match</div><b style="font-size:20px;margin:0">${esc(m.player_of_match.name)}</b></div></div>` : ''}</div>
    ${st.can_edit ? `<button class="btn light block" style="margin-bottom:12px" onclick="go('${m.tournament ? '#/tournament/' + m.tournament.id : '#/match/new'}')">${m.tournament ? '← Back to tournament' : '＋ Start another match'}</button>` : ''}</div>`;
}

/* ----- tabs: scorecard / overs / commentary ----- */
function matchTabs(st) {
  const tabs = [['card', 'Scorecard'], ['overs', 'Overs'], ['comm', 'Ball by ball']];
  const n = st.innings.length;
  if (S.cardInn === null || S.cardInn >= n || S.cardInnN !== n) { S.cardInn = n - 1; S.cardInnN = n; }
  const innSel = n > 1 ? `<div class="chips" style="margin-bottom:10px">${st.innings.map((i, k) => `<button class="chip ${k === S.cardInn ? 'on' : ''}" onclick="S.cardInn=${k};drawMatch(false)">${esc(teamById(i.batting_team_id).short_name)} ${i.runs}/${i.wickets}</button>`).join('')}</div>` : '';
  const inn = st.innings[S.cardInn];
  const body = S.mTab === 'overs' ? oversView(st, inn) : S.mTab === 'comm' ? commView(st, inn) : scorecardView(st, inn);
  return `<div class="content" style="padding-top:2px"><div class="seg">${tabs.map(([k, l]) => `<button class="${S.mTab === k ? 'on' : ''}" onclick="S.mTab='${k}';drawMatch(false)">${l}</button>`).join('')}</div>${innSel}${body}</div>`;
}
function scorecardView(st, inn) {
  const bt = teamById(inn.batting_team_id), bwt = teamById(inn.bowling_team_id);
  const live = inn.status === 'live';
  const bat = inn.batting.map(b => {
    const atCrease = live && (b.id === inn.striker_id || b.id === inn.non_striker_id);
    return `<tr onclick="go('#/player/${b.id}')"><td><div class="nm">${esc(b.name)}${atCrease && b.id === inn.striker_id ? ' *' : ''}</div><div class="how">${atCrease ? 'batting' : esc(b.how)}</div></td><td><b>${b.r}</b></td><td>${b.b}</td><td>${b.f4}</td><td>${b.f6}</td><td>${b.sr}</td></tr>`;
  }).join('');
  const ex = inn.extras_detail;
  const bowl = inn.bowling.map(b => `<tr onclick="go('#/player/${b.id}')"><td><div class="nm">${esc(b.name)}${b.ht ? ' 🎩' : ''}</div>${b.wd || b.nb ? `<div class="how">${b.wd ? b.wd + 'wd ' : ''}${b.nb ? b.nb + 'nb' : ''}</div>` : ''}</td><td>${b.o}</td><td>${b.m}</td><td>${b.r}</td><td><b>${b.w}</b></td><td>${b.econ}</td></tr>`).join('');
  return `<div class="card flush">
    <div class="inn-head">${badge(bt, 'sm')}<span>${esc(bt.name)}</span><b>${inn.runs}/${inn.wickets} <small style="font-size:14px">(${inn.overs})</small></b></div>
    <table class="tbl"><tr><th>Batter</th><th>R</th><th>B</th><th>4s</th><th>6s</th><th>SR</th></tr>${bat || '<tr><td colspan="6" class="muted">No batting yet</td></tr>'}
      <tr><td>Extras</td><td colspan="5">${inn.extras} <span class="muted small">(b ${ex.b}, lb ${ex.lb}, wd ${ex.wd}, nb ${ex.nb})</span></td></tr>
      <tr class="total"><td>Total</td><td colspan="5">${inn.runs}/${inn.wickets} <span class="muted small">(${inn.overs} ov, RR ${Number(inn.crr).toFixed(2)})</span></td></tr></table>
    ${inn.dnb.length ? `<div class="kv"><span>${live ? 'Yet to bat' : 'Did not bat'}</span><span>${inn.dnb.map(p => esc(p.name)).join(', ')}</span></div>` : ''}
    ${inn.fow.length ? `<div class="kv"><span>Fall of wickets</span><span>${inn.fow.map(f => `${f.score}-${f.wkt} (${esc(f.name)}, ${f.overs})`).join(', ')}</span></div>` : ''}
    <div class="inn-head" style="background:var(--pitch-3)">${badge(bwt, 'sm')}<span>${esc(bwt.name)} bowling</span></div>
    <table class="tbl"><tr><th>Bowler</th><th>O</th><th>M</th><th>R</th><th>W</th><th>Econ</th></tr>${bowl || '<tr><td colspan="6" class="muted">No bowling yet</td></tr>'}</table></div>`;
}
function oversView(st, inn) {
  const ov = inn.overs_list;
  if (!ov.length) return '<div class="card empty">No overs bowled yet.</div>';
  const max = Math.max(6, ...ov.map(o => o.runs));
  const bars = ov.map(o => `<div class="bar ${o.wkts ? 'hasw' : ''}" style="height:${Math.max(3, o.runs / max * 100)}%" title="Over ${o.over}: ${o.runs}"></div>`).join('');
  const step = ov.length > 20 ? 5 : ov.length > 10 ? 2 : 1;
  const xs = ov.map(o => `<span>${o.over % step === 0 || o.over === 1 ? o.over : ''}</span>`).join('');
  const list = [...ov].reverse().map(o => `<div class="comm"><span class="ov">Ov ${o.over}</span><div class="grow"><div class="row" style="justify-content:space-between"><b class="small">${esc(o.bowler)}</b><b class="small">${plural(o.runs, 'run')}${o.wkts ? ' · ' + plural(o.wkts, 'wkt') : ''}</b></div>
    <div class="row" style="gap:5px;flex-wrap:wrap;margin-top:6px">${o.balls.map(ballChip).join('')}</div></div></div>`).join('');
  return `<div class="card flush"><div class="kv" style="border-top:0"><span>Runs per over</span><span class="muted small">● = wicket</span></div><div class="bars">${bars}</div><div class="bars-x">${xs}</div>${list}</div>`;
}
function commView(st, inn) {
  const tl = [...inn.timeline].reverse().slice(0, 150);
  if (!tl.length) return '<div class="card empty">No balls bowled yet.</div>';
  return `<div class="card flush">${tl.map(b => `<div class="comm ${b.w ? 'w' : ''}"><span class="ov">${b.ov}</span><div class="row grow" style="align-items:flex-start">${ballChip(b.label)}<span class="small" style="flex:1">${esc(b.desc)}</span></div></div>`).join('')}</div>`;
}

/* =====================================================================
   TEAMS & PLAYERS
   ===================================================================== */
async function viewTeams(cat) {
  if (cat) S.teamCat = cat;
  const c = S.teamCat || 'custom';
  const tabs = [['custom', '🧢 Our Teams'], ['ipl', '🏟️ IPL'], ['international', '🌍 International']];
  mount(topbar('Teams') + `<div class="content"><div class="seg">${tabs.map(([k, l]) => `<button class="${c === k ? 'on' : ''}" onclick="go('#/teams?c=${k}')">${l}</button>`).join('')}</div>
    <div class="search">${I.search}<input class="input" placeholder="Search teams" oninput="App.filterList(this.value)"></div>
    <div id="tl">${sk(4)}</div></div><button class="fab" aria-label="Create team" onclick="go('#/team/new')">${I.plus}</button>`, 'teams');
  const d = await api('teams_list', { category: c });
  if (S.extras) S.extras.team_requests = d.requests;
  $('#tl').innerHTML = (d.requests && c === 'custom' ? `<div class="news news-new"><div class="grow"><b>🔔 ${plural(d.requests, 'player')} want${d.requests === 1 ? 's' : ''} to play with your teams</b><div>Open a team with 🔔 to allow or decline.</div></div></div>` : '') + (d.teams.length ? `<div class="card flush list">${d.teams.map(t => `<div class="item" data-q="${esc((t.name + ' ' + t.short_name).toLowerCase())}" onclick="go('#/team/${t.id}')">${badge(t)}
      <div class="grow"><div class="t ellipsis">${esc(t.name)}</div><div class="s">${plural(+t.players, 'player')}${t.captain && t.captain !== 'TBA' ? ' · C: ' + esc(t.captain) : ''}${c === 'custom' ? ' · by ' + esc(t.mine ? 'you' : t.owner_name || '-') : ''}</div></div>${t.requests ? `<span class="req-pill">🔔 ${t.requests}</span>` : ''}${t.can_use === false ? `<span class="lock-pill">${t.my_access === 'pending' ? '⏳' : '🔒'}</span>` : ''}<span class="chev">${I.chev}</span></div>`).join('')}</div>`
    : `<div class="card empty"><div class="big">🧢</div>No teams here yet.<br><button class="btn" style="margin-top:12px" onclick="go('#/team/new')">Create your team</button></div>`);
}
App.filterList = q => {
  q = q.toLowerCase().trim();
  document.querySelectorAll('[data-q]').forEach(el => el.classList.toggle('hidden', q && !el.dataset.q.includes(q)));
};

async function viewTeam(id) {
  mount(topbar('Team', { back: true }) + `<div class="content">${sk(4)}</div>`, 'teams');
  const d = await api('team_get', { id: +id });
  const t = d.team; S.team = d;
  const groups = [['Batter', '🏏 Batters'], ['Wicket-keeper', '🧤 Wicket-keepers'], ['All-rounder', '⭐ All-rounders'], ['Bowler', '🎯 Bowlers']];
  const fg = textOn(t.color);
  const actions = t.can_edit ? `<button class="icon-btn" aria-label="Edit" onclick="go('#/team/${t.id}/edit')">${I.edit}</button>` : '';
  const squad = groups.map(([r, label]) => {
    const ps = d.players.filter(p => p.role === r);
    if (!ps.length) return '';
    return `<div class="section"><h2>${label}</h2><span class="small muted">${ps.length}</span></div><div class="card flush list">${ps.map(p => `<div class="item" onclick="go('#/player/${p.id}')">
      <span class="avatar">${esc(p.name.split(' ').map(w => w[0]).slice(0, 2).join(''))}</span>
      <div class="grow"><div class="t ellipsis">${esc(p.name)}${t.captain === p.name ? ' <span class="role-tag">C</span>' : ''}${+p.is_overseas ? ' ✈️' : ''}</div>
      <div class="s ellipsis">${esc([p.batting_style, p.bowling_style].filter(Boolean).join(' · '))}${p.country && t.category !== 'international' ? ' · ' + esc(p.country) : ''}</div></div>
      <span class="chev">${I.chev}</span></div>`).join('')}</div>`;
  }).join('');
  mount(topbar(t.short_name, { back: true, actions }) + `<div class="team-hero" style="background:${esc(t.color)};color:${fg}"><span class="badge lg" style="background:${fg === '#ffffff' ? 'rgba(255,255,255,.18)' : 'rgba(0,0,0,.08)'};color:${fg}">${esc(String(t.short_name).slice(0, 4))}</span>
      <div><h2>${esc(t.name)}</h2><p>${{ ipl: 'IPL franchise', international: 'International team', custom: 'Custom team' }[t.category]} · ${plural(d.players.length, 'player')}</p>
      <p>${t.captain ? 'Captain: ' + esc(t.captain) : (t.can_edit ? 'No captain yet' : '')}${t.can_edit ? ` <button class="btn sm light" style="margin-left:6px;padding:4px 10px" onclick="App.captainSheet()">©️ ${t.captain ? 'Change' : 'Pick'} captain</button>` : ''}</p>${t.category === 'custom' && S.user && S.user.is_admin ? `<p>👑 Owner: ${esc(t.owner_name || 'nobody')} <button class="btn sm light" style="margin-left:6px;padding:4px 10px" onclick="App.ownerSheet()">Change owner</button></p>` : ''}${t.home_ground ? `<p>🏟️ ${esc(t.home_ground)}</p>` : ''}</div></div>
    <div class="content">
      ${teamAccessCard(t)}
      ${t.can_edit ? `<div class="grid2" style="margin-bottom:6px"><button class="btn" onclick="App.playerForm(null)">${I.plus} Add player</button><button class="btn light" onclick="App.pickPlayers()">📋 Pick from teams</button></div>` : ''}
      ${squad || `<div class="card empty"><div class="big">🧢</div>No players yet.</div>`}
      ${d.recent.length ? `<div class="section"><h2>Recent matches</h2></div>${d.recent.map(matchCard).join('')}` : ''}
      ${t.can_use !== false ? `<button class="btn light block" style="margin-top:10px" onclick="App.startWith(${t.id})">🏏 Start a match with ${esc(t.short_name)}</button>` : ''}
    </div>`, 'teams');
}
App.startWith = () => go('#/match/new');  // team pickers list every team
/* ---------- Captain: the owner picks any player of the squad ---------- */
App.captainSheet = () => {
  const d = S.team, t = d.team;
  if (!d.players.length) return toast('Add players first', true);
  sheet(`<h3>©️ Team captain</h3><div class="sub">${esc(t.name)} - tap a player. The captain shows a C on the team page and in the playing XI.</div>
    <div class="plist">${d.players.map(p => `<button class="pbtn ${t.captain === p.name ? 'on' : ''}" onclick="App.setCaptain(${p.id})"><span class="chk">${t.captain === p.name ? '✓' : ''}</span>${roleIcon(p.role)} ${esc(p.name)}<span class="s">${esc(p.role)}</span></button>`).join('')}</div>
    ${t.captain ? '<button class="btn light block" onclick="App.setCaptain(null)">Remove captain</button>' : ''}`);
};
/* ---------- Owner: the admin hands a custom team to another player (v1.26) ---------- */
App.ownerSheet = () => {
  const t = S.team.team;
  sheet(`<h3>👑 Change team owner</h3><div class="sub">${esc(t.name)} now belongs to <b>${esc(t.owner_name || 'nobody')}</b>. Search for the new owner by name, username or full mobile number.</div>
    <input id="own-q" class="input" placeholder="Search players…" autocomplete="off" oninput="App.ownerSearch(this.value)">
    <label class="small" style="display:flex;gap:8px;align-items:center;margin:10px 2px"><input type="checkbox" id="own-keep" checked> Let ${esc(t.owner_name || 'the old owner')} keep using this team</label>
    <div class="plist" id="own-list"><div class="small muted">Type at least 2 letters</div></div>`);
  setTimeout(() => { const i = $('#own-q'); if (i) i.focus(); }, 50);
};
App.ownerSearch = q => {
  clearTimeout(App._ownT);
  App._ownT = setTimeout(async () => {
    const box = $('#own-list'); if (!box) return;
    if (q.trim().length < 2) { box.innerHTML = '<div class="small muted">Type at least 2 letters</div>'; return; }
    try {
      const d = await api('users_search', { q: q.trim() });
      App._ownUsers = d.users;
      box.innerHTML = d.users.length ? d.users.map(x => `<button class="pbtn ${+x.id === +S.team.team.owner_id ? 'on' : ''}" onclick="App.setOwner(${x.id})"><span class="chk">${+x.id === +S.team.team.owner_id ? '✓' : ''}</span>${esc(x.display_name)}<span class="s">@${esc(x.username)}</span></button>`).join('') : '<div class="small muted">No players found</div>';
    } catch (e) { box.innerHTML = `<div class="small muted">${esc(e.message || 'Search failed')}</div>`; }
  }, 300);
};
App.setOwner = uid => {
  const t = S.team.team, x = (App._ownUsers || []).find(y => +y.id === +uid);
  const keep = !!($('#own-keep') || {}).checked;
  confirmSheet('Hand over ' + t.name + '?', `${x ? x.display_name : 'This player'} becomes the owner and can edit the team, its players and who may use it.${t.owner_name ? ' ' + t.owner_name + (keep ? ' can still use the team.' : ' loses access.') : ''}`, 'Change owner', () => run(async () => {
    const r = await api('team_owner_set', { id: t.id, user_id: uid, drop_old: keep ? 0 : 1 });
    closeSheet(); toast(`${r.owner_name} now owns ${t.name} ✓`); render();
  }), false);
};
App.setCaptain = pid => run(async () => { const r = await api('team_captain', { id: S.team.team.id, player_id: pid }); closeSheet(); toast(r.captain ? `${r.captain} is the captain ✓` : 'Captain removed'); render(); });

/* ---------- Who can play with a custom team ---------- */
function teamAccessCard(t) {
  if (t.category !== 'custom') return '';
  if (t.can_edit && (t.mine || t.owner_id == S.user.id || t.access)) {
    const acc = t.access || [], pending = acc.filter(a => a.status === 'pending'), allowed = acc.filter(a => a.status === 'allowed');
    const person = (a, btns) => `<div class="item">${personAvatar({ avatar: a.avatar, avatar_preset: a.avatar_preset, display_name: a.display_name }, 36)}<div class="grow"><div class="t ellipsis">${esc(a.display_name)}</div><div class="s">@${esc(a.username)}</div></div>${btns}</div>`;
    return `<div class="card access-card"><b>🔐 Who can play with this team?</b>
      <div class="seg" style="margin:10px 0 6px">${[['approved', 'Only people I allow'], ['everyone', 'Everyone']].map(([k, l]) => `<button class="${t.share_mode === k ? 'on' : ''}" onclick="App.shareMode(${t.id},'${k}')">${l}</button>`).join('')}</div>
      <div class="small muted">${t.share_mode === 'everyone' ? 'Any player can pick this team for their matches and tournaments.' : 'Other players can see your team, but only you and the players below can pick it for matches and tournaments.'}</div>
      ${pending.length ? `<div class="section" style="margin-top:12px"><h2>🔔 Asking to play</h2></div><div class="flush list">${pending.map(a => person(a, `<button class="btn sm" onclick="App.teamAllow(${t.id},${a.user_id},1)">Allow</button><button class="btn sm light" onclick="App.teamAllow(${t.id},${a.user_id},0)">Decline</button>`)).join('')}</div>` : ''}
      ${t.share_mode !== 'everyone' ? `<div class="section" style="margin-top:12px"><h2>Allowed players</h2><span class="small muted">${allowed.length}</span></div>
        ${allowed.length ? `<div class="flush list">${allowed.map(a => person(a, `<button class="btn sm light" onclick="App.teamAllow(${t.id},${a.user_id},0)">Remove</button>`)).join('')}</div>` : '<div class="small muted">Nobody yet - only you can use this team.</div>'}
        <form class="row" style="margin-top:10px" onsubmit="App.teamAdd(event,${t.id})"><input class="input grow" name="u" placeholder="Add by username, e.g. aayush" autocapitalize="none" autocomplete="off" required><button class="btn sm">Add</button></form>` : ''}
    </div>`;
  }
  if (t.can_use) return t.my_access === 'allowed' ? `<div class="card access-card ok">✅ <b>${esc(t.owner_name || 'The owner')}</b> lets you play with this team.</div>` : '';
  if (t.my_access === 'pending') return `<div class="card access-card">⏳ <b>Request sent.</b> <span class="small muted">You can pick this team once ${esc(t.owner_name || 'the owner')} says yes.</span>
      <button class="btn sm light block" style="margin-top:8px" onclick="App.teamAsk(${t.id},0)">Cancel request</button></div>`;
  return `<div class="card access-card">🔒 <b>This is ${esc(t.owner_name || 'another player')}'s team.</b><div class="small muted" style="margin:4px 0 10px">You can look, but you need their OK to use it in your matches.</div>
      <button class="btn block" onclick="App.teamAsk(${t.id},1)">🙋 Ask to use this team</button></div>`;
}
const teamAccessDo = (action, data, msg) => run(async () => { await api(action, data); if (msg) toast(msg); render(); });
App.teamAsk = (id, on) => teamAccessDo(on ? 'team_access_request' : 'team_access_cancel', { team_id: id }, on ? 'Asked ✓ - you\'ll be able to use it once they say yes' : 'Request cancelled');
App.teamAllow = (id, uid, allow) => teamAccessDo('team_access_set', { team_id: id, user_id: uid, allow }, allow ? 'Allowed ✓' : 'Done');
App.shareMode = (id, mode) => teamAccessDo('team_share_mode', { team_id: id, mode }, 'Saved ✓');
App.teamAdd = (e, id) => { e.preventDefault(); const u = formData(e.target).u.trim(); teamAccessDo('team_access_add', { team_id: id, username: u }, 'Added ✓'); };

const BAT_STYLES = ['Right-hand bat', 'Left-hand bat'];
const BOWL_STYLES = ['', 'Right-arm fast', 'Right-arm fast-medium', 'Right-arm medium', 'Left-arm fast', 'Left-arm fast-medium', 'Left-arm medium', 'Right-arm offbreak', 'Right-arm legbreak', 'Slow left-arm orthodox', 'Left-arm wrist-spin'];
App.playerForm = (pid, teamId) => {
  const p = pid ? (S.team?.players.find(x => +x.id === pid) || S.player?.player) : null;
  const tid = teamId || S.team?.team.id;
  const opt = (list, v) => list.map(x => `<option value="${esc(x)}" ${x === (v || '') ? 'selected' : ''}>${x || "Doesn't bowl"}</option>`).join('');
  sheet(`<h3>${p ? 'Edit player' : 'Add player'}</h3><form onsubmit="App.savePlayer(event,${pid || 0},${tid})">
    <div class="field"><label>Name</label><input class="input" name="name" value="${esc(p?.name || '')}" required></div>
    <div class="grid2"><div class="field"><label>Role</label><select class="input" name="role">${Object.keys(ROLE_ICON).map(r => `<option ${p?.role === r ? 'selected' : ''}>${r}</option>`).join('')}</select></div>
    <div class="field"><label>Jersey no.</label><input class="input" name="jersey_no" inputmode="numeric" value="${esc(p?.jersey_no || '')}"></div></div>
    <div class="field"><label>Batting</label><select class="input" name="batting_style">${opt(BAT_STYLES, p?.batting_style || 'Right-hand bat')}</select></div>
    <div class="field"><label>Bowling</label><select class="input" name="bowling_style">${opt(BOWL_STYLES, p?.bowling_style)}</select></div>
    <div class="field"><label>Country</label><input class="input" name="country" value="${esc(p?.country || 'India')}"></div>
    <div class="field"><label>WhatsApp number <span class="muted">(optional)</span></label><input class="input" name="mobile" inputmode="tel" value="${esc(p?.mobile || '')}" placeholder="98765 43210">
      <div class="small muted" style="margin-top:4px">When this player joins Cric-Scorer with this number, all their matches here become their own stats.</div></div>
    <button class="btn block">${p ? 'Save changes' : 'Add player'}</button>
    ${p ? `<button type="button" class="btn light block" style="margin-top:8px;color:var(--red)" onclick="App.deletePlayer(${pid})">Delete player</button>` : ''}</form>`);
};
App.savePlayer = (e, pid, tid) => {
  e.preventDefault();
  run(async () => { const f = formData(e.target); f.id = pid; f.team_id = tid; const r = await api('player_save', f); closeSheet(); toast(r.linked_to ? `Linked to ${r.linked_to.name}'s account ✓` : pid ? 'Player updated' : 'Player added ✓'); render(); }, e.submitter);
};
App.deletePlayer = pid => confirmSheet('Delete player?', 'This removes the player from the team.', 'Delete', () => run(async () => {
  await api('player_delete', { id: pid }); closeSheet(); toast('Player deleted');
  if (location.hash.startsWith('#/player/')) go('#/team/' + S.player.player.team_id); else render();
}));
App.pickPlayers = () => {
  S.pickSel = new Set();
  sheet(`<h3>Add players</h3><div class="sub">Search players who use the app (by name, username or WhatsApp number) or copy stars from IPL & international teams.</div>
    <div class="search">${I.search}<input class="input" id="pq" placeholder="Name, username, number, or e.g. Kohli" oninput="App.searchPlayers(this.value)"></div>
    <div id="pres" class="plist"><div class="muted small">Type at least 2 letters…</div></div>
    <button class="btn block" id="padd" disabled onclick="App.copyPlayers(this)">Add players</button>`);
  setTimeout(() => $('#pq')?.focus(), 250);
};
let searchT;
App.searchPlayers = q => {
  clearTimeout(searchT);
  searchT = setTimeout(async () => {
    if (q.trim().length < 2) return;
    try {
      const d = await api('players_search', { q, exclude_team: S.team.team.id });
      S.pickList = d.players; S.pickAccounts = d.accounts || []; drawPick();
    } catch (e) { toast(e.message, true); }
  }, 250);
};
function drawPick() {
  const acc = (S.pickAccounts || []).map(a => `<button class="pbtn ${S.pickSel.has('u' + a.id) ? 'on' : ''}" onclick="App.togglePick('u${a.id}')"><span class="chk">${S.pickSel.has('u' + a.id) ? '✓' : ''}</span>👤 ${esc(a.display_name)}${a.verified ? ' ✅' : ''}<span class="s">@${esc(a.username)} · app player</span></button>`).join('');
  $('#pres').innerHTML = acc + (S.pickList.length ? S.pickList.map(p => +p.can_use === 0 ? `<button class="pbtn locked" onclick="toast('🔒 ${esc(p.team_name).replace(/'/g, '')} is another player\\'s team - ask them first', true)"><span class="chk">🔒</span>${roleIcon(p.role)} ${esc(p.name)}<span class="s">${esc(p.team)}</span></button>`
    : `<button class="pbtn ${S.pickSel.has(p.id) ? 'on' : ''}" onclick="App.togglePick(${p.id})"><span class="chk">${S.pickSel.has(p.id) ? '✓' : ''}</span>${roleIcon(p.role)} ${esc(p.name)}<span class="s">${esc(p.team)}</span></button>`).join('') : (acc ? '' : '<div class="muted small">No players found.</div>'));
  const b = $('#padd'); b.disabled = !S.pickSel.size; b.textContent = S.pickSel.size ? `Add ${plural(S.pickSel.size, 'player')}` : 'Add players';
}
App.togglePick = id => { S.pickSel.has(id) ? S.pickSel.delete(id) : S.pickSel.add(id); drawPick(); };
App.copyPlayers = btn => run(async () => {
  const sel = [...S.pickSel], user_ids = sel.filter(x => String(x).startsWith('u')).map(x => +String(x).slice(1)), player_ids = sel.filter(x => !String(x).startsWith('u'));
  const r = await api('player_copy', { team_id: S.team.team.id, player_ids, user_ids }); closeSheet(); toast(`${plural(r.added, 'player')} added ✓`); render();
}, btn);

const COLORS = ['#0d4a36', '#15803d', '#1d4ed8', '#0ea5e9', '#7c3aed', '#db2777', '#d62839', '#ea580c', '#f2b134', '#475569', '#111827', '#0f766e'];
async function viewTeamForm(id) {
  let t = { name: '', short_name: '', color: COLORS[Math.floor(Math.random() * COLORS.length)], captain: '', home_ground: '' };
  if (id) { const d = await api('team_get', { id: +id }); t = d.team; S.team = d; if (!t.can_edit) return go('#/team/' + id); }
  mount(topbar(id ? 'Edit Team' : 'Create Team', { back: true }) + `<form class="content" onsubmit="App.saveTeam(event,${id || 0})">
    <div class="card" style="text-align:center"><span id="tprev">${badge(t, 'lg')}</span><div class="small muted" style="margin-top:6px">Your team badge</div></div>
    <div class="card">
      <div class="field"><label>Team name</label><input class="input" name="name" value="${esc(t.name)}" placeholder="e.g. Aarush Super Kings" required oninput="App.teamPrev()"></div>
      <div class="field"><label>Short name (2–4 letters)</label><input class="input" name="short_name" maxlength="4" value="${esc(t.short_name)}" placeholder="e.g. ASK" style="text-transform:uppercase" oninput="App.teamPrev()"></div>
      <div class="field"><label>Team colour</label><div class="swatches">${COLORS.map(c => `<span class="swatch ${c === t.color ? 'on' : ''}" data-c="${c}" style="background:${c}" onclick="App.pickColor('${c}')"></span>`).join('')}
        <input type="color" name="color" value="${esc(t.color)}" style="width:34px;height:34px;border:0;padding:0;background:none" oninput="App.teamPrev()"></div></div>
      <div class="field"><label>Captain</label>${id && S.team && S.team.team.id === +id && S.team.players.length ? `<select class="input" name="captain"><option value="">- none -</option>${S.team.players.map(p => `<option ${t.captain === p.name ? 'selected' : ''}>${esc(p.name)}</option>`).join('')}</select>` : `<input class="input" name="captain" value="${esc(t.captain || '')}" placeholder="optional - or pick one on the team page">`}</div>
      <div class="field"><label>Home ground</label><input class="input" name="home_ground" value="${esc(t.home_ground || '')}" placeholder="optional"></div>
    </div>
    <button class="btn block">${id ? 'Save team' : 'Create team →'}</button>
    ${id ? `<button type="button" class="btn light block" style="margin:10px 0 20px;color:var(--red)" onclick="App.deleteTeam(${id})">Delete team</button>` : ''}
  </form>`, 'teams');
}
App.pickColor = c => { const f = $('form.content'); f.color.value = c; document.querySelectorAll('.swatch').forEach(x => x.classList.toggle('on', x.dataset.c === c)); App.teamPrev(); };
App.teamPrev = () => { const f = $('form.content'); $('#tprev').innerHTML = badge({ name: f.name.value || '?', short_name: (f.short_name.value || f.name.value.replace(/[^A-Za-z]/g, '').slice(0, 3)).toUpperCase(), color: f.color.value }, 'lg'); };
App.saveTeam = (e, id) => {
  e.preventDefault();
  run(async () => { const f = formData(e.target); f.id = id; const r = await api('team_save', f); toast(id ? 'Team saved' : 'Team created! Now add players 🧢'); go('#/team/' + r.id); }, e.submitter);
};
App.deleteTeam = id => confirmSheet('Delete team?', 'The team and its players will be removed.', 'Delete', () => run(async () => { await api('team_delete', { id }); closeSheet(); toast('Team deleted'); go('#/teams'); }));

async function viewPlayer(id) {
  mount(topbar('Player', { back: true }) + `<div class="content">${sk(4)}</div>`, 'teams');
  const d = await api('player_get', { id: +id }); S.player = d;
  const p = d.player, b = d.stats.batting, w = d.stats.bowling, fg = textOn(p.color);
  const g = (v, l) => `<div><b>${v ?? '–'}</b><span>${l}</span></div>`;
  const actions = p.can_edit ? `<button class="icon-btn" aria-label="Edit" onclick="App.playerForm(${p.id},${p.team_id})">${I.edit}</button>` : '';
  mount(topbar(p.name, { back: true, actions }) + `<div class="team-hero" style="background:${esc(p.color)};color:${fg}">
      <span class="avatar" style="width:64px;height:64px;font-size:24px;background:rgba(255,255,255,.2);color:${fg}">${esc(p.name.split(' ').map(x => x[0]).slice(0, 2).join(''))}</span>
      <div><h2>${esc(p.name)}</h2><p>${roleIcon(p.role)} ${esc(p.role)}${p.jersey_no ? ' · #' + esc(p.jersey_no) : ''}</p>
      <p onclick="go('#/team/${p.team_id}')" style="cursor:pointer;text-decoration:underline">${esc(p.team_name)}</p></div></div>
    <div class="content">
      <div class="card flush"><div class="kv" style="border-top:0"><span>Batting</span><span>${esc(p.batting_style || '-')}</span></div>
        <div class="kv"><span>Bowling</span><span>${esc(p.bowling_style || '-')}</span></div>${p.country ? `<div class="kv"><span>Country</span><span>${esc(p.country)}</span></div>` : ''}</div>
      <div class="section"><h2>🏏 Batting stats</h2><span class="small muted">in Cric-Scorer matches</span></div>
      <div class="card flush"><div class="statgrid">${g(b.mat, 'Matches')}${g(b.runs, 'Runs')}${g(b.hs + (b.hs_no ? '*' : ''), 'Best')}${g(b.avg, 'Average')}${g(b.sr, 'Strike rate')}${g(b.inns, 'Innings')}${g(b.f4, 'Fours')}${g(b.f6, 'Sixes')}${g(b.fifties + b.hundreds, '50s / 100s')}</div></div>
      <div class="section"><h2>🎯 Bowling stats</h2></div>
      <div class="card flush"><div class="statgrid">${g(w.wkts, 'Wickets')}${g(w.balls ? Math.floor(w.balls / 6) + '.' + (w.balls % 6) : 0, 'Overs')}${g(w.inns ? w.best_w + '/' + w.best_r : null, 'Best')}${g(w.econ, 'Economy')}${g(w.avg, 'Average')}${g(d.stats.catches, 'Catches')}</div></div>
      ${d.stats.pom ? `<div class="card row"><span style="font-size:28px">⭐</span><b>${plural(d.stats.pom, 'Player of the Match award')}</b></div>` : ''}
      ${d.recent.length ? `<div class="section"><h2>Recent innings</h2></div><div class="card flush list">${d.recent.map(r => `<div class="item" onclick="go('#/match/${r.match_id}')"><div class="grow"><div class="t">${esc(r.t1)} vs ${esc(r.t2)}</div><div class="s">${esc(r.title || '')} ${esc(fmtDate(r.match_date, false))}</div></div><b class="big-num">${r.runs}${r.out_ ? '' : '*'}</b><span class="muted small">(${r.balls})</span></div>`).join('')}</div>` : ''}
    </div>`, 'teams');
}

/* =====================================================================
   TOURNAMENTS
   ===================================================================== */
const FORMAT = { league: 'League (round robin)', knockout: 'Knockout', league_final: 'League + playoffs' };
const PO_FORMAT = { ipl: 'IPL style - Qualifier 1 (1v2), Eliminator (3v4), Qualifier 2, Final', semis: 'Semi-finals (1v4, 2v3) + Final', final: 'Final only (1 v 2)' };
const STAGE = { q1: 'Qualifier 1', elim: 'Eliminator', q2: 'Qualifier 2', sf1: 'Semi-final 1', sf2: 'Semi-final 2', final: 'Final' };
function playoffCard(po) {
  po = po || { format: 'ipl' };
  const n = (name, v, ph) => `<input class="input" type="number" inputmode="numeric" name="${name}" min="0" max="100" placeholder="${ph}" value="${v ?? ''}">`;
  return `<div class="card" id="pocard"><b>🏆 Playoffs</b><div class="small muted" style="margin:2px 0 10px">Who goes through from the league table, and how the knockout games are played. Club tournaments run playoffs per category.</div>
    <div class="field"><label>Playoff format</label><select class="input" name="po_format">${Object.entries(PO_FORMAT).map(([k, l]) => `<option value="${k}" ${po.format === k ? 'selected' : ''}>${l}</option>`).join('')}</select></div>
    <div class="grid2"><div class="field"><label>Qualifier / semi overs</label>${n('po_overs', po.po_overs, 'same as league')}</div><div class="field"><label>Max overs a bowler</label>${n('po_max_bowler', po.po_max_bowler, 'same as league')}</div></div>
    <div class="grid2"><div class="field"><label>Final overs</label>${n('final_overs', po.final_overs, 'same as league')}</div><div class="field"><label>Max overs a bowler</label>${n('final_max_bowler', po.final_max_bowler, 'same as league')}</div></div>
    <div class="small muted">League table order: points, then net run rate, then wickets taken. A playoff that ends level (tied super over, or no result) sends the higher-placed team through.</div></div>`;
}
App.tFormat = v => { const c = $('#pocard'); if (c) c.style.display = v === 'league_final' ? '' : 'none'; };
const stPill = s => s === 'ongoing' ? '<span class="pill live">Ongoing</span>' : s === 'completed' ? '<span class="pill done">Completed</span>' : '<span class="pill up">Upcoming</span>';
async function viewTournaments() {
  mount(topbar('Tournaments') + `<div class="content">${sk(3)}</div><button class="fab" aria-label="New tournament" onclick="go('#/tournament/new')">${I.plus}</button>`, 'tournaments');
  const d = await api('tournaments_list');
  mount(topbar('Tournaments') + `<div class="content">${d.tournaments.length ? d.tournaments.map(t => `<div class="card" style="cursor:pointer" onclick="go('#/tournament/${t.id}')">
      <div class="row"><span style="font-size:30px">🏆</span><div class="grow"><b style="font-size:17px">${esc(t.name)}</b><div class="small muted">${t.club_name ? '🏟️ ' + esc(t.club_name) + ' · ' : ''}${FORMAT[t.format]} · ${esc(t.type_name || 'Custom')} · ${plural(+t.teams, 'team')}</div></div>${stPill(t.state)}</div>
      <div class="progress"><i style="width:${t.matches ? Math.round(t.done / t.matches * 100) : 0}%"></i></div>
      <div class="small muted" style="margin-top:6px">${t.done}/${t.matches} matches played${t.start_date ? ' · starts ' + esc(fmtDate(t.start_date, false)) : ''}${t.mine ? '' : ' · by ' + esc(t.owner_name)}</div></div>`).join('')
    : `<div class="card empty"><div class="big">🏆</div>No tournaments yet.<br>Create your own league like the IPL!<br><button class="btn" style="margin-top:12px" onclick="go('#/tournament/new')">Create tournament</button></div>`}</div>
    <button class="fab" aria-label="New tournament" onclick="go('#/tournament/new')">${I.plus}</button>`, 'tournaments');
}
async function viewTournamentForm(id) {
  mount(topbar(id ? 'Edit Tournament' : 'New Tournament', { back: true }) + `<div class="content">${sk(4)}</div>`, 'tournaments');
  const [ty, tm] = await Promise.all([api('types_list'), api('teams_list')]);
  let t = { name: '', format: 'league_final', playoffs: { format: 'ipl' }, match_type_id: (ty.types.find(x => x.name === 'T20') || ty.types[0]).id, start_date: new Date().toISOString().slice(0, 10), end_date: '', venue: '' };
  S.tsel = new Set();
  if (id) { const d = await api('tournament_get', { id: +id }); t = d.tournament; t.playoffs = d.playoffs; if (!t.can_edit) return go('#/tournament/' + id); d.teams.forEach(x => S.tsel.add(+x.id)); }
  S.tteams = tm.teams;
  mount(topbar(id ? 'Edit Tournament' : 'New Tournament', { back: true }) + `<form class="content" onsubmit="App.saveTournament(event,${id || 0})">
    <div class="card">
      <div class="field"><label>Tournament name</label><input class="input" name="name" value="${esc(t.name)}" placeholder="e.g. Diwali Premier League" required></div>
      <div class="field"><label>Format</label><select class="input" name="format" onchange="App.tFormat(this.value)">${Object.entries(FORMAT).map(([k, l]) => `<option value="${k}" ${t.format === k ? 'selected' : ''}>${l}</option>`).join('')}</select></div>
      <div class="field"><label>Match type</label><select class="input" name="match_type_id">${ty.types.map(x => `<option value="${x.id}" ${+t.match_type_id === x.id ? 'selected' : ''}>${esc(x.name)} - ${x.overs} overs, ${x.players_per_side} a side</option>`).join('')}</select></div>
      <div class="grid2"><div class="field"><label>Start date</label><input class="input" type="date" name="start_date" value="${esc(t.start_date || '')}"></div>
      <div class="field"><label>End date</label><input class="input" type="date" name="end_date" value="${esc(t.end_date || '')}"></div></div>
      <div class="field"><label>Venue</label><input class="input" name="venue" value="${esc(t.venue || '')}" placeholder="optional"></div>
    </div>
    ${playoffCard(t.playoffs && t.playoffs.format !== 'none' ? t.playoffs : null)}
    <div class="section"><h2>Teams</h2><span class="small muted" id="tcount">${S.tsel.size} selected</span></div>
    <div class="search">${I.search}<input class="input" placeholder="Search teams" oninput="App.filterList(this.value)"></div>
    <div class="plist">${tm.teams.map(x => { const locked = x.can_use === false && !S.tsel.has(x.id); return `<button type="button" class="pbtn ${S.tsel.has(x.id) ? 'on' : ''} ${locked ? 'locked' : ''}" data-q="${esc((x.name + ' ' + x.short_name + ' ' + x.category).toLowerCase())}" data-tid="${x.id}" onclick="${locked ? `App.lockedTeam(${x.id})` : `App.tToggle(${x.id},this)`}"><span class="chk">${locked ? '🔒' : S.tsel.has(x.id) ? '✓' : ''}</span>${badge(x, 'sm')} ${esc(x.name)}<span class="s">${locked ? (x.my_access === 'pending' ? 'Asked' : 'Ask owner') : { ipl: 'IPL', international: 'Intl', custom: 'Custom' }[x.category]}</span></button>`; }).join('')}</div>
    <button class="btn block" style="margin:8px 0 20px">${id ? 'Save tournament' : 'Create tournament →'}</button></form>`, 'tournaments');
  App.tFormat(t.format);
}
App.lockedTeam = id => {
  const t = (S.tteams || []).find(x => x.id === id) || {};
  confirmSheet(`🔒 ${t.name || 'This team'} is locked`, `It belongs to ${t.owner_name || 'another player'}. Ask them, and you can use it once they say yes.`, t.my_access === 'pending' ? 'Open team' : 'Ask to use it', () => {
    closeSheet();
    if (t.my_access === 'pending') return go('#/team/' + id);
    run(async () => { await api('team_access_request', { team_id: id }); t.my_access = 'pending'; toast(`Asked ${t.owner_name || 'the owner'} ✓`); });
  }, false);
};
App.tToggle = (id, el) => {
  S.tsel.has(id) ? S.tsel.delete(id) : S.tsel.add(id);
  el.classList.toggle('on', S.tsel.has(id)); el.querySelector('.chk').textContent = S.tsel.has(id) ? '✓' : '';
  $('#tcount').textContent = S.tsel.size + ' selected';
};
App.saveTournament = (e, id) => {
  e.preventDefault();
  if (S.tsel.size < 2) return toast('Pick at least 2 teams', true);
  run(async () => { const f = formData(e.target); f.id = id; f.team_ids = [...S.tsel];
    f.playoffs = { format: f.po_format, po_overs: f.po_overs, po_max_bowler: f.po_max_bowler, final_overs: f.final_overs, final_max_bowler: f.final_max_bowler }; const r = await api('tournament_save', f); toast(id ? 'Saved' : 'Tournament created! 🏆'); go('#/tournament/' + r.id); }, e.submitter);
};
async function viewTournament(id) {
  mount(topbar('Tournament', { back: true }) + `<div class="content">${sk(4)}</div>`, 'tournaments');
  const d = await api('tournament_get', { id: +id }); S.tour = d;
  const t = d.tournament;
  const tabs = [['matches', 'Matches'], ['points', 'Points'], ['leaders', 'Stats'], ['teams', 'Teams']];
  let body = '';
  if (S.tTab === 'points') {
    body = `<div class="card flush"><table class="tbl"><tr><th>Team</th><th>P</th><th>W</th><th>L</th><th>T/NR</th><th>Pts</th><th>NRR</th></tr>
      ${d.points.map((r, i) => `<tr onclick="go('#/team/${r.id}')"><td><div class="row" style="gap:8px"><span class="muted small">${i + 1}</span>${badge(r, 'sm')}<b>${esc(r.short_name)}</b></div></td><td>${r.p}</td><td>${r.w}</td><td>${r.l}</td><td>${r.t + r.nr}</td><td><b>${r.pts}</b></td><td>${r.nrr > 0 ? '+' : ''}${r.nrr.toFixed(3)}</td></tr>`).join('')}</table></div>
      <div class="small muted">Win = 2 pts, tie / no result = 1 pt. Level on points: net run rate (NRR) decides. Playoff matches don't count here.</div>`;
  } else if (S.tTab === 'leaders') {
    body = leaderBlock('🏏 Most runs', d.leaders.runs, r => r.runs, r => `${r.balls} balls · SR ${r.balls ? (r.runs * 100 / r.balls).toFixed(1) : 0}`)
      + leaderBlock('🎯 Most wickets', d.leaders.wickets, r => r.wkts, r => `${r.runs} runs · ${Math.floor(r.balls / 6)}.${r.balls % 6} ov`)
      + leaderBlock('💥 Most sixes', d.leaders.sixes, r => r.sixes, r => `${r.runs} runs`);
  } else if (S.tTab === 'teams') {
    body = `<div class="card flush list">${d.teams.map(x => `<div class="item" onclick="go('#/team/${x.id}')">${badge(x)}<div class="grow"><div class="t">${esc(x.name)}</div><div class="s">${plural(+x.players, 'player')}</div></div><span class="chev">${I.chev}</span></div>`).join('')}</div>`;
  } else {
    const po = d.playoffs || {}, leagueN = d.matches.filter(m => (m.stage || 'league') === 'league').length;
    const poCard = po.format && po.format !== 'none' ? `<div class="card" style="border-left:4px solid var(--lime);padding:10px 12px;margin-bottom:12px"><div class="row"><span style="font-size:24px">🏆</span><div class="grow"><b>Playoffs</b><div class="small muted">${esc(PO_FORMAT[po.format] || '')}${po.po_overs ? ` · ${po.po_overs} overs` : ''}${po.final_overs ? ` · final ${po.final_overs} overs` : ''}${po.po_max_bowler ? ` · max ${po.po_max_bowler} ov/bowler` : ''}</div></div>
      ${t.can_edit && !po.started && leagueN ? `<button class="btn sm" onclick="App.playoffs(this)">Create playoffs</button>` : po.started ? '<span class="small muted">under way</span>' : ''}</div></div>` : '';
    body = poCard + (t.can_edit ? `<div class="grid2" style="margin-bottom:12px">${d.matches.length ? '' : `<button class="btn" onclick="App.fixtures(this)">⚡ Auto fixtures</button>`}<button class="btn light" ${d.matches.length ? 'style="grid-column:1/-1"' : ''} onclick="go('#/match/new?t=${t.id}')">${I.plus} Add match</button></div>` : '')
      + (d.matches.length ? d.matches.map(matchCard).join('') : `<div class="card empty"><div class="big">📅</div>No matches yet.${t.can_edit ? `<br>Tap <b>Auto fixtures</b> to create ${t.format === 'knockout' ? 'round-1 knockout' : 'all league'} matches.` : ''}</div>`);
  }
  const actions = t.can_edit ? `<button class="icon-btn" aria-label="Options" onclick="App.tourMenu()">${I.dots}</button>` : '';
  mount(topbar(t.name, { back: true, actions, sub: FORMAT[t.format] }) + `<div class="board" style="padding:16px">
      <div class="row"><span style="font-size:40px">🏆</span><div class="grow"><b style="font-family:var(--display);font-size:24px">${esc(t.name)}</b>
      <div class="other" style="margin:0">${esc(t.type_name || 'Custom')} · ${t.overs || '?'} overs · ${plural(d.teams.length, 'team')}</div>
      <div class="other" style="margin:0">${t.start_date ? esc(fmtDate(t.start_date, false)) : ''}${t.end_date ? ' – ' + esc(fmtDate(t.end_date, false)) : ''}${t.venue ? ' · ' + esc(t.venue) : ''}</div></div>${stPill(t.state)}</div>
      <div class="progress" style="background:rgba(255,255,255,.15)"><i style="background:var(--lime);width:${t.matches ? Math.round(t.done / t.matches * 100) : 0}%"></i></div>
      <div class="other">${t.done}/${t.matches} matches played</div></div>
    <div class="content"><div class="seg">${tabs.map(([k, l]) => `<button class="${S.tTab === k ? 'on' : ''}" onclick="S.tTab='${k}';render()">${l}</button>`).join('')}</div>${body}</div>`, 'tournaments');
}
function leaderBlock(title, list, val, sub) {
  return `<div class="section"><h2>${title}</h2></div>` + (list.length ? `<div class="card flush list">${list.map((r, i) => `<div class="item" onclick="go('#/player/${r.pid}')"><span class="rank">${['🥇', '🥈', '🥉'][i] || i + 1}</span>${badge({ short_name: r.team, color: r.color }, 'sm')}
    <div class="grow"><div class="t ellipsis">${esc(r.name)}</div><div class="s">${sub(r)}</div></div><b class="big-num">${val(r)}</b></div>`).join('')}</div>` : '<div class="card empty small">No data yet - play some matches!</div>');
}
App.playoffs = (btn, force = 0) => run(async () => {
  const r = await api('tournament_playoffs', { id: S.tour.tournament.id, force });
  if (r.confirm) return confirmSheet('League not finished', r.confirm, 'Create playoffs', () => { closeSheet(); App.playoffs(null, 1); }, false);
  toast(`${plural(r.created, 'playoff match')} created 🏆`); render();
}, btn);
App.fixtures = btn => run(async () => { const r = await api('tournament_fixtures', { id: S.tour.tournament.id }); toast(`${plural(r.created, 'match')} created ✓`); render(); }, btn);
App.tourMenu = () => sheet(`<h3>Tournament options</h3><div class="plist">
  <button class="pbtn" onclick="go('#/tournament/${S.tour.tournament.id}/edit')">✏️ Edit tournament & teams</button>
  <button class="pbtn" onclick="go('#/match/new?t=${S.tour.tournament.id}')">➕ Add a match (e.g. Final)</button>
  <button class="pbtn" style="color:var(--red)" onclick="App.deleteTour()">🗑️ Delete tournament</button></div>`);
App.deleteTour = () => confirmSheet('Delete tournament?', 'The tournament and ALL its matches will be deleted.', 'Delete', () => run(async () => { await api('tournament_delete', { id: S.tour.tournament.id, with_matches: 1 }); closeSheet(); toast('Tournament deleted'); go('#/tournaments'); }));

/* =====================================================================
   STATS, MORE, MATCH TYPES, PROFILE
   ===================================================================== */
async function viewStats() {
  const tabs = [['runs', 'Runs'], ['wickets', 'Wickets'], ['sixes', 'Sixes'], ['fours', 'Fours']];
  mount(topbar('Leaderboard', { back: true }) + `<div class="content"><div class="seg">${tabs.map(([k, l]) => `<button class="${S.statTab === k ? 'on' : ''}" onclick="S.statTab='${k}';render()">${l}</button>`).join('')}</div><div id="lb">${sk(4)}</div></div>`, 'more');
  const d = await api('leaderboards');
  const cfg = {
    runs: ['🏏 Top run scorers', r => r.runs, r => `${plural(r.mats, 'match')} · ${r.balls} balls · SR ${r.balls ? (r.runs * 100 / r.balls).toFixed(1) : 0}`],
    wickets: ['🎯 Top wicket takers', r => r.wkts, r => `${plural(r.mats, 'match')} · ${r.runs} runs · econ ${r.balls ? (r.runs * 6 / r.balls).toFixed(2) : 0}`],
    sixes: ['💥 Six machines', r => r.sixes, r => `${r.runs} runs`],
    fours: ['⚡ Boundary hitters', r => r.fours, r => `${r.runs} runs`],
  }[S.statTab];
  $('#lb').innerHTML = leaderBlock(cfg[0], d[S.statTab], cfg[1], cfg[2]);
}
function viewMore() {
  const u = S.user;
  const item = (ic, t, s, fn) => `<div class="item" onclick="${fn}"><span class="tile ic" style="box-shadow:none;padding:0;width:38px;height:38px;justify-content:center">${ic}</span><div class="grow"><div class="t">${t}</div>${s ? `<div class="s">${s}</div>` : ''}</div><span class="chev">${I.chev}</span></div>`;
  mount(topbar('More') + `<div class="content">
    <div class="card row" style="cursor:pointer" onclick="go('#/user/${u.id}')">${personAvatar(u, 52)}
      <div class="grow"><b style="font-size:18px">${esc(u.display_name)}</b><div class="small muted">@${esc(u.username)}${u.is_admin ? ' · Admin' : ''}${u.google ? ' · Google ✓' : ''}</div></div>
      <button class="btn sm light" onclick="event.stopPropagation();go('#/profile')">Edit</button></div>
    <div class="card flush list">
      ${u.is_admin ? item('🛠️', 'Admin portal', 'Stats, roll-out, updates, help desk', "go('#/admin')") : ''}
      ${u.features && u.features.auction ? item('🔨', 'APL Auction', 'Aaryush Premier League - bid for IPL stars with friends', "go('#/apl')") : ''}
      ${u.features && u.features.game ? item('🎮', 'Gully Duel', 'Bat vs bowl - two phones, one phone or the computer', "go('#/play')") : ''}
      ${item('👥', 'Find players', 'Search, follow, and see your followers', "go('#/people')")}
      ${item('📊', 'Leaderboard', 'Top run scorers & wicket takers', "go('#/stats')")}
      ${item('⚙️', 'Match types', 'T20, ODI, Gully cricket & your own', "go('#/types')")}
      ${item('🎓', 'Cricket academies', (u.academies && u.academies.length) ? 'You train at ' + esc(u.academies.map(a => a.name).join(', ')) : 'Find an academy, or register yours', "go('#/academies')")}
      ${item('🧢', 'Our teams' + (S.extras && S.extras.team_requests ? ' <span class="dot-new"></span>' : ''), S.extras && S.extras.team_requests ? `${plural(S.extras.team_requests, 'player')} asking to use your teams` : 'Teams you and your friends made', "go('#/teams?c=custom')")}
      ${item('🔊', 'Sound & commentary', (Sound.sfx ? 'Sounds on' : 'Sounds off') + ' · ' + (Sound.voice ? 'voice on' : 'voice muted'), 'App.soundSheet()')}
      ${item('💌', 'Invite friends', 'Share Cric-Scorer on WhatsApp & more', 'App.inviteSheet()')}
      ${item('📲', 'Install app', 'Add Cric-Scorer to your home screen', 'App.installHelp()')}
      ${item('🔄', 'Update app', 'Version ' + esc(window.CS_VERSION || '') + (S.newVersion ? ' · new version ' + esc(S.newVersion) + ' available!' : ' · reload the latest code'), 'App.updateApp()')}
      ${item('ℹ️', 'About Cric-Scorer', 'How scoring works', 'App.about()')}
      ${item('💬', 'Help & feedback' + (S.extras && S.extras.help_unread ? ' <span class="dot-new"></span>' : ''), S.extras && S.extras.help_unread ? 'You have a new reply!' : 'Ask a question, report a problem or share an idea', "go('#/help')")}
      ${item('🔐', 'Privacy Policy', 'What we keep and your rights', "location.href='privacy'")}
      ${item('📜', 'Terms of Use', 'The rules for using Cric-Scorer', "location.href='terms'")}
    </div>
    <button class="btn light block" style="color:var(--red)" onclick="App.logout()">Log out</button>
    <p class="small muted" style="text-align:center;margin-top:18px">Cric-Scorer · by Aaryush 🏏</p></div>`, 'more');
}
/* ---------- Invite friends: a ready-made message to share ---------- */
const INVITES = [
  { k: 'fun', t: '🎉 Fun', msg: n => `🏏 Hey! I'm using Cric-Scorer to score our cricket matches ball by ball - live scores, commentary, tournaments and even a cricket academy for kids. It's free, no download needed!\n\nJoin me 👉 ${SITE}` },
  { k: 'team', t: '🧢 For my team', msg: n => `🏏 Team, let's keep proper scores this season! I've set us up on Cric-Scorer - live scorecards, points tables, batting & bowling stats for everyone. Free and works on any phone.\n\nCreate your account 👉 ${SITE}` },
  { k: 'kids', t: '🎓 For parents', msg: n => `🏏 Found a brilliant free app for kids who love cricket: Cric-Scorer. They can score real matches, learn the rules with quizzes and field-position lessons, and play a bat-vs-bowl game with friends. Safe, no ads.\n\nTry it 👉 ${SITE}` },
  { k: 'short', t: '⚡ Short', msg: n => `Score your cricket matches live, for free 🏏 ${SITE}` },
];
const SITE = (typeof CS_SITE_URL === 'string' && CS_SITE_URL) || location.origin + location.pathname.replace(/[^/]*$/, '');
App.inviteSheet = (k = 'fun') => {
  const inv = INVITES.find(x => x.k === k) || INVITES[0], msg = inv.msg(S.user ? S.user.display_name : '');
  S.inviteK = k;
  sheet(`<h3>💌 Invite friends</h3><div class="sub">Pick a message, then share it anywhere - WhatsApp, Instagram, SMS…</div>
    <div class="chips wrap" style="margin-bottom:10px">${INVITES.map(x => `<button type="button" class="chip ${x.k === k ? 'on' : ''}" onclick="App.inviteSheet('${x.k}')">${x.t}</button>`).join('')}</div>
    <div class="card invite-preview"><div class="small" style="white-space:pre-wrap">${esc(msg)}</div></div>
    <div class="grid2"><button class="btn" style="background:#25d366" onclick="App.inviteWa()">WhatsApp</button><button class="btn" onclick="App.inviteShare()">📤 Share…</button></div>
    <button class="btn light block" style="margin-top:8px" onclick="App.inviteCopy()">📋 Copy message</button>`);
};
const inviteMsg = () => (INVITES.find(x => x.k === S.inviteK) || INVITES[0]).msg(S.user ? S.user.display_name : '');
App.inviteWa = () => { window.open('https://wa.me/?text=' + encodeURIComponent(inviteMsg()), '_blank', 'noopener'); };
App.inviteShare = async () => {
  const text = inviteMsg();
  if (navigator.share) { try { await navigator.share({ title: 'Cric-Scorer', text }); } catch (e) { /* cancelled */ } }
  else App.inviteCopy();
};
App.inviteCopy = async () => { try { await navigator.clipboard.writeText(inviteMsg()); toast('Message copied - paste it anywhere ✓'); } catch (e) { toast('Could not copy - long-press the message to select it', true); } };
App.about = () => sheet(`<div style="text-align:center">${LOGO.replace('class="logo"', 'style="width:64px;height:64px"')}<h3>Cric-Scorer</h3><div class="sub">by Aaryush · made for Aarush & Aayush</div></div>
  <div class="card" style="box-shadow:none;border:1px solid var(--line)"><b>Scoring tips</b><div class="small muted" style="margin-top:6px">
  • Tap <b>0–6</b> for runs off the bat.<br>• <b>WD</b> wide and <b>NB</b> no-ball add 1 run and don't count as a ball.<br>• <b>BYE/LB</b> are runs not off the bat.<br>• <b>OUT</b> records a wicket - pick how and who.<br>• Made a mistake? Tap <b>Undo</b> as many times as you need.<br>• Share the match link so family can watch the score live!</div></div>`);
async function viewTypes() {
  mount(topbar('Match Types', { back: true }) + `<div class="content">${sk(3)}</div>`, 'more');
  const d = await api('types_list'); S.types = d.types;
  mount(topbar('Match Types', { back: true }) + `<div class="content">${d.types.map(t => `<div class="card row" ${t.is_system && !S.user.is_admin ? '' : `style="cursor:pointer" onclick="App.typeForm(${t.id})"`}>
      <span class="badge" style="background:var(--pitch);color:#fff">${t.overs}</span><div class="grow"><b>${esc(t.name)}</b>${t.is_system ? ' <span class="role-tag">built-in</span>' : ''}
      <div class="small muted">${t.overs} overs · ${t.players_per_side} a side · ${t.max_overs_bowler ? t.max_overs_bowler + ' ov/bowler' + (+t.extra_bowlers ? ` (${t.extra_bowlers} may bowl ${t.extra_overs})` : '') : 'no bowling limit'}${t.last_man_stands ? ' · last man stands' : ''}</div></div></div>`).join('')}</div>
    <button class="fab" aria-label="New match type" onclick="App.typeForm(0)">${I.plus}</button>`, 'more');
}
App.typeForm = id => {
  const t = S.types.find(x => x.id === id) || { name: '', overs: 8, balls_per_over: 6, players_per_side: 8, max_overs_bowler: 0, last_man_stands: 0, bouncers_per_over: 1, description: '' };
  sheet(`<h3>${id ? 'Edit match type' : 'New match type'}</h3><form onsubmit="App.saveType(event,${id})">
    <div class="field"><label>Name</label><input class="input" name="name" value="${esc(t.name)}" placeholder="e.g. Terrace cricket" required></div>
    <div class="grid3"><div class="field"><label>Overs</label><input class="input" type="number" name="overs" min="1" max="100" value="${t.overs}"></div>
    <div class="field"><label>Balls/over</label><input class="input" type="number" name="balls_per_over" min="1" max="10" value="${t.balls_per_over}"></div>
    <div class="field"><label>Players</label><input class="input" type="number" name="players_per_side" min="1" max="15" value="${t.players_per_side}"></div></div>
    <div class="field"><label>Max overs per bowler (0 = no limit)</label><input class="input" type="number" name="max_overs_bowler" min="0" max="50" value="${t.max_overs_bowler}"></div>
    <div class="grid2"><div class="field"><label>…but up to (bowlers)</label><input class="input" type="number" name="extra_bowlers" min="0" max="11" placeholder="0" value="${t.extra_bowlers || ''}"></div><div class="field"><label>…may bowl (overs)</label><input class="input" type="number" name="extra_overs" min="0" max="50" placeholder="-" value="${t.extra_overs || ''}"></div></div>
    <div class="small muted" style="margin:-6px 0 12px">E.g. 6 overs: max 1 each, but up to 2 bowlers may bowl 2 overs. Leave blank for one limit for everyone.</div>
    <label class="switch" style="margin-bottom:14px"><b>Last man stands</b><input type="checkbox" name="last_man_stands" ${+t.last_man_stands ? 'checked' : ''}><span class="knob"></span></label>
    <div class="field"><label>Bouncers allowed per over (0 = no limit)</label><input class="input" type="number" name="bouncers_per_over" min="0" max="6" value="${t.bouncers_per_over ?? 1}"></div>
    <button class="btn block">Save</button>${id ? `<button type="button" class="btn light block" style="margin-top:8px;color:var(--red)" onclick="App.deleteType(${id})">Delete</button>` : ''}</form>`);
};
App.saveType = (e, id) => { e.preventDefault(); run(async () => { const f = formData(e.target); f.id = id; await api('type_save', f); closeSheet(); toast('Saved'); render(); }, e.submitter); };
App.deleteType = id => run(async () => { await api('type_delete', { id }); closeSheet(); render(); });
function viewProfile() {
  const u = S.user, gOn = S.auth && S.auth.google_client_id;
  const gCard = !gOn && !u.google ? '' : `<div class="section"><h2>Google account</h2></div><div class="card">
      ${u.google ? `<div class="row"><span class="avatar" style="font-weight:900;color:#4285f4;background:#fff;border:1px solid var(--line)">G</span>
          <div class="grow"><b>Linked ✓</b><div class="small muted ellipsis">${esc(u.email || '')}</div></div>
          ${u.has_password ? `<button type="button" class="btn sm light" onclick="App.unlinkGoogle()">Unlink</button>` : ''}</div>
          <div class="small muted" style="margin-top:8px">You can sign in with one tap using "Sign in with Google".${u.has_password ? '' : ' Set a password below if you also want to log in with your username.'}</div>`
        : `<div class="small muted" style="margin-bottom:10px">Link your Gmail so you can sign in with one tap - no password to remember.</div><div id="glink" class="gbtn"></div>`}
    </div>`;
  const chips = (name, list, val) => `<input type="hidden" name="${name}" value="${esc(val || '')}"><div class="chips wrap" data-for="${name}">${list.map(([v, l]) => `<button type="button" class="chip ${v === (val || '') ? 'on' : ''}" data-v="${esc(v)}" onclick="App.pickChip(this)">${l}</button>`).join('')}</div>`;
  const pl = u.player;
  mount(topbar('My Profile', { back: true }) + `<form class="content" onsubmit="App.saveProfile(event)">
    <div class="card ph-card">
      <div class="ph-big" onclick="App.photoMenu()">${personAvatar(u, 96)}<span class="ph-cam">📷</span></div>
      <div class="grow"><b style="font-size:18px">${esc(u.display_name)}</b><div class="small muted">@${esc(u.username)}</div>
        <div class="row" style="gap:6px;margin-top:8px;flex-wrap:wrap"><button type="button" class="btn sm" onclick="App.pickPhoto()">📷 Upload photo</button>
        <button type="button" class="btn sm light" onclick="App.figureSheet()">😀 Choose figure</button></div></div>
      <input type="file" id="photoFile" accept="image/*" hidden onchange="App.photoChosen(this)">
    </div>
    <div class="card">
    <div class="field"><label>Display name</label><input class="input" name="display_name" value="${esc(u.display_name)}" required></div>
    <div class="field"><label>Username</label><input class="input" value="${esc(u.username)}" disabled></div>
    <div class="field" style="margin-bottom:0"><label>About me <span class="muted" id="bioCount" style="float:right">${(u.bio || '').length}/160</span></label>
      <textarea class="input" name="bio" rows="3" maxlength="160" placeholder="e.g. Opening batter who loves pull shots 🏏 Big CSK fan!" oninput="document.getElementById('bioCount').textContent=this.value.length+'/160'">${esc(u.bio || '')}</textarea></div></div>
    <div class="section"><h2>🏏 My cricket profile</h2></div>
    <div class="card">
      <div class="field"><label>I play as</label>${chips('role', [['Batter', '🏏 Batter'], ['Bowler', '🎯 Bowler'], ['All-rounder', '⭐ All-rounder'], ['Wicket-keeper', '🧤 Keeper']], u.role)}</div>
      <div class="field"><label>Batting</label>${chips('batting_style', [['Right-hand bat', '👉 Right-hand'], ['Left-hand bat', '👈 Left-hand']], u.batting_style)}</div>
      <div class="field"><label>Bowling</label><select class="input" name="bowling_style">${BOWL_STYLES.map(b => `<option value="${esc(b)}" ${b === (u.bowling_style || '') ? 'selected' : ''}>${b || "I don't bowl"}</option>`).join('')}</select></div>
      <div class="field" style="margin-bottom:0"><label>Cricket experience (years)</label>
        <div class="row"><button type="button" class="btn sm light" onclick="App.expStep(-1)">−</button>
        <input class="input" id="expIn" name="experience_years" type="number" inputmode="numeric" min="0" max="60" placeholder="0" value="${u.experience_years ?? ''}" style="text-align:center;font-size:20px;font-weight:900;max-width:110px">
        <button type="button" class="btn sm light" onclick="App.expStep(1)">+</button><span class="small muted">years playing</span></div></div>
    </div>
    <div class="section"><h2>📊 My stats from the scorer</h2></div>
    <div class="card">
      ${pl ? `<div class="row">${badge({ short_name: pl.short_name, color: pl.color })}<div class="grow"><b>${esc(pl.name)}</b><div class="small muted">${esc(pl.team)}</div></div>
          <button type="button" class="btn sm light" onclick="App.linkPlayer(null)">Unlink</button></div>
          <div class="small muted" style="margin-top:8px">Your batting & bowling stats from matches scored in the app now show on your profile.</div>`
        : `<div class="small muted" style="margin-bottom:10px">Are you a player in one of your own teams? Link yourself so your real <b>batting and bowling stats</b> show on your profile.</div>
          <button type="button" class="btn block light" onclick="App.findMe()">🔍 Find me in a team</button>`}
    </div>
    <div class="section"><h2>Privacy</h2></div>
    <label class="switch" style="margin-bottom:6px"><span><b>🔒 Private profile</b><div class="small muted">Others can't find you, see your profile or follow you. Your followers lists stay hidden too.</div></span>
      <input type="checkbox" name="is_private" ${u.is_private ? 'checked' : ''}><span class="knob"></span></label>
    <div class="small muted" style="margin:0 2px 4px">Tip: you can also make a single match private when you create it.</div>
    ${mobileCard(u)}
    ${gCard}
    <div class="section"><h2>${u.has_password ? 'Change password' : 'Set a password (optional)'}</h2></div><div class="card">
    ${u.has_password ? `<div class="field"><label>Current password</label><input class="input" type="password" name="current_password" autocomplete="current-password"></div>` : ''}
    <div class="field"><label>New password</label><input class="input" type="password" name="new_password" minlength="6" autocomplete="new-password"></div></div>
    <button class="btn block">Save</button>
    <div class="section"><h2>Delete account</h2></div>
    ${u.is_admin ? `<div class="small muted" style="margin:0 2px">You're the administrator who looks after this website, so this account can't be deleted from the app.</div>`
      : `<button type="button" class="btn light block" style="color:var(--red)" onclick="App.deleteAccount()">🗑️ Delete my account</button>`}
    </form>`, 'more');
  if (gOn && !u.google) { S.gMode = 'link'; renderGoogleButton('glink', 'continue_with'); }
}
App.deleteAccount = () => {
  const u = S.user;
  sheet(`<h3>Delete your account?</h3>
    <p class="small muted">This <b>permanently</b> erases your profile, photo, follows, Academy progress, and the matches, tournaments and teams you created. Teams that other players use in their matches are kept for them. <b>This can't be undone.</b></p>
    <form onsubmit="App.deleteAccountGo(event)">
    ${u.has_password ? `<div class="field"><label>Enter your password to confirm</label><input class="input" type="password" name="password" autocomplete="current-password" required></div>`
      : `<div class="field"><label>Type your username <b>${esc(u.username)}</b> to confirm</label><input class="input" name="confirm" autocapitalize="none" autocomplete="off" required></div>`}
    <button class="btn block" style="background:var(--red)">Delete everything forever</button>
    <button type="button" class="btn light block" style="margin-top:8px" onclick="closeSheet()">Keep my account</button></form>`);
};
App.deleteAccountGo = e => {
  e.preventDefault();
  const f = formData(e.target);
  run(async () => {
    await api('account_delete', f);
    S.user = null; closeSheet();
    try { if (window.google && google.accounts) google.accounts.id.disableAutoSelect(); } catch (x) { /* ignore */ }
    toast('Your account has been deleted. Thanks for playing! 🏏');
    go('#/');
  }, e.submitter);
};
/* ---------- Cricket academies ---------- */
async function viewAcademies() {
  mount(topbar('Cricket academies', { back: true }) + `<div class="content">${sk(3)}</div>`, 'more');
  const d = await api('academies_list', { q: S.acq || '' });
  mount(topbar('Cricket academies', { back: true }) + `<div class="content">
    <div class="search">${I.search}<input class="input" placeholder="Search by name or city" value="${esc(S.acq || '')}" oninput="App.filterList(this.value)"></div>
    ${d.mine.length ? `<div class="news news-new"><div class="grow"><b>🎓 You train at ${d.mine.map(a => `<a class="link" href="#/academy/${a.id}">${esc(a.name)}</a>`).join(', ')}</b></div></div>` : ''}
    ${d.academies.length ? `<div class="card flush list">${d.academies.map(a => `<div class="item" data-q="${esc((a.name + ' ' + (a.city || '')).toLowerCase())}" onclick="go('#/academy/${a.id}')"><span class="avatar" style="background:#0ca678;color:#fff">🎓</span>
        <div class="grow"><div class="t ellipsis">${esc(a.name)}</div><div class="s">${esc([a.city, plural(a.students, 'student')].filter(Boolean).join(' · '))}${a.can_edit ? ' · yours' : ''}</div></div><span class="chev">${I.chev}</span></div>`).join('')}</div>`
      : `<div class="card empty"><div class="big">🎓</div>No academies registered yet.</div>`}
    <div class="card" style="margin-top:14px"><b>Run a cricket academy?</b><div class="small muted" style="margin:4px 0 10px">Register it here, add your students, and they can play and score matches as your academy team.</div><button class="btn block" onclick="go('#/academy/new')">🎓 Register your academy</button></div>
  </div>`, 'more');
}
async function viewAcademy(id) {
  mount(topbar('Academy', { back: true }) + `<div class="content">${sk(3)}</div>`, 'more');
  const d = await api('academy_get', { id: +id }), a = d.academy;
  const stu = s => `<div class="item" ${s.user_id ? `style="cursor:pointer" onclick="go('#/user/${s.user_id}')"` : ''}>${s.user_id ? personAvatar({ avatar: s.avatar, avatar_preset: s.avatar_preset, display_name: s.name }, 40) : `<span class="avatar">${esc(s.name.split(' ').map(w => w[0]).slice(0, 2).join(''))}</span>`}
      <div class="grow"><div class="t ellipsis">${esc(s.name)}${s.verified ? ' ✅' : ''}</div><div class="s">${s.username ? '@' + esc(s.username) : 'Added by the academy'}</div></div>
      ${a.can_edit || s.user_id === S.user.id ? `<button class="btn sm light" onclick="event.stopPropagation();App.academyRemove(${s.id})">${s.user_id === S.user.id && !a.can_edit ? 'Leave' : 'Remove'}</button>` : ''}</div>`;
  mount(topbar(a.name, { back: true, actions: a.can_edit ? `<button class="icon-btn" aria-label="Edit" onclick="go('#/academy/${a.id}/edit')">${I.edit}</button>` : '' }) + `<div class="team-hero" style="background:#0ca678;color:#fff"><span class="badge lg" style="background:rgba(255,255,255,.18);color:#fff">🎓</span>
      <div><h2>${esc(a.name)}</h2><p>${esc([a.city, plural(a.students, 'student')].filter(Boolean).join(' · '))}</p>${a.coach ? `<p>Coach: ${esc(a.coach)}</p>` : ''}${a.address ? `<p>📍 ${esc(a.address)}</p>` : ''}${a.phone ? `<p>📞 +${esc(a.phone)}</p>` : ''}</div></div>
    <div class="content">
      ${a.about ? `<div class="card bio">${esc(a.about)}</div>` : ''}
      ${a.can_edit ? `<div class="card"><b>➕ Add a student</b><div class="small muted" style="margin:2px 0 10px">Type a player's <b>username or mobile number</b> if they use the app, or just a <b>name</b> to add them to the squad.</div>
        <form class="row" onsubmit="App.academyAdd(event,${a.id},'user')"><input class="input grow" name="user" placeholder="Username or mobile number" autocapitalize="none" autocomplete="off" required><button class="btn sm">Add player</button></form>
        <form class="row" style="margin-top:8px" onsubmit="App.academyAdd(event,${a.id},'name')"><input class="input grow" name="name" placeholder="Or a name, e.g. Rahul (no account)" required><select class="input" name="role" style="max-width:120px"><option>Batter</option><option>Bowler</option><option>All-rounder</option><option>Wicket-keeper</option></select><button class="btn sm light">Add</button></form></div>` : ''}
      <div class="section"><h2>Students</h2><span class="small muted">${d.students.length}</span></div>
      ${d.students.length ? `<div class="card flush list">${d.students.map(stu).join('')}</div>` : '<div class="card empty"><div class="big">👥</div>No students yet.</div>'}
      ${d.team ? `<div class="section"><h2>Academy team</h2></div><div class="card row" style="cursor:pointer" onclick="go('#/team/${d.team.id}')">${badge(d.team)}<div class="grow"><b>${esc(d.team.name)}</b><div class="small muted">Students can pick this team for their matches</div></div><span class="chev">${I.chev}</span></div>` : ''}
      ${d.recent.length ? `<div class="section"><h2>Recent matches</h2></div>${d.recent.map(matchCard).join('')}` : ''}
      <p class="small muted" style="text-align:center">Run by ${esc(a.owner_name || '-')}${a.owner_username ? ' (@' + esc(a.owner_username) + ')' : ''}</p>
    </div>`, 'more');
}
App.academyAdd = (e, id, kind) => {
  e.preventDefault(); const f = formData(e.target);
  run(async () => { await api('academy_student_add', Object.assign({ academy_id: id }, kind === 'user' ? { user: f.user } : { name: f.name, role: f.role })); toast('Added ✓'); render(); }, e.submitter);
};
App.academyRemove = id => confirmSheet('Remove this student?', 'They will no longer be listed with the academy.', 'Remove', () => run(async () => { await api('academy_student_remove', { id }); closeSheet(); render(); }));
async function viewAcademyForm(id) {
  id = +id || 0;
  let a = { name: '', address: '', city: '', phone: '', coach: S.user.display_name, about: '' };
  if (id) { const d = await api('academy_get', { id }); a = d.academy; if (!a.can_edit) return go('#/academy/' + id); }
  mount(topbar(id ? 'Edit academy' : 'Register academy', { back: true }) + `<form class="content" onsubmit="App.academySave(event,${id})">
    <div class="card">
      <div class="field"><label>Academy name</label><input class="input" name="name" value="${esc(a.name)}" placeholder="e.g. Hyderabad Young Cricketers" required></div>
      <div class="field"><label>Address</label><input class="input" name="address" value="${esc(a.address || '')}" placeholder="Ground / street"></div>
      <div class="grid2"><div class="field"><label>City</label><input class="input" name="city" value="${esc(a.city || '')}" placeholder="Hyderabad"></div>
      <div class="field"><label>Phone</label><input class="input" name="phone" inputmode="tel" value="${esc(a.phone || '')}" placeholder="98765 43210"></div></div>
      <div class="field"><label>Head coach</label><input class="input" name="coach" value="${esc(a.coach || '')}"></div>
      <div class="field"><label>About</label><textarea class="input" name="about" rows="3" maxlength="600" placeholder="Age groups, timings, what you teach…">${esc(a.about || '')}</textarea></div>
    </div>
    <div class="small muted" style="margin:0 2px 10px">Registering creates a team with the academy's name. Students you add appear in it, and students with accounts can pick it for their matches.</div>
    <button class="btn block">${id ? 'Save' : 'Register academy →'}</button>
    ${id ? `<button type="button" class="btn light block" style="margin-top:8px;color:var(--red)" onclick="App.academyDelete(${id})">Delete academy</button>` : ''}</form>`, 'more');
}
App.academySave = (e, id) => { e.preventDefault(); const f = formData(e.target); f.id = id; run(async () => { const r = await api('academy_save', f); toast(id ? 'Saved ✓' : 'Academy registered 🎓'); go('#/academy/' + r.id); }, e.submitter); };
App.academyDelete = id => confirmSheet('Delete this academy?', 'The student list is removed. The team and its matches stay.', 'Delete', () => run(async () => { await api('academy_delete', { id }); closeSheet(); go('#/academies'); }));

/* ---------- Mobile number + WhatsApp verification (optional) ---------- */
function mobileCard(u) {
  const wa = S.auth && S.auth.whatsapp;
  return `<div class="section"><h2>Mobile number</h2></div><div class="card">
    ${u.mobile ? `<div class="row"><span class="avatar" style="background:#25d366;color:#fff">📱</span><div class="grow"><b>${esc(u.mobile)}</b><div class="small muted">${u.mobile_verified ? '✅ Verified on WhatsApp' : 'Not verified yet'}</div></div>
        <button type="button" class="btn sm light" onclick="App.mobileRemove()">Remove</button></div>`
      : `<div class="small muted" style="margin-bottom:10px">Optional. Add your WhatsApp number so academies can find you, and sign in with one tap - no password needed.</div>`}
    <div class="row" style="margin-top:10px"><input class="input grow" id="mobIn" inputmode="tel" autocomplete="tel" placeholder="${u.mobile ? 'Change number' : 'e.g. 98765 43210'}" maxlength="20" onkeydown="if(event.key==='Enter'){event.preventDefault();App.mobileVerify(this.nextElementSibling)}">
      <button type="button" class="btn sm" style="background:#25d366" onclick="App.mobileVerify(this)">${u.mobile_verified ? 'Change' : 'Verify on WhatsApp'}</button></div>
    ${wa ? '' : '<div class="small muted" style="margin-top:8px">WhatsApp messages are not switched on for this website yet' + (S.user.is_admin ? ' - add the Meta keys to config.local.php (see README). As admin you can still test: the link is shown to you.' : '.') + '</div>'}
  </div>`;
}
App.mobileVerify = btn => {
  const mobile = (document.getElementById('mobIn').value || '').trim() || (S.user.mobile || '');
  if (!mobile) return toast('Type your mobile number first', true);
  run(async () => { const r = await api('wa_request', { purpose: 'link', mobile }); waSheet(r, 'link'); }, btn);
};
App.mobileRemove = () => confirmSheet('Remove your mobile number?', 'You will no longer be able to sign in with WhatsApp.', 'Remove', () => run(async () => { const r = await api('mobile_remove'); S.user = r.user; closeSheet(); viewProfile(); }));
/* the "check WhatsApp" sheet: tap the link, or type the code */
function waSheet(r, purpose) {
  S.wa = { id: r.id, purpose };
  const oneTap = !(S.auth && S.auth.whatsapp_link === false);
  sheet(`<h3>📲 Check WhatsApp</h3><div class="sub">${r.sent ? (oneTap ? `We sent a message to <b>${esc(r.mobile)}</b>. Tap the <b>Verify</b> button in it and you're done - or type the 6-digit code here.` : `We sent a code to <b>${esc(r.mobile)}</b>. Copy it from WhatsApp and type it here.`) : `<span style="color:var(--red)">${esc(r.error_hint || 'Message not sent')}</span>`}</div>
    ${r.dev_link ? `<div class="card" style="box-shadow:none;border:1px dashed var(--line)"><b>Test mode (admin only)</b><div class="small muted">WhatsApp is not set up, so here is the link that would have been sent:</div><a class="link" href="${esc(r.dev_link)}" style="word-break:break-all">${esc(r.dev_link)}</a><div class="small muted">Code: <b>${esc(r.dev_code)}</b></div></div>` : ''}
    <form onsubmit="App.waCode(event)"><input class="input duel-code-in" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" placeholder="000000" autocomplete="one-time-code" required>
    <button class="btn block" style="margin-top:12px">Verify code</button></form>
    <div class="small muted" style="text-align:center;margin-top:10px">${oneTap ? `The link works for ${r.minutes} minutes. Opening it signs you in on that phone.` : `The code works for ${r.minutes} minutes.`}</div>`);
}
App.waCode = e => {
  e.preventDefault();
  const code = formData(e.target).code.trim();
  run(async () => {
    const r = await api('wa_verify_code', { id: S.wa.id, code });
    S.user = r.user; closeSheet(); toast(r.found_player ? `Mobile verified ✓ Found ${r.found_player.matches} of your matches as ${r.found_player.name} (${r.found_player.team})` : 'Mobile verified ✓');
    if (S.wa.purpose === 'link') viewProfile(); else { const a = S.after; S.after = null; go(a && !/^#\/(login|register|wa)/.test(a) ? a : '#/'); }
  }, e.submitter);
};
/* Sign in or sign up with WhatsApp - no password */
function viewWaLogin() {
  if (S.user) return go('#/');
  S.gMode = 'login';
  const signup = S.waMode === 'signup';
  mount(authPage(`<form class="panel" onsubmit="App.waGo(event)"><h2>${signup ? 'Join with WhatsApp 📲' : 'Sign in with WhatsApp 📲'}</h2>
    <p class="small muted" style="margin-top:0">${signup ? 'We\'ll send a WhatsApp message. Tap the button in it and your account is ready - no password.' : 'We\'ll send a one-tap sign-in link to your WhatsApp. No password needed.'}</p>
    ${signup ? '<div class="field"><label>Your name</label><input class="input" name="display_name" placeholder="e.g. Aarush" required></div>' : ''}
    <div class="field"><label>Mobile number</label><input class="input" name="mobile" inputmode="tel" autocomplete="tel" placeholder="98765 43210" required></div>
    ${signup ? consentBox() : ''}
    <div id="cap" class="cap"></div>
    <button class="btn block" style="background:#25d366">Send WhatsApp link</button></form>
    <div class="switch-link">${signup ? 'Already a player? <a href="#/wa" onclick="S.waMode=\'login\'">Sign in</a>' : 'New here? <a href="#/wa" onclick="S.waMode=\'signup\'">Join with WhatsApp</a>'} · <a href="#/login">Use a password</a></div>`), null);
  renderCaptcha();
}
App.waGo = e => {
  e.preventDefault();
  const f = formData(e.target), signup = S.waMode === 'signup';
  run(async () => {
    try {
      f.captcha = await captchaToken(signup ? 'register' : 'login');
      f.purpose = signup ? 'signup' : 'login';
      const r = await api('wa_request', f);
      if (r.new) { toast('No account uses that number yet - join with WhatsApp', true); S.waMode = 'signup'; return viewWaLogin(); }
      if (r.exists) { toast('That number already has an account - signing you in instead'); S.waMode = 'login'; f.purpose = 'login'; const r2 = await api('wa_request', f); return waSheet(r2, 'login'); }
      waSheet(r, signup ? 'signup' : 'login');
    } catch (err) { resetCaptcha(); throw err; }
  }, e.submitter);
};
App.unlinkGoogle = () => confirmSheet('Unlink Google?', 'You will log in with your username and password instead.', 'Unlink', () =>
  run(async () => { const r = await api('google_unlink'); S.user = r.user; closeSheet(); toast('Google account unlinked'); viewProfile(); }));
App.pickChip = btn => {
  const wrap = btn.parentElement, inp = wrap.previousElementSibling, on = btn.classList.contains('on');
  wrap.querySelectorAll('.chip').forEach(c => c.classList.remove('on'));
  if (!on) btn.classList.add('on');
  inp.value = on ? '' : btn.dataset.v;          // tap again to clear
};
App.expStep = d => { const i = document.getElementById('expIn'); i.value = Math.max(0, Math.min(60, (+i.value || 0) + d)); };
// Re-draw My Profile after a photo / figure / player change, keeping anything typed but not yet saved
function refreshProfileKeepForm() {
  const f = document.querySelector('form.content');
  const keep = f ? formData(f) : null;
  viewProfile();
  const g = document.querySelector('form.content');
  if (!keep || !g) return;
  for (const [k, v] of Object.entries(keep)) {
    const el = g.elements[k];
    if (!el || el.type === 'password' || el.disabled) continue;
    if (el.type === 'checkbox') el.checked = !!+v; else el.value = v;
  }
  g.querySelectorAll('.chips[data-for]').forEach(w => {
    const val = g.elements[w.dataset.for].value;
    w.querySelectorAll('.chip').forEach(c => c.classList.toggle('on', c.dataset.v === val));
  });
  const bc = document.getElementById('bioCount'); if (bc && g.elements.bio) bc.textContent = g.elements.bio.value.length + '/160';
}
async function quickSave(extra) {             // save one profile setting straight away
  const r = await api('profile_save', { display_name: S.user.display_name, ...extra });
  S.user = r.user; return r.user;
}
App.photoMenu = () => sheet(`<h3>Profile picture</h3><div class="sub">Show your face, or pick a cricket figure.</div><div class="plist">
  <button class="pbtn" onclick="closeSheet();App.pickPhoto()">📷 Upload a photo</button>
  <button class="pbtn" onclick="App.figureSheet()">😀 Choose a figure</button>
  ${S.user.avatar ? '<button class="pbtn" style="color:var(--red)" onclick="App.removePhoto()">🗑️ Remove photo</button>' : ''}</div>`);
App.pickPhoto = () => document.getElementById('photoFile')?.click();
App.photoChosen = input => {
  const f = input.files && input.files[0];
  input.value = '';
  if (!f) return;
  if (!/^image\//.test(f.type)) return toast('Please choose a photo', true);
  if (f.size > 15 * 1024 * 1024) return toast('That photo is too big', true);
  const url = URL.createObjectURL(f), img = new Image();
  img.onload = () => run(async () => {
    // Crop the middle square and shrink to 320×320 on the phone, so uploads are small and quick
    const side = Math.min(img.naturalWidth, img.naturalHeight), c = document.createElement('canvas');
    c.width = c.height = 320;
    const g = c.getContext('2d');
    g.fillStyle = '#fff'; g.fillRect(0, 0, 320, 320);
    g.drawImage(img, (img.naturalWidth - side) / 2, (img.naturalHeight - side) / 2, side, side, 0, 0, 320, 320);
    URL.revokeObjectURL(url);
    toast('Uploading photo…');
    const r = await api('avatar_upload', { image: c.toDataURL('image/jpeg', 0.86) });
    S.user = r.user; toast('Looking great! 📸'); refreshProfileKeepForm();
  });
  img.onerror = () => { URL.revokeObjectURL(url); toast("This photo type can't be opened here. Try a JPG or PNG.", true); };
  img.src = url;
};
App.removePhoto = () => run(async () => { const r = await api('avatar_remove'); S.user = r.user; closeSheet(); toast('Photo removed'); refreshProfileKeepForm(); });
App.figureSheet = () => {
  const cur = figureFor(S.user);
  sheet(`<h3>Choose your figure</h3><div class="sub">${S.user.avatar ? 'Shown when you remove your photo.' : 'Shown instead of a photo.'}</div>
    <div class="fig-grid">${FIG_KEYS.map(k => `<button class="${k === cur ? 'on' : ''}" onclick="App.setFigure('${k}')">${figureHtml(k, 56)}</button>`).join('')}</div>
    ${S.user.avatar ? '<button class="btn light block" onclick="App.removePhoto()">Remove photo and use this figure</button>' : ''}`);
};
App.setFigure = k => run(async () => { await quickSave({ avatar_preset: k }); closeSheet(); toast('Figure saved ' + FIGURES[k][0]); refreshProfileKeepForm(); });
App.findMe = () => {
  sheet(`<h3>Find yourself 🔍</h3><div class="sub">Type your name as it appears in your team.</div>
    <div class="search">${I.search}<input class="input" id="meq" placeholder="Your name" oninput="App.findMeSearch(this.value)"></div>
    <div id="meres" class="plist"><div class="muted small">Only players in teams made in the app (not IPL or international squads).</div></div>`);
  setTimeout(() => document.getElementById('meq')?.focus(), 250);
};
let meT;
App.findMeSearch = q => {
  clearTimeout(meT);
  meT = setTimeout(async () => {
    if (q.trim().length < 2) return;
    try {
      const d = await api('players_search', { q, custom_only: 1 });
      const el = document.getElementById('meres'); if (!el) return;
      el.innerHTML = d.players.length ? d.players.map(p => `<button class="pbtn" ${p.linked_to && p.linked_to !== S.user.username ? 'disabled' : ''} onclick="App.linkPlayer(${p.id})">${badge({ short_name: p.team, color: p.color }, 'sm')} ${esc(p.name)}<span class="s">${p.linked_to ? '@' + esc(p.linked_to) : esc(p.team_name)}</span></button>`).join('')
        : '<div class="muted small">No players found. Add yourself to one of your teams first (Teams → your team → Add player).</div>';
    } catch (e) { toast(e.message, true); }
  }, 250);
};
App.linkPlayer = id => run(async () => { await quickSave({ player_id: id }); closeSheet(); toast(id ? "Linked! Your stats will show on your profile 📊" : 'Unlinked'); refreshProfileKeepForm(); });
App.saveProfile = e => { e.preventDefault(); run(async () => { const r = await api('profile_save', formData(e.target)); S.user = r.user; toast('Profile saved'); go('#/more'); }, e.submitter); };

/* ---------- Help & feedback (players write to the admin; replies show here) ---------- */
async function viewHelp() {
  mount(topbar('Help & feedback', { back: true }) + `<div class="content">${sk(3)}</div>`, 'more');
  const d = await api('help_list');
  if (S.extras) S.extras.help_unread = 0;
  const kinds = Object.entries(d.kinds);
  mount(topbar('Help & feedback', { back: true }) + `<div class="content">
    <div class="card"><b>Send us a message</b><div class="small muted" style="margin:2px 0 10px">Stuck, found a bug or have an idea? We read every message and reply here.</div>
      <form onsubmit="App.helpSend(event)"><input type="hidden" name="kind" value="help">
      <div class="chips wrap" data-for="kind" style="margin-bottom:10px">${kinds.map(([k, l], i) => `<button type="button" class="chip ${i === 0 ? 'on' : ''}" data-v="${k}" onclick="App.pickChip(this)">${esc(l)}</button>`).join('')}</div>
      <textarea class="input" name="message" rows="4" maxlength="2000" placeholder="Tell us what happened, or what you'd like…" required></textarea>
      <div class="small muted" style="margin:6px 2px 10px">🔒 Please don't write phone numbers, addresses or passwords.</div>
      <button class="btn block">Send message</button></form></div>
    ${d.tickets.length ? `<div class="section"><h2>Your messages</h2></div>` + d.tickets.map(t => `<div class="card help-t"><div class="row"><b>#${t.id}</b><span class="role-tag">${esc(t.kind_label)}</span><span class="grow"></span>
        <span class="small ${t.status === 'answered' ? '' : 'muted'}" style="${t.status === 'answered' ? 'color:var(--pitch-3);font-weight:800' : ''}">${t.status === 'answered' ? '✅ Replied' : t.status === 'closed' ? 'Closed' : '⏳ Waiting for a reply'}</span></div>
        ${t.msgs.map(m => `<div class="adm-msg ${m.from === 'support' ? 'support' : 'player'}"><div>${esc(m.body).replace(/\n/g, '<br>')}</div><span>${m.from === 'support' ? '🏏 Cric-Scorer team' : 'You'} · ${fmtDate(m.at)}</span></div>`).join('')}
        ${t.status !== 'closed' ? `<form class="row" onsubmit="App.helpSend(event,${t.id})"><input class="input grow" name="message" placeholder="Reply…" maxlength="2000" required><button class="btn sm">Send</button></form>` : ''}</div>`).join('') : ''}
  </div>`, 'more');
}
App.helpSend = (e, ticket) => {
  e.preventDefault();
  const f = formData(e.target);
  if (ticket) f.ticket_id = ticket;
  run(async () => { await api('help_send', f); toast(ticket ? 'Sent ✓' : 'Thanks! We\'ll reply here soon 💬'); viewHelp(); }, e.submitter);
};

/* ---------- Crash reports: the admin sees them under Errors (no personal details) ---------- */
function initErrorReports() {
  let sent = 0;
  const report = (where, message) => {
    if (sent >= 5 || !message || /Script error|ResizeObserver|Load failed|NetworkError|Failed to fetch/i.test(message)) return;
    sent++;
    fetch('api.php?action=client_error', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'CricScorer', 'X-App-Version': window.CS_VERSION || '' },
      body: JSON.stringify({ where: (where || location.hash.split('/').slice(0, 2).join('/') || 'app').slice(0, 40), message: String(message).slice(0, 500) }) }).catch(() => {});
  };
  window.addEventListener('error', e => report(location.hash.replace(/\d+/g, 'N').slice(0, 40), (e.message || '') + (e.filename ? ' @ ' + e.filename.split('/').pop().split('?')[0] + ':' + e.lineno : '')));
  window.addEventListener('unhandledrejection', e => { const r = e.reason; if (r && (r.stale || /No internet/.test(r.message))) return; report(location.hash.replace(/\d+/g, 'N').slice(0, 40), r && r.message ? r.message : String(r)); });
}

/* ---------- Refresh, pull-to-refresh & app updates ---------- */
App.refresh = async () => {
  if (S.refreshing) return;
  S.refreshing = true;
  document.querySelectorAll('.refresh-btn').forEach(b => b.classList.add('spin'));
  try {
    const me = await api('me');
    S.extras = me; if (me.user) S.user = me.user;
    checkVersion(me.version, me.force_version);
    await render();
    if (!S.newVersion) toast('Updated ✓');
  } catch (e) { toast(e.message, true); }
  finally { S.refreshing = false; document.querySelectorAll('.refresh-btn').forEach(b => b.classList.remove('spin')); }
};
function checkVersion(v, force) {
  if (!v || !window.CS_VERSION || v === window.CS_VERSION || S.newVersion === v) return;
  S.newVersion = v;
  // The admin asked everyone to move to this version: update straight away (but never in the middle of a game)
  if (force && force === v && !document.getElementById('duel')) { toast(`Updating to version ${v}…`); setTimeout(() => App.updateApp(), 900); return; }
  const bar = document.createElement('div');
  bar.className = 'update-bar';
  bar.innerHTML = `<span>🎉 New version ${esc(v)} is ready</span><button onclick="App.updateApp()">Update now</button>`;
  document.body.appendChild(bar);
}
App.updateApp = async () => {
  toast('Updating app…');
  try {
    if ('serviceWorker' in navigator) for (const r of await navigator.serviceWorker.getRegistrations()) await r.unregister();
    if (window.caches) for (const k of await caches.keys()) await caches.delete(k);
  } catch (e) { /* continue to reload */ }
  location.replace(location.pathname + '?r=' + Date.now() + location.hash);
};
function initPullToRefresh() {
  const ptr = $('#ptr');
  let startY = null, pull = 0;
  const reset = () => { ptr.style.transform = ''; ptr.classList.remove('ready', 'show'); startY = null; pull = 0; };
  window.addEventListener('touchstart', e => {
    if (window.scrollY > 0 || $('#sheet-root').children.length || e.touches.length !== 1 || document.body.classList.contains('duel-open')) { startY = null; return; }
    startY = e.touches[0].clientY; pull = 0;
  }, { passive: true });
  window.addEventListener('touchmove', e => {
    if (startY === null) return;
    const dy = e.touches[0].clientY - startY;
    if (dy <= 0 || window.scrollY > 0) { reset(); return; }
    pull = Math.min(dy * 0.5, 110);
    ptr.classList.add('show'); ptr.classList.toggle('ready', pull > 70);
    ptr.style.transform = `translate(-50%, ${pull}px) rotate(${pull * 4}deg)`;
  }, { passive: true });
  window.addEventListener('touchend', () => {
    if (startY === null) return;
    const go = pull > 70;
    if (go) {
      ptr.classList.add('loading'); ptr.style.transform = 'translate(-50%, 60px)';
      App.refresh().finally(() => { ptr.classList.remove('loading'); reset(); });
    } else reset();
  }, { passive: true });
}

/* =====================================================================
   PLAYERS: search, follow, followers / following, profiles
   ===================================================================== */
const FIGURES = {
  bat: ['🏏', '#0d4a36', '#25a36b'], ball: ['🥎', '#7f1d1d', '#e23a4b'], gloves: ['🧤', '#1e3a8a', '#3b82f6'], trophy: ['🏆', '#92400e', '#f2b134'],
  lion: ['🦁', '#9a3412', '#fb923c'], tiger: ['🐯', '#b45309', '#fbbf24'], eagle: ['🦅', '#334155', '#94a3b8'], rocket: ['🚀', '#4c1d95', '#8b5cf6'],
  fire: ['🔥', '#991b1b', '#f97316'], star: ['⭐', '#1e40af', '#60a5fa'], bolt: ['⚡', '#3f3f46', '#facc15'], target: ['🎯', '#14532d', '#4ade80'],
  crown: ['👑', '#6b21a8', '#e879f9'], rhino: ['🦏', '#374151', '#9ca3af'], shark: ['🦈', '#0c4a6e', '#38bdf8'], wolf: ['🐺', '#1f2937', '#6b7280'],
};
const FIG_KEYS = Object.keys(FIGURES);
function figureFor(p) { return FIGURES[p.avatar_preset] ? p.avatar_preset : FIG_KEYS[(Number(p.id) || 0) % FIG_KEYS.length]; }
function figureHtml(key, size) {
  const [e, a, b] = FIGURES[key] || FIGURES.bat;
  return `<span class="avatar fig" style="width:${size}px;height:${size}px;font-size:${Math.round(size * 0.55)}px;background:linear-gradient(135deg,${a},${b})">${e}</span>`;
}
function personAvatar(p, size = 44) {
  if (!p) return figureHtml('bat', size);
  const fig = figureHtml(figureFor(p), size).replace(/"/g, '&quot;');
  return p.avatar ? `<img src="${esc(p.avatar)}" alt="" class="gavatar" style="width:${size}px;height:${size}px" referrerpolicy="no-referrer" onerror="this.outerHTML='${fig.replace(/'/g, '&#39;')}'">`
    : figureHtml(figureFor(p), size);
}
function followBtn(p) {
  if (!S.user || p.id === S.user.id || p.is_me) return '';
  return `<button class="btn sm ${p.following ? 'light' : ''} fbtn" data-uid="${p.id}" onclick="event.stopPropagation();App.toggleFollow(${p.id},this)">${p.following ? 'Following ✓' : '+ Follow'}</button>`;
}
function personRow(p, extra = '', tail = '') {
  return `<div class="item" onclick="go('#/user/${p.id}')">${personAvatar(p)}
    <div class="grow"><div class="t ellipsis">${esc(p.display_name)}${p.is_private ? ' 🔒' : ''}</div><div class="s ellipsis">@${esc(p.username)}${extra}</div></div>${followBtn(p)}${tail}</div>`;
}
App.toggleFollow = (id, btn) => run(async () => {
  const on = !btn.textContent.includes('Following');
  const r = await api(on ? 'follow' : 'unfollow', { id });
  document.querySelectorAll(`.fbtn[data-uid="${id}"]`).forEach(b => { b.textContent = r.following ? 'Following ✓' : '+ Follow'; b.classList.toggle('light', r.following); });
  const fc = document.getElementById('fcount-' + id); if (fc) fc.textContent = r.counts.followers;
  if (on) Sound.fx && Sound.fx('correct');
  toast(r.following ? 'Following! You\'ll see their matches on Home 👥' : 'Unfollowed');
}, btn);

async function viewPeople(tab) {
  if (tab) S.pTab = tab;
  const t = S.pTab || 'find';
  const tabs = [['find', '🔍 Find'], ['followers', 'Followers'], ['following', 'Following']];
  mount(topbar('Players') + `<div class="content"><div class="seg">${tabs.map(([k, l]) => `<button class="${t === k ? 'on' : ''}" onclick="go('#/people?t=${k}')">${l}</button>`).join('')}</div>
    ${t === 'find' ? `<div class="search">${I.search}<input class="input" id="pq" placeholder="Name, username or mobile number" autocapitalize="none" inputmode="search" value="${esc(S.pq || '')}" oninput="App.peopleSearch(this.value)"></div>` : ''}
    ${S.user.is_private ? `<div class="notice">🔒 Your profile is private - others can't find or follow you. Change this in <a class="link" href="#/profile">My Profile</a>.</div>` : ''}
    <div id="plist">${sk(3)}</div></div>`, 'more');
  if (t === 'find') return App.peopleSearch(S.pq || '', true);
  const d = await api('follow_list', { id: S.user.id, type: t });
  $('#plist').innerHTML = d.users.length ? `<div class="card flush list">${d.users.map(p => personRow(p, ' · since ' + esc(fmtDate(p.since, false)))).join('')}</div>`
    : `<div class="card empty"><div class="big">${t === 'followers' ? '🙋' : '👀'}</div>${t === 'followers' ? 'No followers yet.<br>Share your matches and friends will follow you!' : 'You are not following anyone yet.'}
       <br><button class="btn sm" style="margin-top:10px" onclick="go('#/people?t=find')">🔍 Find players</button></div>`;
}
let peopleT;
App.peopleSearch = (q, now = false) => {
  S.pq = q;
  clearTimeout(peopleT);
  peopleT = setTimeout(async () => {
    try {
      const d = await api('users_search', { q });
      const el = $('#plist'); if (!el) return;
      el.innerHTML = d.users.length ? `${q ? '' : '<div class="section" style="margin-top:4px"><h2>Players on Cric-Scorer</h2></div>'}<div class="card flush list">${d.users.map(p => personRow(p, ` · ${plural(p.followers, 'follower')}`)).join('')}</div>`
        : `<div class="card empty"><div class="big">🔍</div>${q ? `No players found for “${esc(q)}”.` : 'No other players yet.'}<div class="small muted" style="margin-top:6px">Private players don't appear in search.</div></div>`;
    } catch (e) { toast(e.message, true); }
  }, now ? 0 : 250);
};

async function viewUser(id) {
  mount(topbar('Player', { back: true }) + `<div class="content">${sk(3)}</div>`, 'more');
  let d;
  try { d = await api('user_get', { id: +id }); }
  catch (e) { mount(topbar('Player', { back: true }) + `<div class="content"><div class="card empty"><div class="big">🔒</div>${esc(e.message)}</div></div>`, 'more'); return; }
  const u = d.user, r = d.academy && d.academy.rank;
  const btn = d.me ? `<button class="btn sm light" onclick="go('#/profile')">✏️ Edit profile</button>` : followBtn({ ...u, following: d.following });
  mount(topbar(u.display_name, { back: true }) + `<div class="board" style="padding:18px 16px">
      <div class="row">${personAvatar(u, 64)}<div class="grow"><b style="font-family:var(--display);font-size:26px;line-height:1.05">${esc(u.display_name)}${u.verified ? ' <span title="Mobile verified" style="font-size:16px">✅</span>' : ''}${u.is_private ? ' 🔒' : ''}</b>
        <div class="other" style="margin:2px 0 0">@${esc(u.username)}${u.is_admin ? ' · Admin' : ''}${d.follows_me ? ' · <span class="pill" style="background:var(--lime);color:#13241b">Follows you</span>' : ''}</div></div></div>
      <div class="ustats">
        <a href="#/user/${u.id}/followers"><b id="fcount-${u.id}">${d.counts.followers}</b><span>Followers</span></a>
        <a href="#/user/${u.id}/following"><b>${d.counts.following}</b><span>Following</span></a>
        <div><b>${d.stats.matches}</b><span>Matches</span></div>
        ${r ? `<div><b>${r.icon}</b><span>${esc(r.name)}</span></div>` : ''}
      </div>
      <div style="margin-top:12px">${btn}</div></div>
    <div class="content">
      ${d.me && u.is_private ? `<div class="notice">🔒 Your profile is private. Only you (and the app admin) can see this page.</div>` : ''}
      ${u.bio ? `<div class="card bio">“${esc(u.bio)}”</div>` : (d.me ? `<div class="card empty small" style="padding:14px">Add a short bio in <a class="link" href="#/profile">My Profile</a> ✏️</div>` : '')}
      ${u.academies && u.academies.length ? `<div class="card"><b>🎓 Trains at</b> ${u.academies.map(a => `<a class="link" href="#/academy/${a.id}">${esc(a.name)}</a>`).join(', ')}</div>` : ''}
      ${cricketCard(u, d.me)}
      ${statsCard(d.player_stats, u.player)}
      <div class="section"><h2>${d.me ? 'My matches' : 'Matches scored'}</h2></div>
      ${d.matches.length ? d.matches.map(matchCard).join('') : `<div class="card empty small">No ${d.me ? '' : 'public '}matches yet.</div>`}
    </div>`, 'more');
}

function cricketCard(u, me) {
  const rows = [
    ['I play as', u.role ? (ROLE_ICON[u.role] || '') + ' ' + u.role : null],
    ['Batting', u.batting_style], ['Bowling', u.bowling_style],
    ['Experience', u.experience_years != null ? (u.experience_years === 0 ? 'Just starting!' : plural(u.experience_years, 'year')) : null],
  ].filter(r => r[1]);
  if (!rows.length) return me ? `<div class="card empty small" style="padding:14px">Fill in your cricket profile in <a class="link" href="#/profile">My Profile</a> 🏏</div>` : '';
  return `<div class="section"><h2>🏏 Cricket profile</h2></div><div class="card flush">${rows.map(([k, v], i) => `<div class="kv" ${i ? '' : 'style="border-top:0"'}><span>${k}</span><span>${esc(v)}</span></div>`).join('')}</div>`;
}
function statsCard(st, pl) {
  if (!st || !pl) return '';
  const b = st.batting, w = st.bowling, g = (v, l) => `<div><b>${v ?? '–'}</b><span>${l}</span></div>`;
  return `<div class="section"><h2>📊 Career stats</h2><a href="#/player/${pl.id}">${esc(pl.short_name)} ›</a></div>
    <div class="small muted" style="margin:-4px 2px 8px">From matches scored in Cric-Scorer as ${esc(pl.name)} (${esc(pl.team)})</div>
    <div class="card flush"><div class="kv" style="border-top:0"><span>🏏 Batting</span><span></span></div>
      <div class="statgrid">${g(b.mat, 'Matches')}${g(b.runs, 'Runs')}${g(b.hs + (b.hs_no ? '*' : ''), 'Best')}${g(b.avg, 'Average')}${g(b.sr, 'Strike rate')}${g(b.f4 + ' / ' + b.f6, '4s / 6s')}</div>
      <div class="kv"><span>🎯 Bowling</span><span></span></div>
      <div class="statgrid">${g(w.wkts, 'Wickets')}${g(w.balls ? Math.floor(w.balls / 6) + '.' + (w.balls % 6) : 0, 'Overs')}${g(w.inns ? w.best_w + '/' + w.best_r : null, 'Best')}${g(w.econ, 'Economy')}${g(w.avg, 'Average')}${g(st.catches, 'Catches')}</div></div>`;
}
async function viewFollowList(id, type) {
  mount(topbar(type === 'followers' ? 'Followers' : 'Following', { back: true }) + `<div class="content">${sk(3)}</div>`, 'more');
  let d;
  try { d = await api('follow_list', { id: +id, type }); }
  catch (e) { mount(topbar('Players', { back: true }) + `<div class="content"><div class="card empty"><div class="big">🔒</div>${esc(e.message)}</div></div>`, 'more'); return; }
  const who = d.me ? 'You' : d.user.display_name;
  mount(topbar(type === 'followers' ? 'Followers' : 'Following', { back: true, sub: d.me ? 'You' : '@' + d.user.username }) + `<div class="content">
    ${d.users.length ? `<div class="card flush list">${d.users.map(p => personRow(p, '', d.me && type === 'followers' ? `<button class="icon-btn" style="color:var(--muted)" aria-label="Remove follower" onclick="event.stopPropagation();App.removeFollower(${p.id})">✕</button>` : '')).join('')}</div>`
      : `<div class="card empty"><div class="big">👥</div>${type === 'followers' ? `${esc(who)} ${d.me ? "don't" : "doesn't"} have any followers yet.` : `${esc(who)} ${d.me ? "aren't" : "isn't"} following anyone yet.`}</div>`}
    <div class="small muted" style="text-align:center;margin-top:8px">Private players are not shown in these lists.</div></div>`, 'more');
}
App.removeFollower = id => confirmSheet('Remove follower?', 'They will stop following you. They can follow you again unless your profile is private.', 'Remove',
  () => run(async () => { await api('remove_follower', { id }); closeSheet(); toast('Follower removed'); render(); }));

/* ---------- Boot ---------- */
window.addEventListener('beforeinstallprompt', e => { e.preventDefault(); S.deferredInstall = e; });
(async function boot() {
  try { const me = await api('me'); S.user = me.user; S.auth = me.auth || null; S.extras = me; checkVersion(me.version, me.force_version); } catch (e) { /* offline */ }
  initErrorReports();
  if (/[?&]verified=1/.test(location.search)) { history.replaceState(null, '', location.pathname + location.hash); setTimeout(() => toast('✅ Mobile verified - welcome!'), 600); }
  initPullToRefresh();
  document.addEventListener('visibilitychange', () => { if (!document.hidden) api('me').then(r => { S.extras = r; if (r.user) S.user = r.user; checkVersion(r.version, r.force_version); }).catch(() => {}); });
  window.addEventListener('hashchange', () => { navDepth++; render(); });
  await render();
  const sp = $('#splash');
  if (sp) { setTimeout(() => sp.classList.add('out'), 350); setTimeout(() => sp.remove(), 900); }
  if ('serviceWorker' in navigator) navigator.serviceWorker.register('sw.js').catch(() => {});
})();
