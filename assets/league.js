/* Cric-Scorer v1.22 - Club Leagues.
   A sports club switches on its own rule sheet (categories, groups, squad sizes, points for wins / runs / wickets,
   no-repeat rules). Everything below only appears for members of a club that has a league switched on. */
'use strict';

const LG = {};
LG.catName = (cfg, key) => (cfg && (cfg.categories || []).find(c => c.key === key) || {}).name || key || '';
LG.catLine = c => [c.min_age !== null && c.min_age !== '' ? 'from ' + c.min_age : '', c.max_age !== null && c.max_age !== '' ? 'up to ' + c.max_age : '', c.min_women ? `≥ ${c.min_women} women playing` : ''].filter(Boolean).join(' · ') || 'any age';

/* ---------- club page: rules card for the owner, league summary for members ---------- */
const _viewAcademyLG = viewAcademy;
viewAcademy = async id => {
  await _viewAcademyLG(id);
  const d = await api('academy_get', { id: +id }), a = d.academy;
  const content = document.querySelector('#app .content'); if (!content) return;
  let html = '';
  if (a.can_edit) html += `<div class="card" style="border:2px solid var(--pitch)"><div class="row"><span style="font-size:28px">🏟️</span><div class="grow"><b>Club league rules</b><div class="small muted">${d.league ? `${d.league.categories.length} categories · ${d.league.groups.length} groups · ${d.league.teams.length} teams` : d.has_rules ? 'Switched off' : 'Run your own league with categories, groups and a points system'}</div></div>
    <button class="btn sm" onclick="go('#/academy/${a.id}/league')">${d.league || d.has_rules ? 'Edit' : 'Set up'}</button></div></div>`;
  if (d.league) {
    const L = d.league, byGroup = {};
    L.teams.forEach(t => { (byGroup[t.league_group || 'No group'] = byGroup[t.league_group || 'No group'] || []).push(t); });
    const groups = L.groups.length ? L.groups : Object.keys(byGroup);
    html += `<div class="section"><h2>🏟️ ${esc(L.name)} league</h2><span class="small muted">${L.playing} playing + ${L.squad_size - L.playing} extra</span></div>
      <div class="card flush">${L.categories.map(c => `<div class="kv"><span>${esc(c.name)}</span><span class="muted small">${esc(LG.catLine(c))}</span></div>`).join('')}
        <div class="kv"><span>Points</span><span class="muted small">win ${L.points.win} · ${L.points.run_pts} per ${L.points.per_runs} runs · ${L.points.wicket_pts} per wicket</span></div></div>
      ${groups.length ? `<div class="section"><h2>Groups & teams</h2></div><div class="card flush list">${groups.map(g => { const ts = byGroup[g] || []; return `<div class="item" style="align-items:flex-start"><span class="avatar">${esc(g.replace(/[^A-Za-z0-9]/g, '').slice(-2) || 'G')}</span><div class="grow"><div class="t">${esc(g)}</div><div class="s">${ts.length ? ts.map(t => `<a class="link" href="#/team/${t.id}">${esc(t.name)}</a> <span class="muted">(${esc(LG.catName(L, t.league_cat))}, ${t.players})</span>`).join(' · ') : 'no teams yet'}</div></div></div>`; }).join('')}</div>` : ''}
      ${L.tournaments.length ? `<div class="card flush list">${L.tournaments.map(t => `<div class="item" onclick="go('#/tournament/${t.id}')"><span style="font-size:24px">🏆</span><div class="grow"><div class="t">${esc(t.name)}</div></div><span class="chev">${I.chev}</span></div>`).join('')}</div>` : ''}
      ${a.can_edit ? `<div class="grid2" style="margin-bottom:12px"><button class="btn light" onclick="go('#/team/new')">➕ Add a team</button><button class="btn" onclick="S.lgNewClub='${a.id}';go('#/tournament/new')">🏆 New league tournament</button></div>` : ''}`;
  }
  if (html) { const anchor = content.querySelector('.section') || content.firstElementChild; (anchor || content).insertAdjacentHTML(anchor ? 'beforebegin' : 'afterbegin', html); }
};
ROUTES.forEach(r => { if (r[1] === _viewAcademyLG) r[1] = viewAcademy; });

