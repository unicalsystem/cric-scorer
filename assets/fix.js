/* Cric-Scorer - Match correction desk (v1.42): match → innings → over → ball. Administrator only. */
'use strict';
(() => {
  const $ = s => document.querySelector(s);
  const esc = s => String(s == null ? '' : s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const FIELDS = ['batsman_id', 'non_striker_id', 'bowler_id', 'runs', 'extra', 'wicket', 'dismissed_id', 'fielder_id'];
  const EXTRAS = [['', '-'], ['wd', 'Wide'], ['nb', 'No ball'], ['b', 'Bye'], ['lb', 'Leg bye']];
  const F = { list: [], tours: [], d: null, inn: 0, over: 0, draft: [], busy: false, uid: 0 };

  /* ---------- plumbing ---------- */
  async function api(action, data = {}) {
    const r = await fetch('api.php?action=' + action, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'CricScorer' }, body: JSON.stringify(data) });
    let j = null;
    try { j = await r.json(); } catch (e) { /* not JSON */ }
    if (!r.ok || !j || j.error) throw new Error((j && j.error) || 'The server did not answer properly (' + r.status + '). Please try again.');
    return j;
  }
  let toastT = null;
  function toast(msg, err = false) {
    const t = $('#toast'); t.textContent = msg; t.className = 'show' + (err ? ' err' : '');
    clearTimeout(toastT); toastT = setTimeout(() => { t.className = ''; }, err ? 6000 : 2600);
  }
  async function run(fn) {
    if (F.busy) return;
    F.busy = true; document.body.style.cursor = 'progress';
    try { await fn(); } catch (e) { toast(e.message || 'Something went wrong', true); }
    finally { F.busy = false; document.body.style.cursor = ''; }
  }
  function modal(html) { $('#modal-root').innerHTML = `<div class="modal-bg" data-close="1"><div class="modal" role="dialog" aria-modal="true">${html}</div></div>`; }
  function closeModal() { $('#modal-root').innerHTML = ''; }
  let confirmFn = null;
  function confirmBox(title, text, yes, fn, red = false) {
    confirmFn = fn;
    modal(`<h3>${esc(title)}</h3><p>${text}</p><div class="row"><button class="btn light" data-act="modal-close">Cancel</button><button class="btn ${red ? 'red' : ''}" data-act="confirm-yes">${esc(yes)}</button></div>`);
  }
  const fmtDate = s => { if (!s) return ''; const d = new Date(String(s).replace(' ', 'T')); return isNaN(d) ? s : d.toLocaleDateString('en-IN', { day: 'numeric', month: 'short' }) + ', ' + d.toLocaleTimeString('en-IN', { hour: 'numeric', minute: '2-digit' }); };

  /* ---------- 1. match list ---------- */
  async function loadList() {
    const d = await api('admin_fix_matches', { q: $('#q').value.trim(), tournament_id: +$('#tour').value });
    F.list = d.matches;
    if (!F.tours.length && d.tournaments.length) {
      F.tours = d.tournaments;
      $('#tour').innerHTML = '<option value="0">All tournaments</option>' + d.tournaments.map(t => `<option value="${t.id}">${esc(t.name)} (${t.n})</option>`).join('') + '<option value="-1">Matches outside a tournament</option>';
    }
    drawList();
  }
  function drawList() {
    const cur = F.d ? F.d.match.id : 0;
    $('#matches').innerHTML = F.list.length ? F.list.map(m => `<button class="mcard ${m.id === cur ? 'on' : ''}" data-act="open" data-id="${m.id}">
        <div class="meta"><span>#${m.id} · ${esc(m.tournament || 'Friendly')}${m.round_name ? ' · ' + esc(m.round_name) : ''}</span><span>${esc(fmtDate(m.match_date))}</span></div>
        <div class="teams">${esc(m.t1)} <span style="color:var(--muted);font-weight:700">v</span> ${esc(m.t2)}</div>
        <div class="sc">${m.scores.map(s => esc(s.team) + ' ' + esc(s.text) + (s.super ? ' (SO)' : '')).join(' · ')}</div>
        <div class="res">${esc(m.result_text || (m.status === 'abandoned' ? 'Abandoned' : ''))} ${m.fixes ? `<span class="badge">corrected ×${m.fixes}</span>` : ''}</div>
      </button>`).join('') : '<div class="hint">No finished matches found.</div>';
  }

  /* ---------- match data helpers ---------- */
  const inn = () => F.d.innings[F.inn];
  const team = id => F.d.teams.find(t => t.id === id);
  const pname = id => { if (!id) return ''; for (const t of F.d.teams) { const p = t.players.find(x => x.id === id); if (p) return p.name; } return 'Player #' + id; };
  const nonDelivery = w => !!w && F.d.non_delivery.includes(w);
  const bowlerWicket = w => !!w && F.d.bowler_wickets.includes(w);
  const isLegal = b => !nonDelivery(b.wicket) && b.extra !== 'wd' && b.extra !== 'nb';
  const total = b => nonDelivery(b.wicket) ? 0 : b.extra === 'wd' ? 1 + b.runs : b.extra === 'nb' ? b.runs + 1 : b.runs;
  const isWicket = b => !!b.wicket && b.wicket !== 'retired hurt';
  function label(b) {
    if (b.wicket === 'retired hurt') return 'RH';
    if (b.wicket === 'retired out') return 'RO+W';
    if (b.wicket === 'timed out') return 'TO+W';
    let l = b.extra === 'wd' ? (b.runs > 0 ? b.runs : '') + 'wd' : b.extra === 'nb' ? (b.runs > 0 ? b.runs : '') + 'nb' : b.extra === 'b' ? b.runs + 'b' : b.extra === 'lb' ? b.runs + 'lb' : String(b.runs);
    if (isWicket(b)) l = (l === '0' ? '' : l + '+') + 'W';
    return l;
  }
  const chipClass = b => isWicket(b) ? 'w' : b.extra ? 'x' : (b.runs === 4 ? 'b4' : b.runs === 6 ? 'b6' : '');
  const changed = b => !b._new && !b._del && FIELDS.some(f => (b[f] == null ? null : b[f]) !== (b._o[f] == null ? null : b._o[f]));
  const touched = b => b._new || b._del || changed(b);
  const dirtyCount = () => F.draft.filter(touched).length;

  function makeDraft() { F.draft = inn() ? inn().balls.map(b => ({ ...b, _o: { ...b }, _k: ++F.uid })) : []; number(); }
  /** over / ball numbers as they will be after saving (a deleted ball keeps the over it sits in) */
  function number() {
    const bpo = F.d.match.bpo; let legal = 0;
    for (const b of F.draft) {
      b.over = Math.floor(legal / bpo); b.ball = (legal % bpo) + 1;
      if (!b._del && isLegal(b)) legal++;
    }
  }
  function draftScore() {
    const live = F.draft.filter(b => !b._del); const bpo = F.d.match.bpo;
    const legal = live.filter(isLegal).length;
    return { runs: live.reduce((a, b) => a + total(b), 0) + inn().penalties, wk: live.filter(isWicket).length, overs: Math.floor(legal / bpo) + '.' + (legal % bpo) };
  }
  function overList() {
    const map = new Map();
    for (const b of F.draft) { if (!map.has(b.over)) map.set(b.over, []); map.get(b.over).push(b); }
    return [...map.entries()].sort((a, b) => a[0] - b[0]).map(([no, balls]) => ({ no, balls }));
  }

  /* ---------- 2-3. the match screen ---------- */
  async function openMatch(id, keep) {
    const d = await api('admin_fix_match', { id });
    setData(d, keep);
  }
  function setData(d, keep) {
    const same = keep && F.d && F.d.match.id === d.match.id;
    F.d = d;
    if (!same) { F.inn = 0; F.over = 0; }
    if (F.inn >= d.innings.length) F.inn = 0;
    makeDraft();
    const nos = overList().map(o => o.no);
    if (!nos.includes(F.over)) F.over = nos.length ? Math.min(Math.max(F.over, nos[0]), nos[nos.length - 1]) : 0;
    history.replaceState(null, '', 'fix.php?m=' + d.match.id);
    draw(); drawList();
  }

  function playerOptions(teamId, sel, opts = {}) {
    const t = team(teamId); if (!t) return '';
    const o = p => `<option value="${p.id}" ${p.id === sel ? 'selected' : ''}>${esc(p.name)}</option>`;
    const xi = t.players.filter(p => p.xi), rest = t.players.filter(p => !p.xi);
    let h = opts.empty != null ? `<option value="">${esc(opts.empty)}</option>` : '';
    if (sel && !t.players.some(p => p.id === sel)) h += `<option value="${sel}" selected>Player #${sel} (not in this team)</option>`;
    h += rest.length ? `<optgroup label="Played this match">${xi.map(o).join('')}</optgroup><optgroup label="Rest of the squad">${rest.map(o).join('')}</optgroup>` : xi.map(o).join('');
    return h;
  }

  function draw() {
    const d = F.d, m = d.match, I = inn();
    if (!I) { $('#main').innerHTML = `<div class="welcome"><h2>${esc(d.teams[0].name)} v ${esc(d.teams[1].name)}</h2><p>This match has no innings to correct.</p></div>`; return; }
    const overs = overList();
    const cur = overs.find(o => o.no === F.over) || overs[0] || { no: 0, balls: [] };
    F.over = cur.no;
    const bat = I.batting_team_id, bowl = I.bowling_team_id;
    const n = dirtyCount();

    const head = `<div class="mhead"><div><h2>${esc(d.teams[0].name)} v ${esc(d.teams[1].name)}</h2>
        <div class="sub">Match #${m.id} · ${esc(m.tournament || 'Friendly')}${m.round ? ' · ' + esc(m.round) : ''}${m.title ? ' · ' + esc(m.title) : ''} · ${esc(fmtDate(m.date))} · ${m.test ? 'Test' : m.overs + ' overs'} · ${m.players_per_side} a side${m.extra_players ? ' + subs' : ''}</div></div>
      <div class="actions"><button class="btn light" data-act="undo" ${d.history.length ? '' : 'disabled'}>↩ Undo last saved change</button>
        <button class="btn light" data-act="history" ${d.history.length ? '' : 'disabled'}>🕘 History${d.history.length ? ' (' + d.history.length + ')' : ''}</button>
        <button class="btn light" data-act="pdf">📄 PDF scorecard</button>
        <a class="btn light" href="m/${m.id}" target="_blank" rel="noopener">Scorecard ↗</a></div></div>
      <div class="result"><span>${esc(m.result || (m.status === 'abandoned' ? 'Match abandoned' : 'No result yet'))}</span>${m.pom ? `<span class="pom">Player of the Match: ${esc(m.pom)}</span>` : ''}</div>`;

    const warns = d.warnings.length ? `<div class="warns"><b class="t">⚠ ${d.warnings.length} thing${d.warnings.length === 1 ? '' : 's'} to check in this match</b>${d.warnings.map(w =>
      `<button data-act="warn" data-inn="${w.inn}" data-over="${w.over == null ? '' : w.over}">${w.inn ? 'Innings ' + w.inn + ': ' : ''}${esc(w.text)}</button>`).join('')}</div>` : '';

    const inns = `<div class="sect"><div class="step"><i>2</i> Pick the innings</div><div class="inns">${d.innings.map((x, i) => `<button class="inn ${i === F.inn ? 'on' : ''}" data-act="inn" data-i="${i}">
        <div class="k">${x.is_super ? 'Super over' : 'Innings ' + x.no}${x.target != null ? ' · target ' + x.target + (x.dls ? ' (DLS)' : '') : ''}</div>
        <div class="n">${esc(team(x.batting_team_id).name)}</div><div class="s">${x.runs}/${x.wickets} <small>(${x.overs} ov)</small></div></button>`).join('')}</div></div>`;

    const overTiles = `<div class="sect"><div class="step"><i>3</i> Pick the over</div><div class="overs">${overs.map(o => {
      const live = o.balls.filter(b => !b._del);
      const bw = [...new Set(live.filter(b => !nonDelivery(b.wicket)).map(b => b.bowler_id))];
      return `<button class="ov ${o.no === F.over ? 'on' : ''} ${bw.length > 1 ? 'flag' : ''} ${o.balls.some(touched) ? 'dirty' : ''}" data-act="over" data-o="${o.no}">
        <div class="h">Over ${o.no + 1}<span>${live.reduce((a, b) => a + total(b), 0)} run${live.reduce((a, b) => a + total(b), 0) === 1 ? '' : 's'}${live.some(isWicket) ? ' · ' + live.filter(isWicket).length + 'W' : ''}</span></div>
        <div class="bw">${bw.length > 1 ? '⚠ ' : ''}${bw.map(id => esc(pname(id))).join(' + ') || '-'}</div>
        <div class="chips">${live.map(b => `<span class="chip ${chipClass(b)}">${esc(label(b))}</span>`).join('')}</div></button>`;
    }).join('') || '<div class="hint">No balls in this innings.</div>'}</div></div>`;

    const rows = cur.balls.map(b => {
      const i = F.draft.indexOf(b), nd = nonDelivery(b.wicket), o = b._o || {};
      const chg = f => !b._new && !b._del && (b[f] == null ? null : b[f]) !== (o[f] == null ? null : o[f]) ? 'chg' : '';
      const was = f => chg(f) ? `title="Was: ${esc(['batsman_id', 'non_striker_id', 'bowler_id', 'dismissed_id', 'fielder_id'].includes(f) ? (pname(o[f]) || '-') : (o[f] == null || o[f] === '' ? '-' : o[f]))}"` : '';
      const needWho = b.wicket && !bowlerWicket(b.wicket);
      const whoOpts = b.wicket === 'timed out' ? playerOptions(bat, b.dismissed_id, { empty: 'Choose…' })
        : [b.batsman_id, b.non_striker_id].filter(Boolean).map(id => `<option value="${id}" ${id === b.dismissed_id ? 'selected' : ''}>${esc(pname(id))}</option>`).join('');
      return `<tr class="${b._del ? 'del' : b._new ? 'new' : changed(b) ? 'dirty' : ''}">
        <td class="no"><span>${b.over}.${b.ball}</span> <span class="chip ${chipClass(b)}">${esc(label(b))}</span><small>${b._del ? 'deleted' : b._new ? 'new ball' : isLegal(b) ? '' : (nd ? 'no ball bowled' : 'not counted')}</small></td>
        <td class="pl"><select data-i="${i}" data-f="batsman_id" class="${chg('batsman_id')}" ${was('batsman_id')} aria-label="Striker">${playerOptions(bat, b.batsman_id)}</select></td>
        <td><button class="icon" data-act="swap" data-i="${i}" title="Swap striker and non-striker" aria-label="Swap striker and non-striker" ${b._del || !b.non_striker_id ? 'disabled' : ''}>⇄</button></td>
        <td class="pl"><select data-i="${i}" data-f="non_striker_id" class="${chg('non_striker_id')}" ${was('non_striker_id')} aria-label="Non-striker">${playerOptions(bat, b.non_striker_id, m.last_man_stands ? { empty: '(nobody)' } : {})}</select></td>
        <td class="pl"><select data-i="${i}" data-f="bowler_id" class="${chg('bowler_id')}" ${was('bowler_id')} aria-label="Bowler">${playerOptions(bowl, b.bowler_id)}</select></td>
        <td class="runs"><select data-i="${i}" data-f="runs" class="${chg('runs')}" ${was('runs')} ${nd ? 'disabled' : ''} aria-label="Runs">${[0, 1, 2, 3, 4, 5, 6, 7].map(r => `<option ${r === b.runs ? 'selected' : ''}>${r}</option>`).join('')}</select></td>
        <td class="ex"><select data-i="${i}" data-f="extra" class="${chg('extra')}" ${was('extra')} ${nd ? 'disabled' : ''} aria-label="Extra">${EXTRAS.map(([v, l]) => `<option value="${v}" ${(b.extra || '') === v ? 'selected' : ''}>${l}</option>`).join('')}</select></td>
        <td class="wk"><select data-i="${i}" data-f="wicket" class="${chg('wicket')}" ${was('wicket')} aria-label="Wicket"><option value="">Not out</option>${d.wicket_types.map(w => `<option ${w === b.wicket ? 'selected' : ''}>${esc(w)}</option>`).join('')}</select>
          ${b.wicket ? `<div class="wk2">${needWho ? `<label>Out<select data-i="${i}" data-f="dismissed_id" class="${chg('dismissed_id')}" aria-label="Who is out">${whoOpts}</select></label>` : ''}
            ${nd ? '' : `<label>Fielder<select data-i="${i}" data-f="fielder_id" class="${chg('fielder_id')}" aria-label="Fielder">${playerOptions(bowl, b.fielder_id, { empty: 'No fielder' })}</select></label>`}</div>` : ''}</td>
        <td class="act">${b._del ? `<button class="btn light small" data-act="undel" data-i="${i}">Keep</button>` : `<button class="icon" data-act="add" data-i="${i}" title="Add a ball after this one" aria-label="Add a ball after this one">＋</button>
          ${changed(b) ? `<button class="icon" data-act="reset" data-i="${i}" title="Put this ball back as it was" aria-label="Put this ball back as it was">↺</button>` : ''}
          <button class="icon danger" data-act="del" data-i="${i}" title="${b._new ? 'Remove this new ball' : 'Delete this ball'}" aria-label="Delete this ball">🗑</button>`}</td></tr>`;
    }).join('');

    const firstIdx = cur.balls.length ? F.draft.indexOf(cur.balls[0]) : F.draft.length;
    const table = `<div class="sect"><div class="step"><i>4</i> Correct the balls of over ${cur.no + 1}</div><div class="panel">
      <div class="panel-head"><b>${esc(team(bat).name)} batting · over ${cur.no + 1}</b>
        <div class="tools"><span>Whole over:</span>
          <select id="over-bowler" aria-label="Set the bowler for the whole over"><option value="">Set bowler for every ball…</option>${playerOptions(bowl, 0)}</select>
          <button class="btn light small" data-act="swap-over" title="Swap striker and non-striker on every ball of this over">⇄ Swap strike on every ball</button></div></div>
      <table class="balls"><colgroup><col style="width:9%"><col style="width:15%"><col style="width:4%"><col style="width:15%"><col style="width:15%"><col style="width:6%"><col style="width:9%"><col style="width:16%"><col style="width:11%"></colgroup>
        <thead><tr><th>Ball</th><th>Striker</th><th></th><th>Non-striker</th><th>Bowler</th><th>Runs</th><th>Extra</th><th>Wicket</th><th></th></tr></thead>
        <tbody>${rows || '<tr><td colspan="9" class="hint">No balls in this over.</td></tr>'}</tbody></table>
      <div class="addrow"><button class="btn light small" data-act="add-at" data-i="${firstIdx}">＋ Add a ball at the start of this over</button></div>
      <div class="legend">Runs = what the batters ran or hit (a wide with no runs is 0, the 1 for the wide is added by itself). <b style="color:var(--blue)">Blue</b> = changed, not saved yet. Over and ball numbers re-count by themselves when a ball is added, deleted or turned into a wide / no-ball.</div>
    </div></div>`;

    const tools = `<div class="sect grid2">
      <div class="box"><h3>Wrong player picked?</h3><p>Put the right player in for every ball in one go - e.g. the scorer picked Arjun but Rohan actually played.</p>
        <div class="form">
          <label>Wrong player (as scored)<select id="rp-from"><option value="">Choose…</option>${d.teams.map(t => `<optgroup label="${esc(t.name)}">${t.players.filter(p => p.played).map(p => `<option value="${p.id}">${esc(p.name)}</option>`).join('')}</optgroup>`).join('')}</select></label>
          <label>Right player (who really played)<select id="rp-to"><option value="">Choose the wrong player first</option></select></label>
          <label>Where<select id="rp-scope"><option value="0">The whole match</option>${d.innings.map(x => `<option value="${x.id}">${x.is_super ? 'Super over' : 'Innings ' + x.no} only (${esc(team(x.batting_team_id).name)} batting)</option>`).join('')}</select></label>
          <div class="checks"><label><input type="checkbox" id="rp-bat" checked> Batting</label><label><input type="checkbox" id="rp-bowl" checked> Bowling</label><label><input type="checkbox" id="rp-field" checked> Fielding</label></div>
          <div><button class="btn" data-act="replace">Replace player</button></div></div></div>
      <div class="box"><h3>Playing lists</h3><p>Players on the match list. <span style="background:var(--amber-soft);padding:0 5px;border-radius:6px">Amber</span> = does not appear in any ball; remove them only if they did not play.</p>
        ${d.teams.map(t => `<div style="margin-bottom:10px"><b>${esc(t.name)}</b> <span class="small" style="color:var(--muted)">(${t.players.filter(p => p.xi).length})</span>
          <div class="xi" style="margin-top:5px">${t.players.filter(p => p.xi).map(p => `<span class="${p.played ? '' : 'np'}">${esc(p.name)}${p.played ? '' : `<button data-act="xi-remove" data-id="${p.id}" data-name="${esc(p.name)}" title="Remove from the match list">remove</button>`}</span>`).join('')}</div></div>`).join('')}</div></div>`;

    const sc = draftScore();
    const bar = n ? `<div class="savebar"><div class="txt"><b>${n} unsaved change${n === 1 ? '' : 's'} in ${esc(team(bat).name)}'s innings</b>
        <span>This innings will be ${sc.runs}/${sc.wk} (${sc.overs} ov) - it is ${I.runs}/${I.wickets} (${I.overs} ov) now. The result is worked out again when you save.</span></div>
        <input type="text" id="note" maxlength="120" placeholder="Note (optional) - why this change?" value="${esc(F.note || '')}">
        <button class="btn light" data-act="discard">Discard</button><button class="btn lime" data-act="save">Save changes</button></div>` : '';

    const y = $('#main').scrollTop;
    $('#main').innerHTML = head + warns + inns + overTiles + table + tools + bar;
    $('#main').scrollTop = y;
  }

  /* ---------- editing the draft ---------- */
  function setField(b, f, raw) {
    const idFields = ['batsman_id', 'non_striker_id', 'bowler_id', 'dismissed_id', 'fielder_id'];
    const v = idFields.includes(f) ? (raw === '' ? null : +raw) : f === 'runs' ? +raw : (raw === '' ? null : raw);
    const old = b[f];
    let swapped = false;
    if (f === 'batsman_id' && v === b.non_striker_id) { b.non_striker_id = old; swapped = true; }   // picking the other batter swaps them
    if (f === 'non_striker_id' && v === b.batsman_id) { b.batsman_id = old; swapped = true; }
    b[f] = v;
    if ((f === 'batsman_id' || f === 'non_striker_id') && b.wicket && b.wicket !== 'timed out') {
      if (bowlerWicket(b.wicket)) b.dismissed_id = b.batsman_id;
      else if (b.dismissed_id === old && !swapped) b.dismissed_id = v;
      if (![b.batsman_id, b.non_striker_id].includes(b.dismissed_id)) b.dismissed_id = b.batsman_id;
    }
    if (f === 'extra' && (v === 'b' || v === 'lb') && b.runs < 1) b.runs = 1;
    if (f === 'wicket') {
      if (!v) { b.dismissed_id = null; b.fielder_id = null; }
      else if (v === 'timed out') { if ([b.batsman_id, b.non_striker_id].includes(b.dismissed_id)) b.dismissed_id = null; }
      else if (bowlerWicket(v) || ![b.batsman_id, b.non_striker_id].includes(b.dismissed_id)) b.dismissed_id = b.batsman_id;
      if (nonDelivery(v)) { b.runs = 0; b.extra = null; b.fielder_id = null; }
    }
  }
  function newBall(like) {
    const I = inn(), bat = team(I.batting_team_id).players, bowl = team(I.bowling_team_id).players;
    return { id: 0, _new: true, _k: ++F.uid, _o: {}, batsman_id: like ? like.batsman_id : bat[0].id, non_striker_id: like ? like.non_striker_id : (bat[1] ? bat[1].id : null),
      bowler_id: like ? like.bowler_id : bowl[0].id, runs: 0, extra: null, wicket: null, dismissed_id: null, fielder_id: null, over: 0, ball: 0 };
  }
  function buildOps() {
    const ops = [], ins = [], dels = [];
    const vals = b => ({ batsman_id: b.batsman_id, non_striker_id: b.non_striker_id, bowler_id: b.bowler_id, runs: b.runs, extra: b.extra, wicket: b.wicket, dismissed_id: b.dismissed_id, fielder_id: b.fielder_id });
    let lastSeq = 0;
    for (const b of F.draft) {
      if (b._new) { ins.push({ op: 'insert', innings_id: inn().id, after_seq: lastSeq, ...vals(b) }); continue; }
      lastSeq = b._o.seq;
      if (b._del) dels.push({ op: 'delete', ball_id: b.id });
      else if (changed(b)) ops.push({ op: 'update', ball_id: b.id, ...vals(b) });
    }
    // new balls go in from the back so the positions of the earlier ones stay right; deletes last
    return ops.concat(ins.reverse(), dels);
  }
  function guard(fn) {
    if (!F.d || !dirtyCount()) return fn();
    confirmBox('Discard unsaved changes?', `You have ${dirtyCount()} change(s) in this innings that are not saved yet.`, 'Discard them', () => { closeModal(); fn(); }, true);
  }

  async function save() {
    const errs = F.draft.filter(b => !b._del && b.wicket === 'timed out' && !b.dismissed_id);
    if (errs.length) { toast('Choose the batter who was timed out', true); return; }
    const before = F.d.match.result;
    const d = await api('admin_fix_apply', { id: F.d.match.id, ops: buildOps(), note: F.note || '' });
    F.note = '';
    setData(d, true);
    if ((d.match.result || '') !== (before || '')) modal(`<h3>Saved - the result changed</h3><p>Before: <b>${esc(before || '-')}</b><br>Now: <b>${esc(d.match.result || '-')}</b></p><p>The points table and player stats use the new result. If this is not what you expected, use "Undo last saved change".</p><div class="row"><button class="btn" data-act="modal-close">OK</button></div>`);
    else toast('Saved ✓ ' + (d.match.result || ''));
    loadList().catch(() => {});
  }

  function historyModal() {
    const d = F.d;
    modal(`<h3>History of corrections</h3><p>Go back to how the match was just before any saved change. Going back can itself be undone.</p>
      <div class="hist">${d.history.map(h => `<div class="h"><div><b>${esc(h.note || 'Change')}</b><small>${esc(fmtDate(h.created_at))} · ${esc(h.who || 'admin')} · before: ${esc(h.score || '')}</small></div>
        <button class="btn light small" data-act="restore" data-id="${h.id}">Go back to before this</button></div>`).join('')}</div>
      <div class="row">${d.original ? `<button class="btn red" data-act="restore" data-id="${d.original.id}" data-orig="1">Restore the match as originally scored</button>` : ''}<button class="btn light" data-act="modal-close">Close</button></div>`);
  }

  /* ---------- events ---------- */
  document.addEventListener('change', e => {
    const t = e.target;
    if (t.id === 'tour') { run(loadList); return; }
    if (t.id === 'note') { F.note = t.value; return; }
    if (t.id === 'over-bowler' && t.value) {
      const id = +t.value;
      F.draft.filter(b => b.over === F.over && !b._del).forEach(b => { b.bowler_id = id; });
      draw(); return;
    }
    if (t.id === 'rp-from') {
      const id = +t.value, tm = F.d.teams.find(x => x.players.some(p => p.id === id));
      $('#rp-to').innerHTML = tm ? '<option value="">Choose…</option>' + tm.players.filter(p => p.id !== id).map(p => `<option value="${p.id}">${esc(p.name)}${p.xi ? '' : ' (squad)'}</option>`).join('') : '<option value="">Choose the wrong player first</option>';
      return;
    }
    if (t.dataset && t.dataset.f) {
      const b = F.draft[+t.dataset.i]; if (!b) return;
      setField(b, t.dataset.f, t.value); number(); draw();
    }
  });
  let qT = null;
  document.addEventListener('input', e => {
    if (e.target.id === 'q') { clearTimeout(qT); qT = setTimeout(() => run(loadList), 300); }
    if (e.target.id === 'note') F.note = e.target.value;
  });
  document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });
  document.addEventListener('click', e => {
    if (e.target.dataset && e.target.dataset.close && e.target.classList.contains('modal-bg')) { closeModal(); return; }
    const el = e.target.closest('[data-act]'); if (!el) return;
    const a = el.dataset.act, i = +el.dataset.i, b = F.draft[i];
    switch (a) {
      case 'modal-close': closeModal(); break;
      case 'confirm-yes': if (confirmFn) { const fn = confirmFn; confirmFn = null; fn(); } break;
      case 'open': guard(() => run(() => openMatch(+el.dataset.id))); break;
      case 'inn': if (i !== F.inn) guard(() => { F.inn = i; F.over = 0; makeDraft(); draw(); }); break;
      case 'over': F.over = +el.dataset.o; draw(); break;
      case 'warn': {
        const no = +el.dataset.inn, idx = F.d.innings.findIndex(x => x.no === no), ov = el.dataset.over;
        if (idx < 0) { const box = document.querySelector('.grid2'); if (box) box.scrollIntoView({ behavior: 'smooth' }); break; }
        const go = () => { if (idx !== F.inn) { F.inn = idx; makeDraft(); } if (ov !== '') F.over = +ov; draw(); };
        if (idx !== F.inn) guard(go); else go();
        break;
      }
      case 'swap': if (b && b.non_striker_id) { setField(b, 'batsman_id', String(b.non_striker_id)); draw(); } break;
      case 'swap-over': F.draft.filter(x => x.over === F.over && !x._del && x.non_striker_id).forEach(x => { setField(x, 'batsman_id', String(x.non_striker_id)); }); draw(); break;
      case 'add': F.draft.splice(i + 1, 0, newBall(b)); number(); draw(); break;
      case 'add-at': { const like = F.draft[i] || F.draft[F.draft.length - 1]; const nb = newBall(like); F.draft.splice(i, 0, nb); number(); F.over = nb.over; draw(); break; }
      case 'del': if (b._new) F.draft.splice(i, 1); else b._del = true; number(); draw(); break;
      case 'undel': b._del = false; number(); draw(); break;
      case 'reset': FIELDS.forEach(f => { b[f] = b._o[f]; }); number(); draw(); break;
      case 'discard': makeDraft(); F.note = ''; draw(); break;
      case 'save': run(save); break;
      case 'undo': guard(() => confirmBox('Undo the last saved change?', `This puts the match back to how it was before: <b>${esc((F.d.history[0] || {}).note || 'the last change')}</b>.`, 'Undo it', () => { closeModal(); run(async () => { setData(await api('admin_fix_undo', { id: F.d.match.id }), true); toast('Undone ✓'); loadList().catch(() => {}); }); })); break;
      case 'history': historyModal(); break;
      case 'pdf': run(async () => {
        const id = F.d.match.id, r = await api('admin_pdf_link', { id });
        const url = new URL('pdf.php?id=' + id + '&k=' + r.k, location.href).href;
        modal(`<h3>📄 Full scorecard PDF</h3><p>Every ball, every run and every over, with batting, bowling, extras, fall of wickets and partnerships. It is made fresh each time, so it always shows the latest corrections.</p>
          <div class="row"><a class="btn" href="${esc(url)}&dl=1">⬇ Download PDF</a><a class="btn light" href="${esc(url)}" target="_blank" rel="noopener">Open</a><button class="btn light" data-act="pdf-copy" data-url="${esc(url)}">🔗 Copy link for teams</button></div>
          <p class="small" style="margin-top:10px">Anyone with the link can view this one match's PDF - no login. Names written in non-English scripts print as ? in the PDF.</p>
          <div class="row"><button class="btn light" data-act="modal-close">Close</button></div>`);
      }); break;
      case 'pdf-copy': navigator.clipboard.writeText(el.dataset.url).then(() => toast('Link copied - paste it in WhatsApp or email ✓'), () => toast(el.dataset.url)); break;
      case 'restore': {
        const id = +el.dataset.id;
        guard(() => confirmBox(el.dataset.orig ? 'Restore the original scoring?' : 'Go back to before this change?', el.dataset.orig ? 'Every correction made on this desk is taken out and the match is exactly as the scorer left it. You can undo this too.' : 'The match goes back to how it was just before that change. You can undo this too.', 'Yes, go back',
          () => { closeModal(); run(async () => { setData(await api('admin_fix_undo', { id: F.d.match.id, snapshot_id: id }), true); toast('Done ✓'); loadList().catch(() => {}); }); }, true));
        break;
      }
      case 'replace': {
        const from = +$('#rp-from').value, to = +$('#rp-to').value, scope = +$('#rp-scope').value;
        const roles = [['bat', '#rp-bat'], ['bowl', '#rp-bowl'], ['field', '#rp-field']].filter(([, s]) => $(s).checked).map(([r]) => r);
        if (!from || !to) { toast('Choose the wrong player and the right player', true); break; }
        if (!roles.length) { toast('Tick batting, bowling or fielding', true); break; }
        guard(() => confirmBox('Replace this player?', `Every ball where <b>${esc(pname(from))}</b> appears (${roles.join(', ')}) in ${scope ? 'that innings' : 'the whole match'} will show <b>${esc(pname(to))}</b> instead.`, 'Replace',
          () => { closeModal(); run(async () => { setData(await api('admin_fix_replace', { id: F.d.match.id, from, to, innings_id: scope, roles }), true); toast('Player replaced ✓'); loadList().catch(() => {}); }); }));
        break;
      }
      case 'xi-remove': {
        const id = +el.dataset.id;
        guard(() => confirmBox('Remove from the match list?', `<b>${esc(el.dataset.name)}</b> does not appear in any ball. Remove them only if they did not play - this match will no longer count in their matches played.`, 'Remove',
          () => { closeModal(); run(async () => { setData(await api('admin_fix_xi_remove', { id: F.d.match.id, player_id: id }), true); toast('Removed ✓'); }); }, true));
        break;
      }
    }
  });
  window.addEventListener('beforeunload', e => { if (F.d && dirtyCount()) { e.preventDefault(); e.returnValue = ''; } });

  /* ---------- start ---------- */
  run(async () => {
    await loadList();
    const id = (window.FIX_BOOT && window.FIX_BOOT.match) || 0;
    if (id) await openMatch(id);
  });
})();
