<?php
/* Cric-Scorer - live score overlay for streamers: add overlay.php?m=ID as a browser source (1280x140, transparent) in OBS,
   Streamlabs or a phone streaming app. Polls the public match API every few seconds. Free. */
require_once __DIR__ . '/inc/core.php';
$id = (int)($_GET['m'] ?? 0);
$theme = preg_match('/^[a-z]+$/', (string)($_GET['theme'] ?? '')) ? $_GET['theme'] : 'dark';
header('X-Robots-Tag: noindex');
?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex">
<title>Cric-Scorer overlay</title>
<link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@700;800&family=Nunito:wght@700;800;900&display=swap" rel="stylesheet">
<style>
html,body{margin:0;background:transparent;font-family:Nunito,system-ui,sans-serif;color:#fff;overflow:hidden}
.bar{position:absolute;left:0;right:0;bottom:0;display:flex;align-items:stretch;height:96px;margin:0 20px 16px;border-radius:16px;overflow:hidden;box-shadow:0 10px 30px rgba(0,0,0,.45);background:rgba(10,30,22,.92);backdrop-filter:blur(6px)}
.bar.light{background:rgba(255,255,255,.94);color:#102a1e}
.team{display:flex;align-items:center;gap:12px;padding:0 20px;min-width:240px}
.team .b{width:44px;height:44px;border-radius:12px;display:grid;place-items:center;font-family:'Barlow Condensed',sans-serif;font-weight:800;font-size:20px;color:#fff;text-shadow:0 1px 2px rgba(0,0,0,.4)}
.team .n{font-weight:900;font-size:22px;letter-spacing:.3px}
.team .s{font-family:'Barlow Condensed',sans-serif;font-size:40px;font-weight:800;line-height:1}
.team .o{font-size:13px;font-weight:700;opacity:.75}
.mid{flex:1;display:flex;flex-direction:column;justify-content:center;gap:6px;padding:0 16px;border-left:1px solid rgba(255,255,255,.12);border-right:1px solid rgba(255,255,255,.12)}
.light .mid{border-color:rgba(0,0,0,.1)}
.row{display:flex;gap:14px;align-items:center;font-size:15px;font-weight:800;white-space:nowrap;overflow:hidden}
.row .k{opacity:.6;font-weight:700;font-size:12px;text-transform:uppercase;letter-spacing:.8px}
.balls{display:flex;gap:5px}
.ball{min-width:24px;height:24px;border-radius:50%;background:rgba(255,255,255,.14);display:grid;place-items:center;font-size:12px;font-weight:900;padding:0 4px}
.light .ball{background:rgba(0,0,0,.08)}
.ball.w{background:#e63946;color:#fff}.ball.b4{background:#1d7ad8;color:#fff}.ball.b6{background:#7b2cbf;color:#fff}.ball.ex{background:#f4a261;color:#222}
.live{display:inline-flex;align-items:center;gap:6px;background:#e63946;color:#fff;border-radius:999px;padding:2px 10px;font-size:12px;font-weight:900}
.live::before{content:"";width:7px;height:7px;border-radius:50%;background:#fff;animation:bl 1.2s infinite}
@keyframes bl{50%{opacity:.2}}
.brand{display:flex;align-items:center;padding:0 16px;white-space:nowrap;font-family:'Barlow Condensed',sans-serif;font-weight:800;font-size:18px;color:#b6e94a;letter-spacing:.5px}
.light .brand{color:#0d4a36}
.need{font-size:14px;font-weight:800;color:#b6e94a}.light .need{color:#0d4a36}
.pp{background:#ffd166;color:#4a3300;border-radius:8px;padding:1px 7px;font-size:11px;font-weight:900}
</style></head>
<body><div class="bar <?= $theme === 'light' ? 'light' : '' ?>" id="bar"><div class="brand">Cric-Scorer</div></div>
<script>
const ID = <?= $id ?>, esc = v => String(v ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const chip = l0 => { const bn = l0.endsWith('°'); const l = bn ? l0.slice(0, -1) : l0; let c = ''; if (l.includes('W')) c = 'w'; else if (l === '4') c = 'b4'; else if (l === '6') c = 'b6'; else if (/wd|nb|lb|b|RH|RO|TO/.test(l)) c = 'ex'; return `<span class="ball ${c}">${l === '0' ? '•' : esc(l)}${bn ? '↑' : ''}</span>`; };
async function tick() {
  try {
    const r = await fetch('api.php?action=match_get', { method: 'POST', headers: { 'X-Requested-With': 'CricScorer', 'Content-Type': 'application/json' }, body: JSON.stringify({ id: ID }) });
    const st = await r.json();
    if (st.error) { document.getElementById('bar').innerHTML = `<div class="brand">Cric-Scorer</div><div class="mid"><div class="row">${esc(st.error)}</div></div>`; return; }
    const m = st.match, team = id => (st.team1.id === id ? st.team1 : st.team2);
    const inn = st.innings[st.innings.length - 1];
    const badge = t => `<span class="b" style="background:${esc(t.color || '#0d4a36')}">${esc(String(t.short_name).slice(0, 4))}</span>`;
    let html = '';
    if (!inn) {
      html = `<div class="team">${badge(st.team1)}<div><div class="n">${esc(st.team1.name)}</div></div></div><div class="mid"><div class="row"><span class="k">${esc(m.title || 'Match')}</span>${m.toss_winner_id ? `<span>${esc(team(m.toss_winner_id).short_name)} won the toss, ${m.toss_decision === 'bat' ? 'batting' : 'bowling'} first</span>` : '<span>Starting soon</span>'}</div></div><div class="team">${badge(st.team2)}<div><div class="n">${esc(st.team2.name)}</div></div></div>`;
    } else {
      const bt = team(inn.batting_team_id), other = st.innings.find(i => i.batting_team_id !== inn.batting_team_id && !i.super);
      const bats = [inn.striker_id, inn.non_striker_id].filter(Boolean).map(id => { const b = inn.batting.find(x => x.id === id); return b ? `<span>${esc(b.name)} <b>${b.r}</b> (${b.b})${id === inn.striker_id ? '*' : ''}</span>` : ''; }).join('');
      const bw = inn.bowling.find(x => x.id === inn.bowler_id);
      const bpo = m.balls_per_over; let cur = Math.floor(inn.legal_balls / bpo); if (inn.legal_balls > 0 && inn.legal_balls % bpo === 0 && (!inn.bowler_id || inn.status === 'completed')) cur--;
      const balls = inn.timeline.filter(b => b.over_no === cur).map(b => chip(b.label)).join('');
      const line2 = m.result_text ? `<span class="need">🏆 ${esc(m.result_text)}</span>` : inn.target ? `<span class="need">Need ${inn.need} off ${inn.balls_left} · RRR ${Number(inn.rrr).toFixed(2)}</span>` : `<span class="k">CRR</span><span>${Number(inn.crr).toFixed(2)}</span><span class="k">Proj</span><span>${inn.projected}</span>`;
      html = `<div class="team">${badge(bt)}<div><div class="n">${esc(bt.short_name)}</div><div class="s">${inn.runs}/${inn.wickets}</div><div class="o">${inn.overs}${inn.max_overs >= 9999 ? "" : " / " + inn.max_overs} ov${inn.super ? ' · Super over' : ''}</div></div></div>
        <div class="mid"><div class="row">${m.status === 'live' ? '<span class="live">LIVE</span>' : ''}${inn.powerplay && inn.powerplay.live ? '<span class="pp">POWERPLAY</span>' : ''}${bats}${bw ? `<span class="k">Bowl</span><span>${esc(bw.name)} ${bw.w}-${bw.r} (${bw.o})</span>` : ''}</div>
        <div class="row">${line2}<span class="k">This over</span><span class="balls">${balls || '<span class="ball">-</span>'}</span></div></div>
        <div class="team" style="min-width:200px">${other ? `${badge(team(other.batting_team_id))}<div><div class="n">${esc(team(other.batting_team_id).short_name)}</div><div class="o">${other.runs}/${other.wickets} (${other.overs} ov)</div></div>` : `${badge(team(inn.bowling_team_id))}<div><div class="n">${esc(team(inn.bowling_team_id).short_name)}</div><div class="o">bowling</div></div>`}</div>`;
    }
    document.getElementById('bar').innerHTML = `<div class="brand">Cric-Scorer</div>` + html;
  } catch (e) { /* keep last */ }
}
tick(); setInterval(tick, 5000);
</script></body></html>