/* ---------- rule sheet editor (club owner only) ---------- */
async function viewLeagueRules(id) {
  mount(topbar('League rules', { back: true }) + `<div class="content">${sk(4)}</div>`, 'more');
  let d; try { d = await api('academy_league_get', { id: +id }); } catch (e) { toast(e.message, true); return go('#/academy/' + id); }
  S.lg = { id: +id, cfg: d.league, club: d.club, teams: d.teams };
  LG.drawRules();
}
LG.drawRules = () => {
  const { cfg, club, teams, id } = S.lg, P = cfg.points;
  const num = (name, v, extra = '') => `<input class="input" type="number" inputmode="numeric" name="${name}" value="${v === null || v === undefined ? '' : v}" ${extra}>`;
  const cats = cfg.categories.map((c, i) => `<div class="card flush" style="padding:10px 12px;margin-bottom:8px"><div class="row"><input class="input grow" placeholder="Category name e.g. Under 20" value="${esc(c.name)}" oninput="S.lg.cfg.categories[${i}].name=this.value"><button type="button" class="icon-btn" aria-label="Remove" onclick="S.lg.cfg.categories.splice(${i},1);LG.drawRules()">✕</button></div>
      <div class="grid3" style="margin-top:8px"><div class="field"><label>Min age</label><input class="input" type="number" inputmode="numeric" placeholder="any" value="${c.min_age ?? ''}" oninput="S.lg.cfg.categories[${i}].min_age=this.value===''?null:+this.value"></div>
      <div class="field"><label>Max age</label><input class="input" type="number" inputmode="numeric" placeholder="any" value="${c.max_age ?? ''}" oninput="S.lg.cfg.categories[${i}].max_age=this.value===''?null:+this.value"></div>
      <div class="field"><label>Min women playing</label><input class="input" type="number" inputmode="numeric" value="${c.min_women || 0}" oninput="S.lg.cfg.categories[${i}].min_women=+this.value||0"></div></div></div>`).join('');
  const pairs = [];
  cfg.categories.forEach((a, i) => cfg.categories.slice(i + 1).forEach(b => pairs.push([a, b])));
  const nr = pairs.map(([a, b]) => { const on = (cfg.no_repeat || []).some(p => (p[0] === a.key && p[1] === b.key) || (p[0] === b.key && p[1] === a.key)); return `<label class="switch" style="margin-top:8px"><span class="grow">${esc(a.name)} ↔ ${esc(b.name)}</span><input type="checkbox" ${on ? 'checked' : ''} onchange="LG.pair('${a.key}','${b.key}',this.checked)"><span class="knob"></span></label>`; }).join('');
  mount(topbar('League rules', { back: true, sub: club.name }) + `<form class="content" onsubmit="LG.saveRules(event)">
    <label class="switch" style="margin-bottom:12px"><div class="grow"><b>League switched on</b><div class="small muted">Only club members see these rules, the club's teams, groups and league tournaments.</div></div><input type="checkbox" name="enabled" ${cfg.enabled ? 'checked' : ''}><span class="knob"></span></label>

    <div class="section"><h2>Categories</h2><button type="button" class="btn sm light" onclick="S.lg.cfg.categories.push({key:'',name:'',min_age:null,max_age:null,min_women:0});LG.drawRules()">+ Add</button></div>
    <div class="small muted" style="margin:-4px 0 8px">Age is counted from the player's birth year. "Min women playing" makes a category mixed.</div>
    ${cats || '<div class="card empty small">Add at least one category.</div>'}

    <div class="section"><h2>Teams</h2></div>
    <div class="card"><div class="grid2"><div class="field"><label>Players in a squad</label>${num('squad_size', cfg.squad_size)}</div><div class="field"><label>Playing per side</label>${num('playing', cfg.playing)}</div></div>
      <div class="field"><label>Groups (one per line) - e.g. towers, blocks, departments</label><textarea class="input" name="groups" rows="4" placeholder="Tower G&#10;Tower H&#10;Tower I">${esc((cfg.groups || []).join('\n'))}</textarea></div>
      <div class="field"><label>Max teams per group</label>${num('max_teams_per_group', cfg.max_teams_per_group)}</div>
      <div class="small muted">Each group fields at most one team per category, and teams only play other groups' teams of the same category.</div></div>

    <div class="section"><h2>Points</h2></div>
    <div class="card"><div class="grid3"><div class="field"><label>Win</label>${num('p_win', P.win)}</div><div class="field"><label>Tie</label>${num('p_tie', P.tie)}</div><div class="field"><label>No result</label>${num('p_no_result', P.no_result)}</div></div>
      <div class="grid3"><div class="field"><label>Every … runs</label>${num('p_per_runs', P.per_runs, 'min="1"')}</div><div class="field"><label>… gives points</label>${num('p_run_pts', P.run_pts)}</div><div class="field"><label>Per wicket</label>${num('p_wicket_pts', P.wicket_pts)}</div></div>
      <div class="field"><label>Which wickets count</label><select class="input" name="wicket_mode"><option value="all" ${cfg.wicket_mode !== 'bowler' ? 'selected' : ''}>All dismissals while bowling (run outs too)</option><option value="bowler" ${cfg.wicket_mode === 'bowler' ? 'selected' : ''}>Bowler's wickets only</option></select></div>
      <div class="small muted">Run points are per batter, rounded down: with "every 10 runs = 2 pts", a batter on 27 earns 4 pts. Extras do not count.</div></div>

    ${pairs.length ? `<div class="section"><h2>No repeating players</h2></div><div class="card"><div class="small muted" style="margin-bottom:4px">Within one group, a player cannot be in both of these teams:</div>${nr}</div>` : ''}

    ${teams.length ? `<div class="section"><h2>Club teams</h2><span class="small muted">${teams.length}</span></div><div class="card flush list">${teams.map(t => `<div class="item" onclick="go('#/team/${t.id}')">${badge(t, 'sm')}<div class="grow"><div class="t">${esc(t.name)}</div><div class="s">${esc(t.league_group || 'no group')} · ${esc(LG.catName(cfg, t.league_cat) || 'no category')} · ${plural(+t.players, 'player')}</div></div><span class="chev">${I.chev}</span></div>`).join('')}</div>` : ''}
    <button class="btn block" style="margin:8px 0 20px">Save rules</button></form>`, 'more');
};
LG.pair = (a, b, on) => { const c = S.lg.cfg; c.no_repeat = (c.no_repeat || []).filter(p => !((p[0] === a && p[1] === b) || (p[0] === b && p[1] === a))); if (on) c.no_repeat.push([a, b]); };
LG.saveRules = e => {
  e.preventDefault(); const f = formData(e.target), c = S.lg.cfg;
  const league = { enabled: f.enabled ? 1 : 0, categories: c.categories.map(x => ({ ...x, key: x.key || x.name.toLowerCase().replace(/[^a-z0-9]+/g, '_') })), squad_size: +f.squad_size, playing: +f.playing,
    points: { win: +f.p_win, tie: +f.p_tie, no_result: +f.p_no_result, per_runs: +f.p_per_runs, run_pts: +f.p_run_pts, wicket_pts: +f.p_wicket_pts }, wicket_mode: f.wicket_mode,
    no_repeat: c.no_repeat || [], groups: String(f.groups || '').split('\n').map(s => s.trim()).filter(Boolean), max_teams_per_group: +f.max_teams_per_group };
  run(async () => { const d = await api('academy_league_save', { id: S.lg.id, league }); S.lg.cfg = d.league; S.lg.teams = d.teams; toast(league.enabled ? 'League rules saved ✓' : 'Saved - league is switched off'); go('#/academy/' + S.lg.id); }, e.submitter);
};
ROUTES.unshift([/^#\/academy\/(\d+)\/league$/, viewLeagueRules]);

/* ---------- team form: club / group / category ---------- */
const _viewTeamFormLG = viewTeamForm;
viewTeamForm = async id => {
  await _viewTeamFormLG(id);
  const form = document.querySelector('form.content'); if (!form) return;
  let clubs = []; try { clubs = (await api('tournaments_list', {})).my_clubs || []; } catch (e) { /* none */ }
  if (!clubs.length) return;
  const t = id && S.team && S.team.team.id === +id ? S.team.team : {};
  S.lgClubs = clubs;
  const cur = clubs.find(c => c.id === +t.academy_id) || null;
  const opts = (list, v, label) => `<option value="">${label}</option>` + list.map(x => `<option value="${esc(x.v)}" ${x.v === v ? 'selected' : ''}>${esc(x.l)}</option>`).join('');
  form.querySelector('.card:last-of-type').insertAdjacentHTML('afterend', `<div class="card" id="lgcard"><b>🏟️ Club league</b><div class="small muted" style="margin:2px 0 10px">Attach this team to your club's league, pick its group and category.</div>
    <div class="field"><label>Club</label><select class="input" name="academy_id" onchange="LG.teamClub(this.value)">${opts(clubs.map(c => ({ v: String(c.id), l: c.name })), cur ? String(cur.id) : '', '- not in a club league -')}</select></div>
    <div id="lgfields">${cur ? LG.teamFields(cur, t) : ''}</div></div>`);
};
LG.teamFields = (c, t) => {
  const L = c.league, opts = (list, v, label) => `<option value="">${label}</option>` + list.map(x => `<option value="${esc(x.v)}" ${x.v === v ? 'selected' : ''}>${esc(x.l)}</option>`).join('');
  return `<div class="grid2"><div class="field"><label>Group</label>${L.groups.length ? `<select class="input" name="league_group">${opts(L.groups.map(g => ({ v: g, l: g })), t.league_group || '', '- pick -')}</select>` : `<input class="input" name="league_group" value="${esc(t.league_group || '')}" placeholder="e.g. Tower G">`}</div>
    <div class="field"><label>Category</label><select class="input" name="league_cat">${opts(L.categories.map(x => ({ v: x.key, l: x.name })), t.league_cat || '', '- pick -')}</select></div></div>
    <div class="small muted">${L.playing} playing + ${L.squad_size - L.playing} extra per team${L.categories.some(x => x.min_women) ? ' · mixed teams need women in the playing side' : ''}</div>`;
};
LG.teamClub = v => { const c = (S.lgClubs || []).find(x => String(x.id) === v); document.getElementById('lgfields').innerHTML = c ? LG.teamFields(c, {}) : ''; };
ROUTES.forEach(r => { if (r[1] === _viewTeamFormLG) r[1] = viewTeamForm; });

/* ---------- team page: club card with rule checks ---------- */
const _viewTeamLG = viewTeam;
viewTeam = async id => {
  await _viewTeamLG(id);
  const d = S.team; if (!d || !d.club || d.team.id !== +id) return;
  const c = d.club, content = document.querySelector('#app .content'); if (!content) return;
  content.insertAdjacentHTML('afterbegin', `<div class="card" style="border-left:4px solid var(--pitch)"><div class="row"><span style="font-size:26px">🏟️</span><div class="grow"><b><a class="link" href="#/academy/${c.id}">${esc(c.name)}</a></b><div class="small muted">${esc(c.group || 'no group')} · ${esc(c.cat || 'no category')} · ${d.players.length}/${c.squad_size} in squad · ${c.playing} play${c.min_women ? ` · ≥ ${c.min_women} women` : ''}</div></div></div>
    ${c.problems.length ? `<div class="small" style="margin-top:8px;color:var(--red)">${c.problems.map(p => '⚠️ ' + esc(p)).join('<br>')}</div>` : `<div class="small" style="margin-top:8px;color:var(--pitch)">✅ Squad follows the club rules</div>`}</div>`);
};
ROUTES.forEach(r => { if (r[1] === _viewTeamLG) r[1] = viewTeam; });

/* ---------- player form: gender + birth year for club teams ---------- */
const _playerFormLG = App.playerForm;
App.playerForm = (pid, teamId) => {
  _playerFormLG(pid, teamId);
  const club = S.team && S.team.club; if (!club) return;
  const p = pid ? (S.team.players.find(x => +x.id === pid) || S.player?.player) : null;
  const country = document.querySelector('.sheet form [name=country]'); if (!country) return;
  country.closest('.field').insertAdjacentHTML('beforebegin', `<div class="grid2"><div class="field"><label>Gender</label><select class="input" name="gender"><option value="">-</option>${[['M', 'Man'], ['F', 'Woman'], ['O', 'Other']].map(([v, l]) => `<option value="${v}" ${p?.gender === v ? 'selected' : ''}>${l}</option>`).join('')}</select></div>
    <div class="field"><label>Birth year</label><input class="input" name="birth_year" type="number" inputmode="numeric" placeholder="e.g. 2008" value="${esc(p?.birth_year || '')}"></div></div>
    <div class="small muted" style="margin:-6px 0 10px">Needed for ${esc(club.name)} league categories.</div>`);
};

/* ---------- tournament form: club league + visibility ---------- */
const _viewTournamentFormLG = viewTournamentForm;
viewTournamentForm = async id => {
  await _viewTournamentFormLG(id);
  const form = document.querySelector('form.content'); if (!form) return;
  let clubs = []; try { clubs = (await api('tournaments_list', {})).my_clubs || []; } catch (e) { /* none */ }
  if (!clubs.length) return;
  S.lgClubs = clubs;
  const t = id && S.tour && S.tour.tournament.id === +id ? S.tour.tournament : {};
  const pre = S.lgNewClub; S.lgNewClub = null;
  const cur = String(t.academy_id || pre || '');
  form.querySelector('.card').insertAdjacentHTML('afterend', `<div class="card"><b>🏟️ Club league</b><div class="small muted" style="margin:2px 0 10px">Use your club's categories, groups and points system for this tournament.</div>
    <div class="field"><label>Club</label><select class="input" name="academy_id" onchange="LG.tourClub(this.value)">${'<option value="">- ordinary tournament -</option>' + clubs.map(c => `<option value="${c.id}" ${String(c.id) === cur ? 'selected' : ''}>${esc(c.name)}</option>`).join('')}</select></div>
    <div class="field"><label>Who can see it</label><select class="input" name="visibility"><option value="public" ${t.visibility !== 'club' ? 'selected' : ''}>Everyone (read-only)</option><option value="club" ${t.visibility === 'club' ? 'selected' : ''}>Club members only</option></select></div>
    <div class="small muted" id="lgtourhint"></div></div>`);
  LG.tourClub(cur);
};
LG.tourClub = v => {
  const c = (S.lgClubs || []).find(x => String(x.id) === v), hint = document.getElementById('lgtourhint');
  if (hint) hint.textContent = c ? `Fixtures: every team plays the other groups' teams of its category. Points: win ${c.league.points.win}, ${c.league.points.run_pts} per ${c.league.points.per_runs} runs, ${c.league.points.wicket_pts} per wicket.` : '';
  // only club teams can be in a club tournament - dim the others
  document.querySelectorAll('.plist .pbtn[data-tid]').forEach(b => { const team = (S.tteams || []).find(x => x.id === +b.dataset.tid); const ok = !c || (team && +team.academy_id === c.id && team.league_cat); b.style.opacity = ok ? '' : '.35'; });
};
ROUTES.forEach(r => { if (r[1] === _viewTournamentFormLG) r[1] = viewTournamentForm; });

/* ---------- tournament page: league points, group standings, category chips on matches ---------- */
const _viewTournamentLG = viewTournament;
viewTournament = async id => {
  await _viewTournamentLG(id);
  const d = S.tour; if (!d || !d.league || !d.league_points) return;
  const L = d.league, LP = d.league_points, content = document.querySelector('#app .content'); if (!content) return;
  const sub = document.querySelector('.topbar .sub'); if (sub) sub.textContent = '🏟️ ' + L.club_name + ' league' + (d.tournament.visibility === 'club' ? ' · members only' : '');
  if (S.tTab === 'points') {
    const seg = content.querySelector('.seg'); let html = '';
    const cats = L.categories.filter(c => LP.teams.some(t => t.cat === c.key)).concat(LP.teams.some(t => !t.cat) ? [{ key: null, name: 'Other teams' }] : []);
    html += `<div class="card flush" style="margin-bottom:12px"><div class="kv" style="border-top:0"><span>🏆 Group standings</span><span class="muted small">all teams added up</span></div><table class="tbl"><tr><th>Group</th><th>P</th><th>W</th><th>Match</th><th>Runs</th><th>Wkts</th><th>Total</th></tr>
      ${LP.groups.map((g, i) => `<tr><td style="white-space:nowrap"><b>${i + 1}. ${esc(g.group)}</b><div class="how">${plural(g.teams, 'team')}</div></td><td>${g.p}</td><td>${g.w}</td><td>${g.match_pts}</td><td>${g.run_pts}</td><td>${g.wkt_pts}</td><td><b style="font-size:16px">${g.pts}</b></td></tr>`).join('')}</table></div>`;
    cats.forEach(c => { const rows = LP.teams.filter(t => t.cat === c.key); if (!rows.length) return; html += `<div class="card flush" style="margin-bottom:12px"><div class="kv" style="border-top:0"><span>${esc(c.name)}</span><span class="muted small">${plural(rows.length, 'team')}</span></div><table class="tbl"><tr><th>Team</th><th>P</th><th>W</th><th>Match</th><th>Runs</th><th>Wkts</th><th>Pts</th></tr>
      ${rows.map((r, i) => `<tr onclick="go('#/team/${r.id}')"><td style="white-space:nowrap"><div class="row" style="gap:6px"><span class="muted small">${i + 1}</span>${badge(r, 'sm')}<div><b>${esc(r.short_name)}</b><div class="how">${esc(r.group || '')}</div></div></div></td><td>${r.p}</td><td>${r.w}</td><td>${r.match_pts}</td><td>${r.run_pts}<div class="how">${r.runs}r</div></td><td>${r.wkt_pts}<div class="how">${r.wkts}w</div></td><td><b style="font-size:16px">${r.pts}</b></td></tr>`).join('')}</table></div>`; });
    html += `<div class="small muted">Win ${L.points.win} · tie ${L.points.tie} · no result ${L.points.no_result} · every ${L.points.per_runs} runs by a batter ${L.points.run_pts} pts · each wicket ${L.points.wicket_pts} pts${L.wicket_mode === 'bowler' ? " (bowler's wickets)" : ''}.</div>`;
    // replace the ordinary table
    let n = seg.nextElementSibling; while (n) { const x = n; n = n.nextElementSibling; x.remove(); }
    seg.insertAdjacentHTML('afterend', html);
  } else if (S.tTab === 'teams') {
    content.querySelectorAll('.item .s').forEach((el, i) => { const t = d.teams[i]; if (t && t.league_cat) el.textContent = `${t.league_group || ''} · ${LG.catName(L, t.league_cat)} · ${plural(+t.players, 'player')}`; });
  }
};
ROUTES.forEach(r => { if (r[1] === _viewTournamentLG) r[1] = viewTournament; });

/* ---------- playing-side picker: show the club rule and live counts ---------- */
const _xiSetupLG = xiSetup;
xiSetup = () => {
  let h = _xiSetupLG();
  const st = S.m, m = st.match; if (!m.tournament || !S.squads || S.squads.mid !== m.id) return h;
  const team = S.squads[S.xiTeam]; const c = team && team.club; if (!c || !c.cat) return h;
  const sel = S.xiSel[S.xiTeam]; const women = team.players.filter(p => sel.has(+p.id) && p.gender === 'F').length;
  const need = c.min_women ? ` · women ${women}/${c.min_women}${women >= c.min_women ? ' ✓' : ''}` : '';
  return h.replace('<div class="seg">', `<div class="card" style="padding:10px 12px;margin-bottom:10px;border-left:4px solid var(--pitch)"><b>🏟️ ${esc(c.name)}</b> · ${esc(c.cat)} · ${esc(c.group || '')}<div class="small muted">${c.playing} playing${need}${c.problems && c.problems.length ? ' · ⚠️ ' + esc(c.problems[0]) : ''}</div></div><div class="seg">`);
};
