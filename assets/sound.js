/* Cric-Scorer by Aaryush - stadium sounds (synthesised, no audio files) + spoken commentary */
'use strict';
const Sound = (() => {
  const get = k => { try { return localStorage.getItem(k); } catch (e) { return null; } };
  const put = (k, v) => { try { localStorage.setItem(k, v); } catch (e) { /* private mode */ } };
  const st = { sfx: get('cs_sfx') !== '0', voice: get('cs_voice') !== '0', ctx: null, master: null, noise: null, primed: false, unlocked: false };
  const hasSpeech = 'speechSynthesis' in window;

  /* ---------- Audio plumbing ---------- */
  function ctx() {
    if (!st.ctx) {
      const C = window.AudioContext || window.webkitAudioContext;
      if (!C) return null;
      st.ctx = new C();
      const comp = st.ctx.createDynamicsCompressor();
      comp.threshold.value = -14; comp.ratio.value = 4;
      st.master = st.ctx.createGain(); st.master.gain.value = 0.9;
      st.master.connect(comp); comp.connect(st.ctx.destination);
      const len = st.ctx.sampleRate * 3, buf = st.ctx.createBuffer(1, len, st.ctx.sampleRate), d = buf.getChannelData(0);
      for (let i = 0; i < len; i++) d[i] = Math.random() * 2 - 1;
      st.noise = buf;
    }
    if (st.ctx.state === 'suspended') st.ctx.resume();
    return st.ctx;
  }
  function unlock() {
    ctx(); st.unlocked = true;
    if (hasSpeech && !st.primed) { const u = new SpeechSynthesisUtterance(' '); u.volume = 0; speechSynthesis.speak(u); st.primed = true; }
  }
  // Browsers only allow sound after the user touches the page
  ['pointerdown', 'keydown', 'touchend'].forEach(e => window.addEventListener(e, unlock, { passive: true }));

  function noise(t, dur, { type = 'bandpass', freq = 1000, q = 1, gain = 1, attack = 0.002, to = null } = {}) {
    const c = st.ctx, src = c.createBufferSource(), f = c.createBiquadFilter(), g = c.createGain();
    src.buffer = st.noise; src.loop = true;
    f.type = type; f.frequency.setValueAtTime(freq, t); f.Q.value = q;
    if (to) f.frequency.exponentialRampToValueAtTime(to, t + attack + dur);
    g.gain.setValueAtTime(0.0001, t);
    g.gain.exponentialRampToValueAtTime(Math.max(gain, 0.0002), t + attack);
    g.gain.exponentialRampToValueAtTime(0.0001, t + attack + dur);
    src.connect(f); f.connect(g); g.connect(st.master);
    src.start(t, Math.random() * 2); src.stop(t + attack + dur + 0.05);
  }
  function tone(t, freq, dur, { type = 'sine', gain = 0.5, to = null, attack = 0.003 } = {}) {
    const c = st.ctx, o = c.createOscillator(), g = c.createGain();
    o.type = type; o.frequency.setValueAtTime(freq, t);
    if (to) o.frequency.exponentialRampToValueAtTime(to, t + dur);
    g.gain.setValueAtTime(0.0001, t);
    g.gain.exponentialRampToValueAtTime(gain, t + attack);
    g.gain.exponentialRampToValueAtTime(0.0001, t + dur);
    o.connect(g); g.connect(st.master); o.start(t); o.stop(t + dur + 0.05);
  }

  /* ---------- Sound effects ---------- */
  function bat(t, p = 1) {               // willow on leather: sharp crack + woody ring + low thump
    noise(t, 0.035 + 0.03 * p, { freq: 2600, q: 2.2, gain: 0.9 * p });
    noise(t, 0.09 + 0.05 * p, { freq: 950, q: 7, gain: 0.45 * p });
    tone(t, 1250, 0.06, { type: 'triangle', gain: 0.25 * p, to: 900 });
    tone(t, 170, 0.14, { gain: 0.6 * p, to: 80 });
  }
  function claps(t0, count, spread, level = 0.5) {
    for (let i = 0; i < count; i++) {
      const t = t0 + Math.pow(Math.random(), 0.8) * spread;
      noise(t, 0.02 + Math.random() * 0.035, { freq: 1000 + Math.random() * 1400, q: 1.4, gain: level * (0.25 + Math.random() * 0.75) });
    }
  }
  function crowd(t0, dur, level = 1) {   // stadium roar: formant-filtered noise with a murmuring wobble + whistles
    const c = st.ctx, out = c.createGain();
    out.gain.setValueAtTime(0.0001, t0);
    out.gain.exponentialRampToValueAtTime(0.55 * level, t0 + 0.35);
    out.gain.setValueAtTime(0.55 * level, t0 + dur);
    out.gain.exponentialRampToValueAtTime(0.0001, t0 + dur + 1.8);
    out.connect(st.master);
    [[420, 0.9, 1], [1050, 1.3, 0.7], [2500, 1.6, 0.22]].forEach(([fq, q, gn]) => {
      const src = c.createBufferSource(), f = c.createBiquadFilter(), g = c.createGain(), lfo = c.createOscillator(), lg = c.createGain();
      src.buffer = st.noise; src.loop = true; f.type = 'bandpass'; f.frequency.value = fq; f.Q.value = q;
      g.gain.value = gn; lfo.frequency.value = 2 + Math.random() * 5; lg.gain.value = gn * 0.35;
      lfo.connect(lg); lg.connect(g.gain); src.connect(f); f.connect(g); g.connect(out);
      const end = t0 + dur + 2;
      src.start(t0, Math.random() * 2); src.stop(end); lfo.start(t0); lfo.stop(end);
    });
    for (let i = 0; i < Math.round(3 * level); i++) {   // a few fans whistling
      const t = t0 + 0.3 + Math.random() * dur * 0.8, f = 1700 + Math.random() * 900;
      tone(t, f, 0.35 + Math.random() * 0.3, { gain: 0.05 * level, to: f * (1.15 + Math.random() * 0.2), attack: 0.05 });
    }
  }
  function groan(t) { noise(t, 0.9, { freq: 750, q: 1.1, gain: 0.35, attack: 0.15, to: 320 }); }
  function stumps(t) {                   // ball crashing into the stumps, bails flying
    noise(t, 0.05, { freq: 3200, q: 1.5, gain: 0.8 });
    [0, 0.06, 0.13, 0.19].forEach((d, k) => { tone(t + d, 1900 - k * 280, 0.09, { type: 'triangle', gain: 0.35 - k * 0.06 }); noise(t + d, 0.04, { freq: 1500 - k * 200, q: 6, gain: 0.3 }); });
  }
  function dhol(t0, beats = 8) {          // stadium dhol rhythm
    const pattern = [1, 0.5, 0.8, 0.5, 1, 0.5, 0.8, 0.9];
    for (let i = 0; i < beats; i++) {
      const t = t0 + i * 0.17, a = pattern[i % pattern.length];
      tone(t, 115, 0.2, { gain: 0.55 * a, to: 62 });
      noise(t, 0.05, { freq: 600, q: 2, gain: 0.18 * a });
    }
  }

  function fx(kind, force = false) {
    if ((!st.sfx && !force) || !kind || !ctx()) return;
    const t = st.ctx.currentTime + 0.02;
    switch (kind) {
      case 'gavel': noise(t, 0.04, { freq: 1800, q: 1.5, gain: 0.9 }); tone(t, 180, 0.12, { type: 'square', gain: 0.5, to: 90 }); noise(t + 0.09, 0.04, { freq: 1600, q: 1.5, gain: 0.7 }); tone(t + 0.09, 170, 0.12, { type: 'square', gain: 0.45, to: 85 }); break;
      case 'tick': tone(t, 1200, 0.05, { type: 'square', gain: 0.18 }); break;
      case 'bid': tone(t, 660, 0.08, { type: 'triangle', gain: 0.35 }); tone(t + 0.08, 990, 0.12, { type: 'triangle', gain: 0.35 }); break;
      case 'sold': tone(t, 523, 0.12, { type: 'triangle', gain: 0.4 }); tone(t + 0.12, 659, 0.12, { type: 'triangle', gain: 0.4 }); tone(t + 0.24, 784, 0.3, { type: 'triangle', gain: 0.45 }); crowd(t + 0.2, 2.2, 0.8); claps(t + 0.3, 90, 2.6, 0.45); break;
      case 'dot': bat(t, 0.35); break;
      case 'run': bat(t, 0.6); claps(t + 0.15, 25, 1.2, 0.25); break;
      case 'four': bat(t, 1); crowd(t + 0.1, 2.2, 0.8); claps(t + 0.2, 90, 2.6, 0.45); break;
      case 'six': bat(t, 1.25); crowd(t + 0.1, 3.2, 1.15); claps(t + 0.25, 130, 3.4, 0.5); dhol(t + 0.35, 16); break;
      case 'bowled': stumps(t); crowd(t + 0.12, 2.6, 1); claps(t + 0.3, 80, 2.6, 0.45); break;
      case 'out': bat(t, 0.8); crowd(t + 0.2, 2.6, 1); claps(t + 0.4, 80, 2.6, 0.45); break;
      case 'wide': groan(t); break;
      case 'milestone': crowd(t, 3, 0.9); claps(t, 160, 4, 0.55); break;
      case 'cheer': crowd(t, 2.4, 0.7); claps(t, 100, 3, 0.45); break;
      case 'win': crowd(t, 5, 1.25); claps(t + 0.2, 240, 5.5, 0.6); dhol(t + 0.3, 24); break;
      case 'correct': tone(t, 880, 0.14, { type: 'triangle', gain: 0.35 }); tone(t + 0.1, 1320, 0.22, { type: 'triangle', gain: 0.35 }); claps(t + 0.15, 18, 0.9, 0.22); break;
      case 'coin': for (let i = 0; i < 6; i++) tone(t + i * 0.22, 2200 + (i % 2) * 300, 0.09, { type: 'triangle', gain: 0.22 - i * 0.025 }); break;
      case 'wrong': tone(t, 220, 0.18, { type: 'sawtooth', gain: 0.18, to: 180 }); tone(t + 0.16, 160, 0.28, { type: 'sawtooth', gain: 0.18, to: 120 }); break;
    }
  }

  /* ---------- Commentary voice ---------- */
  let voiceCache = null;
  function pickVoice() {
    if (!hasSpeech) return null;
    if (voiceCache) return voiceCache;
    const vs = speechSynthesis.getVoices();
    voiceCache = vs.find(v => /en[-_]IN/i.test(v.lang)) || vs.find(v => /en[-_]GB/i.test(v.lang)) || vs.find(v => /^en/i.test(v.lang)) || null;
    return voiceCache;
  }
  if (hasSpeech) speechSynthesis.onvoiceschanged = () => { voiceCache = null; };
  function say(text, { delay = 350, interrupt = true, force = false } = {}) {
    if ((!st.voice && !force) || !hasSpeech || !text) return;
    if (interrupt) speechSynthesis.cancel();
    setTimeout(() => {
      const u = new SpeechSynthesisUtterance(text), v = pickVoice();
      if (v) { u.voice = v; u.lang = v.lang; } else u.lang = 'en-IN';
      u.rate = 1.04; u.pitch = 1.02;
      speechSynthesis.speak(u);
    }, delay);
  }
  function stopVoice() { if (hasSpeech) speechSynthesis.cancel(); }

  const pick = a => a[Math.floor(Math.random() * a.length)];
  const fill = (s, d) => s.replace(/\{(\w+)\}/g, (_, k) => d[k] ?? '');
  const LINES = {
    four: ['FOUR! Cracking shot from {bat}!', "That's raced away to the boundary! Four runs to {bat}.", 'Beautifully timed by {bat}, and that is four!', 'Up and over the infield... four runs!', 'Smashed! {bat} finds the gap, and that is four.'],
    six: ['SIX! {bat} has sent that into the stands!', "That's huge! Maximum for {bat}!", 'Massive hit! That one has gone all the way, six runs!', 'Out of the ground! What a shot from {bat}!', '{bat} launches it! Six!'],
    dot: ['Good ball, no run.', 'Dot ball. Well bowled, {bowl}.', 'Defended solidly.', 'Beaten! No run.'],
    run1: ['Quick single.', 'Pushed away for one.', 'They take a single.'],
    run2: ['Two runs, good running.', 'They come back for the second.', 'Two more to the total.'],
    run3: ['Three runs! Great running between the wickets.', 'They run three!'],
    run5: ['Five runs! Overthrows!'],
    run7: ['Seven runs! Overthrows galore!'],
    wide: ['Wide ball. Extra run.', "That's a wide.", 'Wide, down the leg side.'],
    noball: ['No ball! Free hit coming up.', "No ball! That's an extra run."],
    bowled: ['BOWLED HIM! {bowl} knocks the stumps over! {out} goes for {r}.', 'Clean bowled! What a delivery from {bowl}!'],
    caught: ['OUT! Caught by {fld}! {out} has to walk back for {r}.', 'Gone! {fld} takes the catch off {bowl}.', 'In the air... and taken! {out} is caught for {r}.'],
    'caught&': ['Caught and bowled! {bowl} takes a sharp return catch!'],
    lbw: ['Big appeal... and the finger goes up! LBW! {out} is out for {r}.', 'Plumb in front! {out} is out leg before wicket.'],
    'run out': ['Run out! Direct hit! {out} is short of the crease.', "There's a mix-up, and {out} is run out for {r}!"],
    stumped: ['Stumped! Lightning work from {fld} behind the stumps!', '{out} is stumped! Out of the crease and gone.'],
    'hit wicket': ['Oh no! Hit wicket! {out} has knocked the bails off.'],
    fifty: ["That's fifty for {bat}! Raise the bat, what a knock!", 'Half century for {bat}! The crowd is on its feet!'],
    hundred: ['CENTURY! A magnificent hundred for {bat}!', "That's a hundred! {bat} is a superstar today!"],
  };
  function line(key, d = {}) { const l = LINES[key]; return l ? fill(pick(l), d) : ''; }

  return {
    get sfx() { return st.sfx; }, get voice() { return st.voice; }, get unlocked() { return st.unlocked; },
    hasSpeech,
    setSfx(v) { st.sfx = !!v; put('cs_sfx', v ? '1' : '0'); },
    setVoice(v) { st.voice = !!v; put('cs_voice', v ? '1' : '0'); if (!v) stopVoice(); },
    unlock, fx, say, line, stopVoice,
  };
})();
