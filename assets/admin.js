/* Cric-Scorer - admin portal: usage stats (totals only - no personal data), feature roll-out, updates, help desk, announcements, errors */
'use strict';
const Admin = (() => {
  const A = {};
  const TABS = [['stats', '📊 Stats'], ['users', '👥 Players'], ['roster', '🧢 Team players'], ['imports', '📥 Imports'], ['features', '🚦 Roll-out'], ['update', '⬆️ Updates'], ['help', '💬 Help desk'], ['news', '📣 News'], ['errors', '🐞 Errors']];
  let userQ = '', userPage = 0;
  let tab = 'stats', helpStatus = 'open';
  const num = n => Number(n || 0).toLocaleString('en-IN');

  function frame(body, sub = '') {
    return topbar('Admin', { back: true, sub: sub || 'Totals only - no personal details' }) + `<div class="content admin">
      <div class="adm-tabs">${TABS.map(([k, l]) => `<button class="${k === tab ? 'on' : ''}" onclick="Admin.tab('${k}')">${l}</button>`).join('')}</div>${body}</div>`;
  }
  A.tab = k => { tab = k; A.show(); };
  A.show = async (k) => {
    if (k) tab = k;
    if (!S.user || !S.user.is_admin) { mount(topbar('Admin', { back: true }) + '<div class="content"><div class="empty">Only the administrator can open this page.</div></div>', 'more'); return; }
    mount(frame(sk(4)), 'more');
    const fn = { stats, users, roster, imports, features, update, help, news, errors }[tab];
    await fn();
  };

  /* ---------- Stats ---------- */
  const tile = (n, l, hint = '') => `<div class="adm-tile"><b>${num(n)}</b><span>${l}</span>${hint ? `<i>${hint}</i>` : ''}</div>`;
  function bars30(obj, color) {
    const vals = Object.values(obj), max = Math.max(1, ...vals), keys = Object.keys(obj), total = vals.reduce((a, b) => a + b, 0);
    return `<div class="adm-bars" role="img" aria-label="Last 30 days, total ${total}">${keys.map((d, i) => `<div class="b" title="${d}: ${vals[i]}"><i style="height:${Math.round(vals[i] / max * 100)}%;background:${color}"></i></div>`).join('')}</div>
      <div class="adm-axis"><span>${fmtDate(keys[0], false)}</span><span>Last 30 days · ${num(total)}</span><span>Today</span></div>`;
  }
  function hbars(obj) {
    const e = Object.entries(obj); if (!e.length) return '<div class="small muted">Nothing yet</div>';
    const max = Math.max(1, ...e.map(x => x[1]));
    return e.map(([k, v]) => `<div class="adm-h"><span>${esc(k)}</span><div><i style="width:${Math.max(2, v / max * 100)}%"></i></div><b>${num(v)}</b></div>`).join('');
  }
  async function stats() {
    const d = await api('admin_stats'), u = d.users, m = d.matches, a = d.academy, g = d.games;
    mount(frame(`
      ${d.support.open || d.support.errors_week ? `<div class="adm-alert">${d.support.open ? `💬 <b>${d.support.open}</b> help message${d.support.open > 1 ? 's' : ''} waiting · <a href="javascript:Admin.tab('help')">Reply</a>` : ''} ${d.support.errors_week ? `🐞 <b>${d.support.errors_week}</b> error${d.support.errors_week > 1 ? 's' : ''} this week · <a href="javascript:Admin.tab('errors')">Check</a>` : ''}</div>` : ''}
      <div class="section"><h2>👥 Players</h2></div>
      <div class="adm-grid">${tile(u.total, 'Joined')}${tile(u.week, 'New this week')}${tile(u.active_day, 'Active today')}${tile(u.active_week, 'Active this week')}${tile(u.active_month, 'Active in 30 days')}${tile(u.google, 'Use Google sign-in')}${tile(u.private, 'Private profiles')}${tile(u.follows, 'Follows')}</div>
      <div class="card"><b>New players per day</b>${bars30(u.signups, 'var(--pitch-3)')}</div>
      <div class="grid2 adm-two"><div class="card"><b>Devices</b>${hbars(u.devices)}</div><div class="card"><b>App versions in use</b>${hbars(u.versions)}</div></div>
      <div class="section"><h2>🏏 Matches</h2></div>
      <div class="adm-grid">${tile(m.total, 'Matches')}${tile(m.live, 'Live now')}${tile(m.completed, 'Completed')}${tile(m.week, 'Created this week')}${tile(m.balls, 'Balls scored')}${tile(m.scorers, 'Players who scored')}${tile(m.tournaments, 'Tournaments')}${tile(m.teams, 'Custom teams')}${tile(m.private, 'Private matches')}${tile(m.dls, 'DLS results')}</div>
      <div class="card"><b>Matches created per day</b>${bars30(m.per_day, '#1c7ed6')}</div>
      <div class="card"><b>Match lengths</b>${hbars(m.formats)}</div>
      <div class="section"><h2>🎮 Gully Duel</h2></div>
      <div class="adm-grid">${tile(g.total, 'Games played')}${tile(g.online, 'Two-phone games')}${tile(g.local, 'One-phone games')}${tile(g.cpu, 'vs Computer')}${tile(g.live_now, 'Being played now')}${tile(g.players, 'Different players')}${tile(g.balls, 'Balls bowled')}</div>
      <div class="card"><b>Games per day</b>${bars30(g.per_day, '#7048e8')}</div>
      <div class="section"><h2>🎓 Cricket Academy</h2></div>
      <div class="adm-grid">${tile(a.learners, 'Learners')}${tile(a.passed, 'Lessons passed')}${tile(a.three_star, '3-star lessons')}${tile(a.attempts, 'Quiz attempts')}${tile(a.certified, 'Certified experts')}</div>
      <p class="small muted" style="text-align:center">Updated ${esc(d.generated)} · These are totals only. No names, usernames, emails or photos are shown here.</p>`), 'more');
  }

  /* ---------- Players: name, username, id and search ---------- */
  async function users() {
    const d = await api('admin_users', { q: userQ, page: userPage });
    const pages = Math.max(1, Math.ceil(d.total / d.per));
    mount(frame(`<form class="row" onsubmit="Admin.userSearch(event)"><input class="input grow" name="q" value="${esc(userQ)}" placeholder="Search name, username or ID" autocapitalize="none"><button class="btn sm">Search</button>${userQ ? `<button type="button" class="btn sm light" onclick="Admin.userSearchClear()">All</button>` : ''}</form>
      <div class="small muted" style="margin:10px 2px">${num(d.total)} player${d.total === 1 ? '' : 's'}${userQ ? ' matching' : ''} · newest first</div>
      ${d.users.length ? `<div class="card flush list">${d.users.map(u => `<div class="item" onclick="go('#/user/${u.id}')">
          <div class="grow"><div class="t">${esc(u.display_name)}${u.is_admin ? ' <span class="role-tag">admin</span>' : ''}${u.verified ? ' ✅' : ''}${u.is_private ? ' 🔒' : ''}</div>
            <div class="s">@${esc(u.username)} · ID ${u.id} · joined ${fmtDate(u.created_at, false)}${u.last_seen_at ? ' · seen ' + fmtDate(u.last_seen_at, false) : ''} · ${num(u.matches)} match${u.matches === 1 ? '' : 'es'}${u.google ? ' · Google' : ''}</div></div>
          <span class="chev">${I.chev}</span></div>`).join('')}</div>` : '<div class="card empty"><div class="big">👥</div>No players found.</div>'}
      ${pages > 1 ? `<div class="grid2" style="margin-top:10px"><button class="btn light" ${userPage === 0 ? 'disabled' : ''} onclick="Admin.userPage(-1)">← Newer</button><button class="btn light" ${userPage >= pages - 1 ? 'disabled' : ''} onclick="Admin.userPage(1)">Older →</button></div><div class="small muted" style="text-align:center;margin-top:6px">Page ${userPage + 1} of ${pages}</div>` : ''}`,
      'Players list'), 'more');
  }
  A.userSearch = e => { e.preventDefault(); userQ = formData(e.target).q.trim(); userPage = 0; users(); };
  A.userSearchClear = () => { userQ = ''; userPage = 0; users(); };
  A.userPage = d => { userPage = Math.max(0, userPage + d); users(); };

  /* ---------- Feature roll-out ---------- */
  async function features(data) {
    const d = data || await api('admin_features');
    mount(frame(`<p class="small muted">Switch new features on for <b>everyone</b>, or for <b>selected players</b> only while you try them out. You (the admin) can always use every feature.</p>
      ${d.features.map(f => `<div class="card adm-feat"><div class="row"><span style="font-size:30px">${f.emoji}</span><div class="grow"><b>${esc(f.name)}</b><div class="small muted">${esc(f.about)}</div></div></div>
        <div class="seg" style="margin:12px 0 0">${[['off', 'Off'], ['some', 'Selected players'], ['all', 'Everyone']].map(([k, l]) => `<button class="${f.mode === k ? 'on' : ''}" onclick="Admin.featMode('${f.key}','${k}')">${l}</button>`).join('')}</div>
        ${f.mode === 'some' ? `<form class="row" style="margin-top:12px" onsubmit="Admin.featAdd(event,'${f.key}')"><input class="input grow" name="u" placeholder="Type a username, e.g. aarush" autocapitalize="none" autocomplete="off" required><button class="btn sm">Add</button></form>
          <div class="adm-people">${f.people.length ? f.people.map(p => `<span class="chip on">@${esc(p.username)} <button aria-label="Remove" onclick="Admin.featRemove('${f.key}',${p.id})">✕</button></span>`).join('') : '<span class="small muted">Nobody added yet - only you can see it.</span>'}</div>
          <div class="small muted">${f.people.length} of ${num(f.total_users)} players have early access.</div>` : f.mode === 'all' ? '<div class="small muted" style="margin-top:10px">✅ Every player can use this now.</div>' : '<div class="small muted" style="margin-top:10px">⛔ Hidden for everyone except you.</div>'}
      </div>`).join('')}`), 'more');
  }
  A.featMode = (key, mode) => run(async () => { const d = await api('admin_feature_set', { feature: key, mode }); toast('Saved ✓'); features(d); });
  A.featAdd = (e, key) => { e.preventDefault(); const u = formData(e.target).u; run(async () => { const d = await api('admin_feature_set', { feature: key, add: u }); toast('Added ✓'); features(d); }, e.submitter); };
  A.featRemove = (key, id) => run(async () => { const d = await api('admin_feature_set', { feature: key, remove: id }); features(d); });

  /* ---------- Updates & roll-out of new versions ---------- */
  async function update() {
    mount(frame(`<div class="card"><div class="row"><div class="grow"><div class="small muted">This website is running</div><b style="font-size:26px;font-family:var(--display)">Version ${esc(window.CS_VERSION || '')}</b></div>
      <button class="btn" onclick="Admin.checkUpdate()">🔍 Check for updates</button></div></div><div id="updBox">${sk(2)}</div>`), 'more');
    const d = await api('admin_update_check').catch(e => ({ error: e.message }));
    renderUpdate(d);
  }
  function renderUpdate(d) {
    const box = $('#updBox'); if (!box) return;
    const last = d.last;
    box.innerHTML = `${d.error ? `<div class="adm-alert warn">⚠️ ${esc(d.error)}</div>` : ''}
      ${d.latest ? (d.newer ? `<div class="card adm-new"><div class="row"><span style="font-size:30px">🎉</span><div class="grow"><b>Version ${esc(d.latest)} is ready</b><div class="small muted">From GitHub · ${esc(d.repo)} (${esc(d.branch)})</div></div></div>
          ${d.changes.length ? d.changes.map(c => `<div class="adm-cl"><b>${esc(c.version)}</b> <span class="small muted">${esc(c.date)}</span><ul>${c.items.map(i => `<li>${esc(i)}</li>`).join('')}</ul></div>`).join('') : ''}
          ${d.writable ? `<button class="btn lime block" onclick="Admin.install('${esc(d.latest)}')">⬆️ Install version ${esc(d.latest)}</button>
            <div class="small muted" style="margin-top:8px">A backup is made first, so you can roll back. Your settings (config files), photos and data are never touched.</div>`
            : `<div class="adm-alert warn">The server can't write these files, so it can't update itself: ${esc(d.not_writable.join(', '))}. Upload the update zip in the Hostinger file manager instead.</div>`}</div>`
        : `<div class="card"><b>✅ You're up to date</b><div class="small muted">${d.latest === d.current ? `GitHub has version ${esc(d.latest)} - the same as this website.` : `This website (${esc(d.current)}) is newer than GitHub (${esc(d.latest)}). Push the code to GitHub first if you want other servers to get it.`}</div></div>`) : ''}
      <div class="card"><label class="switch"><span><b>📲 Ask everyone to update now</b><div class="small muted">Phones still on an older version reload to ${esc(d.current)} straight away instead of showing the "update" banner.</div></span>
        <input type="checkbox" ${d.force_version === d.current ? 'checked' : ''} onchange="Admin.force(this.checked)"><span class="knob"></span></label></div>
      ${last ? `<div class="card"><b>Last ${last.kind === 'rollback' ? 'roll back' : 'update'}</b><div class="small muted">${esc(last.from)} → ${esc(last.to)} · ${esc(last.at)} · ${num(last.files)} files</div></div>` : ''}
      <div class="section"><h2>Backups</h2></div>
      ${d.backups && d.backups.length ? `<div class="card flush list">${d.backups.map(b => `<div class="item"><div class="grow"><div class="t">Version ${esc(b.version)}</div><div class="s">${esc(b.at)} · ${(b.size / 1048576).toFixed(1)} MB</div></div>
          <button class="btn sm light" onclick="Admin.rollback('${esc(b.file)}','${esc(b.version)}')">↩️ Roll back</button></div>`).join('')}</div>`
        : '<div class="small muted">A backup is saved automatically every time you install an update (the newest 3 are kept).</div>'}
      ${d.zip_ok === false ? '<div class="adm-alert warn">This server is missing PHP\'s zip extension, so one-tap updates won\'t work. Ask your host to switch it on.</div>' : ''}`;
  }
  A.checkUpdate = () => run(async () => { const b = $('#updBox'); if (b) b.innerHTML = sk(2); renderUpdate(await api('admin_update_check')); });
  A.install = v => confirmSheet(`Install version ${v}?`, 'The website will update itself from GitHub. It takes under a minute. Players will see an "update" banner.', 'Install now', () => run(async () => {
    sheet(`<div style="text-align:center;padding:10px"><div style="font-size:40px">⬆️</div><h3>Installing ${esc(v)}…</h3><div class="sub">Making a backup, downloading and copying files. Please keep this screen open.</div>${sk(1)}</div>`, { locked: true });
    try {
      const r = await api('admin_update_install');
      sheet(`<div style="text-align:center;padding:10px"><div style="font-size:44px">✅</div><h3>Updated to ${esc(r.to)}</h3><div class="sub">${num(r.files)} files updated. Backup: ${esc(r.backup)}</div>
        <button class="btn block" onclick="App.updateApp()">Reload the app</button></div>`, { locked: true });
    } catch (e) { closeSheet(); throw e; }
  }), false);
  A.rollback = (f, v) => confirmSheet(`Roll back to ${v}?`, 'The website goes back to the code saved in this backup. Your data stays as it is.', 'Roll back', () => run(async () => {
    const r = await api('admin_update_rollback', { file: f });
    sheet(`<div style="text-align:center;padding:10px"><div style="font-size:44px">↩️</div><h3>Rolled back to ${esc(r.to)}</h3><button class="btn block" onclick="App.updateApp()">Reload the app</button></div>`, { locked: true });
  }));
  A.force = on => run(async () => { await api('admin_force_update', { on: on ? 1 : 0 }); toast(on ? 'Everyone will be moved to this version' : 'Players will see the update banner'); });

  /* ---------- Help desk ---------- */
  async function help() {
    const d = await api('admin_tickets', { status: helpStatus });
    mount(frame(`<p class="small muted">Messages from players. You see only the ticket number, device and app version - not who sent it. Your replies appear in the player's <b>Help & feedback</b> page.</p>
      <div class="seg">${[['open', 'Waiting'], ['answered', 'Answered'], ['closed', 'Closed']].map(([k, l]) => `<button class="${k === helpStatus ? 'on' : ''}" onclick="Admin.helpTab('${k}')">${l} (${d.counts[k]})</button>`).join('')}</div>
      ${d.tickets.length ? d.tickets.map(t => `<div class="card adm-ticket"><div class="row"><b>#${t.id}</b><span class="role-tag">${esc(t.kind_label)}</span><span class="grow"></span><span class="small muted">${esc(t.device || '')} · v${esc(t.app_version || '?')} · ${fmtDate(t.updated_at)}</span></div>
          ${t.msgs.map(m => `<div class="adm-msg ${m.from}"><div>${esc(m.body).replace(/\n/g, '<br>')}</div><span>${m.from === 'support' ? 'You' : 'Player'} · ${fmtDate(m.at)}</span></div>`).join('')}
          <form onsubmit="Admin.reply(event,${t.id})"><textarea class="input" name="message" rows="2" placeholder="Write a reply…"></textarea>
          <div class="grid2" style="margin-top:8px"><button class="btn">Send reply</button><button type="button" class="btn light" onclick="Admin.close(${t.id})">${t.status === 'closed' ? 'Reopen' : 'Close'}</button></div></form></div>`).join('')
        : '<div class="card empty"><div class="big">📭</div>No messages here.</div>'}`), 'more');
  }
  A.helpTab = k => { helpStatus = k; A.show('help'); };
  A.reply = (e, id) => { e.preventDefault(); const m = formData(e.target).message.trim(); if (!m) return toast('Type a reply first', true); run(async () => { await api('admin_ticket_reply', { id, message: m }); toast('Reply sent ✓'); help(); }, e.submitter); };
  A.close = id => run(async () => { await api('admin_ticket_reply', { id, status: helpStatus === 'closed' ? 'open' : 'closed' }); help(); });

  /* ---------- Announcements ---------- */
  async function news(data) {
    const d = data || await api('admin_announcements');
    mount(frame(`<div class="card"><b>New announcement</b><div class="small muted" style="margin-bottom:10px">Shows on every player's Home screen until they close it.</div>
      <form onsubmit="Admin.newsSave(event)"><div class="field"><input class="input" name="title" maxlength="120" placeholder="e.g. 🎮 Gully Duel is here!" required></div>
      <div class="field"><textarea class="input" name="body" rows="2" maxlength="600" placeholder="A few words for players (optional)"></textarea></div>
      <input type="hidden" name="kind" value="new"><div class="chips wrap" data-for="kind" style="margin-bottom:12px">${[['new', '🎉 New'], ['info', 'ℹ️ Info'], ['warn', '⚠️ Important']].map(([v, l], i) => `<button type="button" class="chip ${i === 0 ? 'on' : ''}" data-v="${v}" onclick="App.pickChip(this)">${l}</button>`).join('')}</div>
      <button class="btn block">📣 Publish</button></form></div>
      ${d.announcements.map(a => `<div class="card adm-news ${a.active ? '' : 'off'}"><div class="row"><div class="grow"><b>${esc(a.title)}</b><div class="small muted">${a.active ? '🟢 Showing' : '⚪ Hidden'} · ${fmtDate(a.created_at)}</div></div>
        <button class="btn sm light" onclick="Admin.newsToggle(${a.id},${a.active ? 0 : 1})">${a.active ? 'Hide' : 'Show'}</button><button class="btn sm light" style="color:var(--red)" onclick="Admin.newsDel(${a.id})">Delete</button></div>
        ${a.body ? `<div class="small" style="margin-top:6px">${esc(a.body)}</div>` : ''}</div>`).join('')}`), 'more');
  }
  A.newsSave = e => { e.preventDefault(); const f = formData(e.target); if (!f.kind) f.kind = 'new'; run(async () => { const d = await api('admin_announcement_save', f); toast('Published ✓'); news(d); }, e.submitter); };
  A.newsToggle = (id, active) => run(async () => news(await api('admin_announcement_toggle', { id, active })));
  A.newsDel = id => confirmSheet('Delete this announcement?', 'Players will no longer see it.', 'Delete', () => run(async () => { closeSheet(); news(await api('admin_announcement_toggle', { id, delete: 1 })); }));

  /* ---------- Error reports ---------- */
  async function errors(ref = '') {
    const d = await api('admin_errors', { ref });
    mount(frame(`<p class="small muted">When something goes wrong, players see a reference like <b>(ref 3FA9C1)</b>. Search it here. Names, emails and anything in quotes are blanked out.</p>
      <form class="row" onsubmit="Admin.errSearch(event)"><input class="input grow" name="ref" value="${esc(ref)}" placeholder="Reference, e.g. 3FA9C1" autocapitalize="characters"><button class="btn sm">Search</button>${ref ? `<button type="button" class="btn sm light" onclick="Admin.tab('errors')">All</button>` : ''}</form>
      <div class="adm-grid" style="margin-top:12px">${tile(d.week, 'Errors this week')}${d.top.map(t => tile(t.c, esc(t.action || 'app'))).join('')}</div>
      ${d.errors.length ? `<div class="card flush list">${d.errors.map(x => `<div class="item"><div class="grow"><div class="t"><b>${esc(x.ref)}</b> <span class="role-tag">${esc(x.source)}</span> ${esc(x.action || '')}</div>
          <div class="s" style="white-space:normal;word-break:break-word">${esc(x.message || '')}</div><div class="s">${esc(x.device || '')} · v${esc(x.app_version || '?')} · ${fmtDate(x.created_at)}</div></div></div>`).join('')}</div>
        <button class="btn light block" onclick="Admin.errClear()">Clear all error reports</button>` : '<div class="card empty"><div class="big">🎉</div>No errors' + (ref ? ' with that reference' : '') + '.</div>'}
      <p class="small muted">Reports older than one year are deleted automatically.</p>`), 'more');
  }
  A.errSearch = e => { e.preventDefault(); errors(formData(e.target).ref.trim()); };
  A.errClear = () => confirmSheet('Clear all error reports?', 'This deletes the list. New errors will still be recorded.', 'Clear', () => run(async () => { closeSheet(); await api('admin_errors_clear'); errors(); }));

  /* ---------- v1.34: Team players - correct anyone (name, team, age, gender, number, flat, who manages them) ---------- */
  let rosterQ = '', rosterTeam = 0, rosterData = null;
  async function roster(data) {
    const d = data || await api('admin_player_search', { q: rosterQ, team_id: rosterTeam }); rosterData = d;
    mount(frame(`<form class="row" onsubmit="Admin.rosterSearch(event)"><select class="input" name="team_id" style="max-width:46%" onchange="this.form.requestSubmit()"><option value="0">All custom teams</option>${d.teams.map(t => `<option value="${t.id}" ${+t.id === rosterTeam ? 'selected' : ''}>${esc(t.name)}</option>`).join('')}</select>
        <input class="input grow" name="q" value="${esc(rosterQ)}" placeholder="Name, flat or number"><button class="btn sm">Search</button></form>
      <div class="small muted" style="margin:10px 2px">${d.players.length}${d.players.length >= 300 ? '+' : ''} player${d.players.length === 1 ? '' : 's'} · tap one to correct it</div>
      ${d.players.length ? `<div class="card flush list">${d.players.map(p => `<div class="item" onclick="Admin.playerEdit(${p.id})">${badge({ short_name: p.short_name, color: p.color }, 'sm')}
          <div class="grow"><div class="t">${esc(p.name)} ${p.gender ? `<span class="role-tag">${p.gender}</span>` : ''}${p.age ? ` <span class="role-tag">${p.age}y</span>` : ''}</div>
          <div class="s">${esc(p.team)}${p.flat ? ' · ' + esc(p.flat) : ''}${p.mobile_pretty ? ' · ' + esc(p.mobile_pretty) : ''}${p.login ? ' · 🔑 @' + esc(p.login) : ''}${p.managed_by ? ' · 👨‍👩‍👧 @' + esc(p.managed_by) : ''}${+p.matches ? ' · ' + p.matches + ' matches' : ''}</div></div><span class="chev">${I.chev}</span></div>`).join('')}</div>`
        : '<div class="card empty">No players found.</div>'}`, 'Team players'), 'more');
  }
  A.rosterSearch = e => { e.preventDefault(); const f = formData(e.target); rosterQ = f.q.trim(); rosterTeam = +f.team_id || 0; roster(); };
  A.playerEdit = id => {
    const p = rosterData.players.find(x => +x.id === id); if (!p) return;
    const inp = (n, l, v, extra = '') => `<div class="field"><label>${l}</label><input class="input" name="${n}" value="${esc(v ?? '')}" ${extra}></div>`;
    sheet(`<h3>✏️ ${esc(p.name)}</h3><div class="sub">${+p.matches ? `Has played ${p.matches} matches - changes show everywhere.` : 'Not in any match yet.'}</div>
      <form onsubmit="Admin.playerSave(event,${id})">${inp('name', 'Name', p.name, 'required')}
      <div class="grid2"><div class="field"><label>Team</label><select class="input" name="team_id">${rosterData.teams.map(t => `<option value="${t.id}" ${+t.id === +p.team_id ? 'selected' : ''}>${esc(t.name)}</option>`).join('')}</select></div>
        <div class="field"><label>Role</label><select class="input" name="role">${['Batter', 'Bowler', 'All-rounder', 'Wicket-keeper'].map(r => `<option ${r === p.role ? 'selected' : ''}>${r}</option>`).join('')}</select></div></div>
      <div class="grid2"><div class="field"><label>Gender</label><select class="input" name="gender"><option value="">-</option><option value="M" ${p.gender === 'M' ? 'selected' : ''}>Male</option><option value="F" ${p.gender === 'F' ? 'selected' : ''}>Female</option></select></div>
        ${inp('age', 'Age (goes up yearly)', p.age, 'type="number" min="1" max="109"')}</div>
      ${inp('mobile', 'WhatsApp number', p.mobile_pretty, 'inputmode="tel"')}
      <div class="grid2">${inp('flat', 'Flat', p.flat)}${inp('home_city', 'City', p.home_city)}</div>
      <div class="grid2">${inp('home_state', 'State', p.home_state)}${inp('home_country', 'Country code', p.home_country, 'maxlength="2" placeholder="IN"')}</div>
      <div class="grid2">${inp('login', 'Own login (username)', p.login, 'autocapitalize="none" placeholder="none"')}${inp('managed_by', 'Managed by (username)', p.managed_by, 'autocapitalize="none" placeholder="none"')}</div>
      <button class="btn block">Save</button>${+p.matches ? '' : `<button type="button" class="btn light block" style="margin-top:8px;color:var(--red)" onclick="Admin.playerDelete(${id})">🗑️ Delete player</button>`}</form>`);
  };
  A.playerSave = (e, id) => { e.preventDefault(); run(async () => { const f = formData(e.target); const d = await api('admin_player_save', { id, ...f, _q: rosterQ, _team: rosterTeam }); closeSheet(); toast('Saved ✓'); roster(d); }, e.submitter); };
  A.playerDelete = id => confirmSheet('Delete this player?', 'They are removed from the team. Players who have played matches cannot be deleted.', 'Delete', () => run(async () => { await api('admin_player_delete', { id }); closeSheet(); toast('Deleted'); roster(); }));

  /* ---------- v1.34: Imports - one-tap undo ---------- */
  async function imports(data) {
    const d = data || await api('admin_import_list');
    mount(frame(`<p class="small muted">Every bulk import of players is kept here. <b>Undo</b> removes all the players it added and the logins / family links it made - possible until any of them has played a match.</p>
      ${d.imports.length ? d.imports.map(b => `<div class="card"><div class="row"><div class="grow"><b>${esc(b.label)}</b><div class="small muted">#${b.id} · ${fmtDate(b.at)} · by ${esc(b.by || '?')}</div></div>
          ${b.undone_at ? '<span class="role-tag">undone</span>' : ''}</div>
          <div class="small" style="margin:6px 0">${b.players} players · ${b.linked} logins linked · ${b.family} family links${b.undone_at ? ' · undone ' + fmtDate(b.undone_at) : ''}${b.played ? ` · <b>${b.played} have played</b>` : ''}</div>
          ${b.teams.length ? `<div class="small muted">${b.teams.map(t => `${esc(t.name)}: ${t.n}`).join(' · ')}</div>` : ''}
          ${b.accounts ? `<div class="small" style="margin-top:4px">👤 ${b.accounts} accounts · ${b.claimed} claimed by their players</div>` : ''}
          ${!b.undone_at && b.players && !d.imports.some(x => x.for_batch === b.id && !x.undone_at) ? `<button class="btn light block" style="margin-top:10px" onclick="Admin.makeAccounts(${b.id})">👤 Create player accounts</button>` : ''}
          ${b.undone_at ? '' : `<button class="btn ${b.played ? 'light' : ''} block" style="margin-top:10px;${b.played ? '' : 'background:var(--red);color:#fff'}" ${b.played ? 'disabled' : ''} onclick="Admin.importUndo(${b.id},${b.players || b.accounts},${b.accounts ? 1 : 0})">↩️ ${b.played ? 'Cannot undo - players have played' : b.accounts ? 'Undo - remove these accounts' : 'Undo this import'}</button>`}</div>`).join('')
        : '<div class="card empty"><div class="big">📥</div>No imports yet.</div>'}`, 'Imports'), 'more');
  }
  A.makeAccounts = id => confirmSheet('Create player accounts?', 'Each player without a login gets a registered account (username = name + last 4 digits of the number). The oldest person on each WhatsApp number claims it with a WhatsApp code; others on that number become their family profiles. This can be undone from this page.', 'Create accounts', () => run(async () => { const r = await api('admin_create_accounts', { id }); closeSheet(); toast(`${r.accounts} accounts created ✓`); imports(); }), false);
  A.importUndo = (id, n, acc) => confirmSheet(acc ? 'Remove these accounts?' : 'Undo this import?', acc ? `The ${n} accounts made for imported players are deleted (only while nobody has claimed theirs). The players stay in their teams.` : `All ${n} players it added are removed, with the logins and family links it made. Teams, matches and other players stay as they are.`, 'Undo import', () => run(async () => { const d = await api('admin_import_undo', { id }); closeSheet(); toast(`${d.removed} players removed ✓`); imports(d); }));

  return A;
})();
window.Admin = Admin;
