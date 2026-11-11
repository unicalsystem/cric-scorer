/* ---------- v1.25 Import players: paste a list (WhatsApp / Excel) or pick a CSV file ---------- */
const IMP_ROLES = [[/\b(wk|keeper|wicket)/i, 'Wicket-keeper'], [/\b(all|ar|allrounder|all-rounder)\b/i, 'All-rounder'], [/\bbowl/i, 'Bowler'], [/\bbat/i, 'Batter']];
/* One line -> {name, role, mobile, jersey_no, gender, birth_year}. Accepts "Ravi Kumar", "1. Ravi", "Ravi, Bowler, 98765 43210", tab/CSV columns, "Ravi (wk) #7", "Priya F 1994" */
function impParse(text, club) {
  const out = [];
  for (let raw of String(text || '').split(/\r?\n/)) {
    let line = raw.replace(/[\u200b-\u200f\u2028\u2029]/g, '').trim();
    if (!line) continue;
    line = line.replace(/^\s*(?:[-*•▪◦]|\d{1,2}[.)]|\(\d{1,2}\))\s*/, '');            // list bullets / numbering
    const cells = line.includes('\t') ? line.split('\t') : line.split(/\s*[,;|]\s*/);
    const first = cells.shift().trim(); if (!first) continue;
    const p = { name: first, role: '', mobile: '', jersey_no: '', gender: '', birth_year: '' };
    const rest = cells.map(c => c.trim()).filter(Boolean);
    // name cell may carry "(wk)" / "#7" / "- bowler"
    let m;
    if ((m = p.name.match(/\(([^)]+)\)/))) { rest.push(m[1]); p.name = p.name.replace(m[0], '').trim(); }
    if ((m = p.name.match(/#\s*(\d{1,3})\b/))) { p.jersey_no = m[1]; p.name = p.name.replace(m[0], '').trim(); }
    if ((m = p.name.match(/\s[-–]\s(.+)$/))) { rest.push(m[1]); p.name = p.name.replace(m[0], '').trim(); }
    if ((m = p.name.match(/(\+?\d[\d\s-]{8,}\d)/))) { rest.push(m[1]); p.name = p.name.replace(m[0], '').trim(); }
    for (const c of rest) {
      const d = c.replace(/[\s-]/g, '');
      if (/^\+?\d{10,13}$/.test(d)) { p.mobile = c; continue; }
      if (/^(19|20)\d\d$/.test(d) && club) { p.birth_year = d; continue; }
      if (/^\d{1,3}$/.test(d)) { p.jersey_no = d; continue; }
      if (club && /^(f|female|w|woman|women|girl|lady)$/i.test(d)) { p.gender = 'F'; continue; }
      if (club && /^(m|male|man|men|boy)$/i.test(d)) { p.gender = 'M'; continue; }
      if (/^(batter|bowler|all-rounder|wicket-keeper)$/i.test(c)) { p.role = c[0].toUpperCase() + c.slice(1).toLowerCase(); continue; }
      const r = IMP_ROLES.find(([re]) => re.test(c)); if (r) { p.role = r[1]; continue; }
    }
    p.name = p.name.replace(/\s{2,}/g, ' ').replace(/^[\s,.-]+|[\s,.-]+$/g, '');
    if (!p.name || /^(name|player|players|sno|s\.no|sl)$/i.test(p.name)) continue;      // header row
    if (!p.role) p.role = 'Batter';
    out.push(p);
  }
  return out.slice(0, 60);
}
App.importPlayers = teamId => {
  S.imp = { team: teamId, text: '', rows: [] };
  const club = !!(S.team && S.team.club);
  sheet(`<h3>📥 Import players</h3><div class="sub">Paste names from WhatsApp or Excel - one player per line. Optional after the name: role, WhatsApp number, jersey no${club ? ', M/F, birth year' : ''}.</div>
    <textarea class="input" id="imptxt" rows="6" placeholder="Ravi Kumar
Kiran Reddy, bowler
Suresh (wk), 98765 43210${club ? '\nPriya Sharma, F, 1994' : ''}" oninput="App.impPreview(this.value)" style="font-size:14px"></textarea>
    <div class="row" style="margin:8px 0 4px;gap:8px"><label class="btn light sm" style="cursor:pointer">📄 Choose CSV / text file<input type="file" accept=".csv,.txt,text/csv,text/plain" hidden onchange="App.impFile(this)"></label><span class="small muted">or paste above</span></div>
    <div id="impprev" class="small muted" style="margin:6px 0 10px">Nothing to import yet.</div>
    <button class="btn block" id="impgo" disabled onclick="App.impGo(this)">Import</button>`);
  setTimeout(() => $('#imptxt')?.focus(), 250);
};
App.impFile = inp => {
  const f = inp.files && inp.files[0]; if (!f) return;
  const r = new FileReader();
  r.onload = () => { let t = String(r.result || ''); if (t.charCodeAt(0) === 0xFEFF) t = t.slice(1); $('#imptxt').value = t; App.impPreview(t); };
  r.readAsText(f);
};
App.impPreview = text => {
  const club = !!(S.team && S.team.club);
  const rows = S.imp.rows = impParse(text, club);
  const box = $('#impprev'), go = $('#impgo'); if (!box) return;
  go.disabled = !rows.length; go.textContent = rows.length ? `Import ${plural(rows.length, 'player')}` : 'Import';
  if (!rows.length) { box.innerHTML = 'Nothing to import yet.'; return; }
  const have = new Set((S.team && S.team.players || []).map(p => p.name.trim().toLowerCase()));
  box.innerHTML = `<div class="card flush" style="max-height:220px;overflow:auto;border:1px solid var(--line);box-shadow:none"><table class="tbl" style="width:100%;font-size:13px">
    ${rows.map(p => `<tr style="${have.has(p.name.toLowerCase()) ? 'opacity:.5' : ''}"><td style="padding:6px 8px"><b>${esc(p.name)}</b>${have.has(p.name.toLowerCase()) ? ' <span class="small">(already in team)</span>' : ''}</td><td class="small muted" style="padding:6px 8px;white-space:nowrap">${esc(p.role)}${p.jersey_no ? ' · #' + esc(p.jersey_no) : ''}${p.mobile ? ' · 📱' : ''}${p.gender ? ' · ' + p.gender : ''}${p.birth_year ? ' · ' + p.birth_year : ''}</td></tr>`).join('')}</table></div>
    <div style="margin-top:6px">${rows.length} found · duplicates are skipped automatically</div>`;
};
App.impGo = btn => run(async () => {
  const r = await api('players_import', { team_id: S.imp.team, rows: S.imp.rows });
  closeSheet();
  const n = r.added.length, s = r.skipped.length;
  toast(n ? `Added ${plural(n, 'player')}${s ? ` · skipped ${s}` : ''} ✓` : (s ? 'Nothing added - ' + r.skipped[0].reason : 'Nothing added'), !n);
  if (s && n < S.imp.rows.length) setTimeout(() => sheet(`<h3>Skipped ${s}</h3><div class="card flush list">${r.skipped.map(x => `<div class="item" style="cursor:default"><div class="grow"><div class="t">${esc(x.name)}</div><div class="s">${esc(x.reason)}</div></div></div>`).join('')}</div><button class="btn block" onclick="closeSheet()">OK</button>`), 400);
  render();
}, btn);
const _viewTeamImp = viewTeam;
viewTeam = async id => {
  await _viewTeamImp(id);
  const pick = [...document.querySelectorAll('.content .grid2 button')].find(b => b.textContent.includes('Pick from teams')); if (!pick) return;
  const grid = pick.parentElement; grid.classList.remove('grid2'); grid.classList.add('grid3');
  grid.querySelector('button').innerHTML = I.plus + ' Add';
  pick.textContent = '📋 Pick';
  pick.insertAdjacentHTML('afterend', `<button class="btn light" onclick="App.importPlayers(${+id})">📥 Import</button>`);
};
ROUTES.forEach(r => { if (r[1] === _viewTeamImp) r[1] = viewTeam; });
