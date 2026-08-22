/* ============================================================================
   NEON SURGE — jeu d'arcade "foule / multiplicateurs"
   Rendu canvas 2D avec projection perspective maison, zéro dépendance.
   Tout le contenu graphique est généré au trait : aucun asset externe.
   ========================================================================== */
'use strict';

/* ------------------------------- constantes ------------------------------ */
const TW    = 3.5;    // demi-largeur de la piste (unités monde)
const D0    = 7;      // profondeur du canon (= caméra + recul)
const LEN   = 30;     // longueur de la piste
const DEND  = D0 + LEN;
const UR    = 0.30;   // rayon d'une unité
const MAXU  = 520;    // plafond d'unités simultanées (perf)
const ROUND = 62;     // durée d'une manche, en secondes

const PAL = {
  bg:'#070b18', deep:'#0a1130',
  cyan:'#22e0ff', cyanDim:'#0d6d8b',
  mag:'#ff2d78', magDim:'#7a1039',
  lime:'#9dff3c', amber:'#ffc73a', ink:'#eaf6ff'
};

/* ------------------------------ sauvegarde ------------------------------- */
const SAVE_KEY = 'neonsurge.v1';
const SAVE = { level:1, best:1, coins:0, up:{ rate:0, volley:0, speed:0 }, sound:true };

function loadSave(){
  try{
    const raw = localStorage.getItem(SAVE_KEY);
    if(!raw) return;
    const o = JSON.parse(raw);
    if(o && typeof o === 'object'){
      SAVE.level = o.level|0 || 1;
      SAVE.best  = o.best|0  || 1;
      SAVE.coins = o.coins|0 || 0;
      SAVE.sound = o.sound !== false;
      if(o.up){ SAVE.up.rate=o.up.rate|0; SAVE.up.volley=o.up.volley|0; SAVE.up.speed=o.up.speed|0; }
    }
  }catch(e){ /* stockage indisponible : on joue sans persistance */ }
}
function storeSave(){
  try{ localStorage.setItem(SAVE_KEY, JSON.stringify(SAVE)); }catch(e){}
}
loadSave();

/* ------------------------- upgrades (boutique) --------------------------- */
const UPGRADES = [
  { key:'rate',   name:'CADENCE',  desc:'Le canon tire plus vite',      max:8 },
  { key:'volley', name:'VOLÉE',    desc:'Plus d’unités par salve', max:5 },
  { key:'speed',  name:'PROPULSION', desc:'Tes unités avancent plus vite', max:6 }
];
const upCost = lvl => Math.round(45 * Math.pow(1.62, lvl));

const fireInterval = () => Math.max(0.125, 0.34 - SAVE.up.rate * 0.027);
const volleySize   = () => 2 + SAVE.up.volley;
const unitSpeed    = () => 7.6 + SAVE.up.speed * 0.85;

/* --------------------------------- canvas -------------------------------- */
const cv  = document.getElementById('game');
const ctx = cv.getContext('2d', { alpha:false });
let W = 0, H = 0, DPR = 1, HZ = 0, FX = 0, CAMH = 0;

function resize(){
  DPR = Math.min(window.devicePixelRatio || 1, 2.5);
  W = cv.clientWidth; H = cv.clientHeight;
  cv.width  = Math.round(W * DPR);
  cv.height = Math.round(H * DPR);
  ctx.setTransform(DPR, 0, 0, DPR, 0, 0);
  HZ   = H * 0.15;                 // ligne d'horizon
  FX   = 0.475 * W * D0 / TW;      // focale horizontale : la piste remplit l'écran au canon
  CAMH = 0.71 * D0 * H;            // hauteur caméra pré-multipliée par la focale verticale (= H)
  YN   = HZ + CAMH / D0;           // bas de piste (au canon)
  YF   = HZ + CAMH / DEND;         // fond de piste (noyau ennemi)
}
window.addEventListener('resize', resize);

/* Mélange perspective / linéaire : la perspective pure tasse trop le fond de
   piste. On garde l'échelle perspective (les objets lointains restent petits)
   mais on étire la position verticale pour dégager le fond. */
const YMIX = 0.60;
let YN = 0, YF = 0;

/** Projette un point (x latéral, d profondeur) vers l'écran. */
function proj(x, d){
  const s  = FX / d;
  const t  = (d - D0) / LEN;
  const y  = (HZ + CAMH / d) * (1 - YMIX) + (YN + (YF - YN) * t) * YMIX;
  return { x: W * 0.5 + x * s, y, s, t, sv: s * (1 + 1.1 * Math.max(0, t)) };
}

/* --------------------------------- audio --------------------------------- */
let actx = null;
function beep(freq, dur, type, vol){
  if(!SAVE.sound) return;
  try{
    if(!actx) actx = new (window.AudioContext || window.webkitAudioContext)();
    if(actx.state === 'suspended') actx.resume();
    const o = actx.createOscillator(), g = actx.createGain();
    o.type = type || 'square';
    o.frequency.setValueAtTime(freq, actx.currentTime);
    g.gain.setValueAtTime(vol || 0.05, actx.currentTime);
    g.gain.exponentialRampToValueAtTime(0.0001, actx.currentTime + dur);
    o.connect(g); g.connect(actx.destination);
    o.start(); o.stop(actx.currentTime + dur);
  }catch(e){}
}
const sfx = {
  gateGood(){ beep(660, .12, 'square', .05); beep(990, .1, 'square', .03); },
  gateBad (){ beep(150, .16, 'sawtooth', .05); },
  wall    (){ beep(90,  .07, 'square', .04); },
  capture (){ beep(520, .18, 'triangle', .07); beep(780, .22, 'triangle', .05); },
  hitBase (){ beep(240, .05, 'square', .025); },
  win     (){ [523,659,784,1046].forEach((f,i)=>setTimeout(()=>beep(f,.18,'triangle',.07), i*110)); },
  lose    (){ [400,320,240,170].forEach((f,i)=>setTimeout(()=>beep(f,.22,'sawtooth',.06), i*130)); }
};

/* ------------------------------ générateur PRNG -------------------------- */
function rngFrom(seed){
  let s = (seed * 2654435761) >>> 0;
  return function(){
    s ^= s << 13; s >>>= 0;
    s ^= s >> 17;
    s ^= s << 5;  s >>>= 0;
    return s / 4294967296;
  };
}

/* ------------------------------- niveaux --------------------------------- */
/** Construit un niveau : rangées de portails, murs, tours, noyau ennemi. */
function buildLevel(n){
  const rnd = rngFrom(n * 7919 + 13);
  const lv = { gates:[], walls:[], towers:[] };

  /* --- rangées de portails ---------------------------------------------
     On limite à 3 le nombre de rangées multiplicatives : au-delà, les chaînes
     ×N s'emballent de façon exponentielle et le niveau devient une loterie. */
  const rows = Math.min(5, 2 + Math.floor(n / 3));
  /* Couloir d'or : les unités conservent leur position latérale après le tir,
     le joueur choisit donc un couloir et non une porte isolée. On garantit
     qu'au moins un couloir traverse le niveau en n'enchaînant que des bonus —
     sans ça, un niveau peut n'avoir aucun trajet viable. */
  const gx = (rnd() * 2 - 1) * TW * 0.86;
  let xRows = 0;
  for(let i = 0; i < rows; i++){
    const t = rows === 1 ? .5 : i / (rows - 1);
    const d = D0 + LEN * (0.17 + 0.64 * t);
    const segs = (n >= 6 && rnd() < 0.42) ? 3 : 2;
    const good = Math.min(segs - 1, Math.floor((gx + TW) / (2 * TW) * segs));
    for(let k = 0; k < segs; k++){
      const x1 = -TW + (2 * TW) * (k / segs);
      const x2 = -TW + (2 * TW) * ((k + 1) / segs);
      let op, val;
      if(k === good || n === 1){
        if(xRows < 3 && rnd() < 0.62){
          op = 'x'; val = 2 + Math.floor(rnd() * Math.min(2, 1 + Math.floor(n / 6)));
          xRows++;
        }else{
          op = '+'; val = 5 + Math.floor(rnd() * (8 + n * 2));
        }
      }else if(rnd() < 0.22){
        op = '+'; val = 2 + Math.floor(rnd() * 4);              // choix « tiède »
      }else if(rnd() < 0.5){
        op = '/'; val = 2 + Math.floor(rnd() * 2);
      }else{
        op = '-'; val = 5 + Math.floor(rnd() * (8 + n * 2));
      }
      lv.gates.push({ d, x1, x2, op, val, batch:0, timer:0, flash:0 });
    }
  }

  /* --- murs à percer ---------------------------------------------------
     Toujours placés après la première rangée de portails : un mur en amont
     ponctionne une foule encore non multipliée et casse net la progression. */
  if(n >= 3){
    const firstGate = Math.min.apply(null, lv.gates.map(g => g.d));
    const count = Math.min(2, 1 + Math.floor((n - 3) / 6));
    for(let i = 0; i < count; i++){
      const lo = Math.max(0.34, (firstGate - D0) / LEN + 0.06);
      const d  = D0 + LEN * (lo + (0.80 - lo) * rnd());
      const hw = 0.9 + rnd() * 1.5;
      const cxw = (rnd() * 2 - 1) * (TW - hw);
      const hp = 3 + Math.floor(rnd() * (4 + n * 0.8));
      lv.walls.push({ d, x1:cxw - hw, x2:cxw + hw, hp, max:hp, flash:0 });
    }
  }

  /* --- tours capturables ---------------------------------------------- */
  if(n >= 4){
    const count = Math.min(2, 1 + Math.floor((n - 4) / 6));
    for(let i = 0; i < count; i++){
      const hp = 5 + Math.floor(rnd() * (4 + n));
      lv.towers.push({
        d: D0 + LEN * (0.42 + 0.40 * rnd()),
        x: (rnd() * 2 - 1) * (TW - 0.7),
        owner:'enemy', hp, max:hp, cd: 1 + rnd(), flash:0
      });
    }
  }

  /* --- calibrage du noyau ennemi ----------------------------------------
     Les PV ne sont pas fixés « au niveau » mais déduits du potentiel réel du
     niveau tiré : on évalue le meilleur trajet possible à travers les rangées,
     puis on en déduit le débit d'unités que le joueur peut livrer. Sans ça, un
     tirage riche en ×N se gagne en 4 s et un tirage additif est infaisable. */
  const rowsMap = new Map();
  for(const g of lv.gates){
    const key = g.d.toFixed(2);
    if(!rowsMap.has(key)) rowsMap.set(key, []);
    rowsMap.get(key).push(g);
  }
  const SQUAD = 6;                       // escouade nominale entre deux rangées
  let est = SQUAD;
  for(const row of rowsMap.values()){
    let best = est;
    for(const g of row){
      const v = g.op === 'x' ? est * g.val : g.op === '+' ? est + g.val : est;
      if(v > best) best = v;
    }
    est = best;
  }
  const mult = est / SQUAD;                       // multiplicateur du meilleur trajet
  /* Le débit est plafonné par MAXU : au-delà d'environ ×22, la foule sature et
     le multiplicateur n'apporte plus rien. On borne en plus par le niveau, sinon
     un tirage riche en ×N atteint le plafond dès le niveau 6 et la difficulté
     n'évolue plus ensuite. */
  const flow = 5.9 * Math.min(mult, 22, 4 + n * 0.9);
  lv.mult   = mult;
  lv.golden = gx;
  lv.baseHp = Math.round(Math.min(1850, Math.max(45 + n * 8, flow * 14)));

  lv.myHp       = 26 + Math.floor(n * 1.5);
  lv.spawnEvery = Math.max(0.50, 1.50 - n * 0.040);
  lv.spawnBatch = 1 + Math.floor(n / 7);
  lv.enemySpeed = Math.min(6.6, 4.3 + n * 0.07);
  return lv;
}

/* ------------------------------ état de jeu ------------------------------ */
const G = {
  state:'menu', level:1, lv:null,
  units:[], parts:[], pops:[],
  cannon:{ x:0, cd:0 },
  base:{ hp:0, max:0, cd:0 },
  me:{ hp:0, max:0 },
  time:0, shake:0, flow:0, started:false, reward:0
};

function startLevel(n){
  G.level = n;
  G.lv    = buildLevel(n);
  G.units.length = 0; G.parts.length = 0; G.pops.length = 0;
  G.cannon.x = 0; G.cannon.cd = 0;
  G.base.hp = G.base.max = G.lv.baseHp;
  G.base.cd = 1.4;
  G.me.hp   = G.me.max   = G.lv.myHp;
  G.time = ROUND; G.shake = 0; G.started = false;
  G.hintT = 4.5;
  G.state = 'play';
}

/* -------------------------------- unités --------------------------------- */
function spawnUnit(x, d, side, speed){
  if(G.units.length >= MAXU) return;
  G.units.push({
    x: Math.max(-TW + UR, Math.min(TW - UR, x)),
    d, side,
    sp: speed,
    wob: Math.random() * 6.28,
    dead:false
  });
}
function killUnit(u, color){
  u.dead = true;
  burst(u.x, u.d, color || (u.side === 'p' ? PAL.cyan : PAL.mag), 5);
}
function burst(x, d, color, n){
  for(let i = 0; i < n; i++){
    G.parts.push({
      x, d, color, life: .35 + Math.random() * .3, t:0,
      vx:(Math.random()*2-1)*2.4, vd:(Math.random()*2-1)*2.4, vy:1.4 + Math.random()*2.4
    });
  }
}
function popup(x, d, text, color, tag){
  if(tag){
    for(const q of G.pops) if(q.tag === tag && q.t < .35) return;   // évite l'empilement
  }
  G.pops.push({ x, d, text, color, t:0, life:.7, tag });
}

/* ------------------------------ portails --------------------------------- */
/** Applique l'opérateur d'un portail au paquet d'unités qui vient de le franchir. */
function applyGate(g){
  const n = g.batch;
  g.batch = 0;
  if(n <= 0) return;
  const cx = (g.x1 + g.x2) / 2, hw = (g.x2 - g.x1) / 2;
  let delta = 0;

  if(g.op === 'x')      delta =  n * (g.val - 1);
  else if(g.op === '+') delta =  g.val;
  else if(g.op === '-') delta = -Math.min(g.val, n);
  else if(g.op === '/') delta = -(n - Math.max(1, Math.round(n / g.val)));

  if(delta > 0){
    delta = Math.min(delta, MAXU - G.units.length);
    for(let i = 0; i < delta; i++){
      spawnUnit(cx + (Math.random()*2-1) * hw * .82, g.d + .12 + Math.random()*.5, 'p', unitSpeed());
    }
    popup(cx, g.d, (g.op === 'x' ? '×' + g.val : '+' + g.val), PAL.lime, g);
    sfx.gateGood();
  }else if(delta < 0){
    let toKill = -delta;
    for(let i = G.units.length - 1; i >= 0 && toKill > 0; i--){
      const u = G.units[i];
      if(!u.dead && u.side === 'p' && u.d > g.d - 1.2 && u.d < g.d + 2.2){ killUnit(u); toKill--; }
    }
    popup(cx, g.d, (g.op === '/' ? '÷' + g.val : '−' + g.val), PAL.mag, g);
    sfx.gateBad();
  }
  g.flash = 1;
}

/* =========================================================================
   SIMULATION
   ========================================================================= */
const clampX = x => Math.max(-TW + UR, Math.min(TW - UR, x));
let wallSfxCd = 0;

function update(dt){
  G.flow = (G.flow + dt * 2.2) % 2;
  if(G.shake > 0) G.shake = Math.max(0, G.shake - dt * 3);
  if(wallSfxCd > 0) wallSfxCd -= dt;

  stepParticles(dt);
  if(G.state !== 'play') return;

  const lv = G.lv;
  G.time -= dt;
  if(G.hintT > 0 && (G.hintT -= dt) <= 0) UI.hint.classList.add('hidden');

  /* --- canon : tir automatique ---------------------------------------- */
  G.cannon.cd -= dt;
  if(G.cannon.cd <= 0){
    G.cannon.cd = fireInterval();
    const v = volleySize();
    for(let i = 0; i < v; i++){
      const off = v === 1 ? 0 : (i / (v - 1) - .5) * .7;
      spawnUnit(G.cannon.x + off, D0 + .35 + Math.random() * .25, 'p', unitSpeed());
    }
  }

  /* --- noyau ennemi : vagues ------------------------------------------- */
  G.base.cd -= dt;
  if(G.base.cd <= 0){
    G.base.cd = lv.spawnEvery;
    for(let i = 0; i < lv.spawnBatch; i++){
      spawnUnit((Math.random() * 2 - 1) * (TW - .6), DEND - .9, 'e', lv.enemySpeed);
    }
  }

  /* --- tours : production ---------------------------------------------- */
  for(const t of lv.towers){
    if(t.flash > 0) t.flash = Math.max(0, t.flash - dt * 2.5);
    t.cd -= dt;
    if(t.cd <= 0){
      t.cd = 2.2;
      if(t.owner === 'enemy') spawnUnit(t.x, t.d - .8, 'e', lv.enemySpeed);
      else                    spawnUnit(t.x, t.d + .8, 'p', unitSpeed());
    }
  }

  /* --- portails : fenêtre de collecte ---------------------------------- */
  for(const g of lv.gates){
    if(g.flash > 0) g.flash = Math.max(0, g.flash - dt * 2.5);
    if(g.timer > 0){
      g.timer -= dt;
      if(g.timer <= 0) applyGate(g);
    }
  }
  for(const w of lv.walls) if(w.flash > 0) w.flash = Math.max(0, w.flash - dt * 4);

  /* --- déplacement + interactions -------------------------------------- */
  const now = performance.now() / 1000;
  for(const u of G.units){
    if(u.dead) continue;
    const pd = u.d;
    u.d += (u.side === 'p' ? u.sp : -u.sp) * dt;
    u.x = clampX(u.x + Math.sin(now * 5 + u.wob) * .35 * dt);

    if(u.side === 'p'){
      /* portails */
      for(const g of lv.gates){
        if(pd < g.d && u.d >= g.d && u.x >= g.x1 && u.x <= g.x2){
          g.batch++;
          if(g.timer <= 0) g.timer = .20;
        }
      }
      /* murs */
      for(const w of lv.walls){
        if(w.hp > 0 && pd < w.d && u.d >= w.d && u.x >= w.x1 && u.x <= w.x2){
          w.hp--; w.flash = 1;
          killUnit(u, PAL.amber);
          if(wallSfxCd <= 0){ sfx.wall(); wallSfxCd = .06; }
          if(w.hp <= 0) burst((w.x1 + w.x2) / 2, w.d, PAL.amber, 22);
          break;
        }
      }
      if(u.dead) continue;
    }

    /* tours (les deux camps peuvent les reprendre) */
    for(const t of lv.towers){
      if(t.owner === u.side) continue;
      if(Math.abs(u.d - t.d) < .75 && Math.abs(u.x - t.x) < .85){
        t.hp--; t.flash = 1;
        killUnit(u);
        if(t.hp <= 0){
          t.owner = u.side; t.hp = t.max; t.cd = .8;
          burst(t.x, t.d, u.side === 'p' ? PAL.cyan : PAL.mag, 26);
          popup(t.x, t.d, u.side === 'p' ? 'CAPTURÉE' : 'PERDUE', u.side === 'p' ? PAL.cyan : PAL.mag);
          sfx.capture();
        }
        break;
      }
    }
    if(u.dead) continue;

    /* noyaux */
    if(u.side === 'p' && u.d >= DEND - .55){
      G.base.hp--; killUnit(u, PAL.mag); G.shake = Math.max(G.shake, .35);
      if(Math.random() < .3) sfx.hitBase();
    }else if(u.side === 'e' && u.d <= D0){
      G.me.hp--; killUnit(u, PAL.cyan); G.shake = Math.max(G.shake, .5);
      if(Math.random() < .3) sfx.hitBase();
    }
  }

  collide();
  for(let i = G.units.length - 1; i >= 0; i--) if(G.units[i].dead) G.units.splice(i, 1);

  /* --- fin de manche ---------------------------------------------------- */
  if(G.base.hp <= 0)                  endRound(true);
  else if(G.me.hp <= 0 || G.time <= 0) endRound(false);
}

/** Annihilation 1 contre 1 entre unités adverses, via buckets de profondeur. */
function collide(){
  const bins = new Map();
  for(const u of G.units){
    if(u.dead || u.side !== 'e') continue;
    const k = u.d | 0;
    let a = bins.get(k);
    if(!a){ a = []; bins.set(k, a); }
    a.push(u);
  }
  if(!bins.size) return;
  for(const u of G.units){
    if(u.dead || u.side !== 'p') continue;
    const k = u.d | 0;
    for(let o = -1; o <= 1; o++){
      const a = bins.get(k + o);
      if(!a) continue;
      for(const e of a){
        if(e.dead) continue;
        if(Math.abs(e.d - u.d) < .55 && Math.abs(e.x - u.x) < .55){
          killUnit(u); killUnit(e, PAL.mag);
          break;
        }
      }
      if(u.dead) break;
    }
  }
}

function stepParticles(dt){
  for(let i = G.parts.length - 1; i >= 0; i--){
    const p = G.parts[i];
    p.t += dt;
    if(p.t >= p.life){ G.parts.splice(i, 1); continue; }
    p.x += p.vx * dt; p.d += p.vd * dt;
    p.vy -= 7 * dt;
  }
  for(let i = G.pops.length - 1; i >= 0; i--){
    const q = G.pops[i];
    q.t += dt;
    if(q.t >= q.life) G.pops.splice(i, 1);
  }
}

/* =========================================================================
   RENDU
   ========================================================================= */
function makeOrb(hex, rgb){
  const S = 72, c = document.createElement('canvas');
  c.width = c.height = S;
  const g = c.getContext('2d');
  const halo = g.createRadialGradient(S/2, S/2, 0, S/2, S/2, S/2);
  halo.addColorStop(0,   'rgba(' + rgb + ',.85)');
  halo.addColorStop(.35, 'rgba(' + rgb + ',.35)');
  halo.addColorStop(1,   'rgba(' + rgb + ',0)');
  g.fillStyle = halo; g.fillRect(0, 0, S, S);
  const core = g.createRadialGradient(S*.42, S*.4, S*.02, S/2, S/2, S*.24);
  core.addColorStop(0, '#ffffff');
  core.addColorStop(.45, hex);
  core.addColorStop(1, 'rgba(' + rgb + ',.25)');
  g.fillStyle = core;
  g.beginPath(); g.arc(S/2, S/2, S*.24, 0, 6.2832); g.fill();
  return c;
}
const ORB = { p:makeOrb(PAL.cyan, '34,224,255'), e:makeOrb(PAL.mag, '255,45,120') };

function render(){
  ctx.save();
  if(G.shake > 0){
    const m = G.shake * 9;
    ctx.translate((Math.random()*2-1)*m, (Math.random()*2-1)*m);
  }
  drawSky();
  drawTrack();
  if(G.lv){
    drawBaseCore();
    drawScenery();
    drawUnits();
  }
  drawCannon();
  drawParticles();
  drawPops();
  ctx.restore();
}

function drawSky(){
  const g = ctx.createLinearGradient(0, 0, 0, H);
  g.addColorStop(0,   '#050818');
  g.addColorStop(.16, '#0b1440');
  g.addColorStop(.34, '#0a1024');
  g.addColorStop(1,   PAL.bg);
  ctx.fillStyle = g;
  ctx.fillRect(-40, -40, W + 80, H + 80);

  /* lueur d'horizon */
  const gl = ctx.createRadialGradient(W/2, HZ, 0, W/2, HZ, W * .75);
  gl.addColorStop(0,  'rgba(255,45,120,.30)');
  gl.addColorStop(.4, 'rgba(90,40,140,.14)');
  gl.addColorStop(1,  'rgba(0,0,0,0)');
  ctx.fillStyle = gl;
  ctx.fillRect(0, 0, W, HZ * 2.4);

  /* étoiles fixes (bruit déterministe) */
  ctx.fillStyle = 'rgba(255,255,255,.55)';
  for(let i = 0; i < 60; i++){
    const a = (i * 9301 % 233280) / 233280, b = (i * 49297 % 233280) / 233280;
    ctx.globalAlpha = .12 + b * .4;
    ctx.fillRect(a * W, b * HZ * .95, 1.6, 1.6);
  }
  ctx.globalAlpha = 1;
}

function drawTrack(){
  const nl = proj(-TW, D0 - 2.2), nr = proj(TW, D0 - 2.2);
  const fl = proj(-TW, DEND + 1),  fr = proj(TW, DEND + 1);

  ctx.beginPath();
  ctx.moveTo(nl.x, nl.y); ctx.lineTo(nr.x, nr.y);
  ctx.lineTo(fr.x, fr.y); ctx.lineTo(fl.x, fl.y);
  ctx.closePath();
  const g = ctx.createLinearGradient(0, fl.y, 0, nl.y);
  g.addColorStop(0, '#101a44');
  g.addColorStop(1, '#0a1030');
  ctx.fillStyle = g; ctx.fill();

  /* barres transversales en défilement */
  ctx.save(); ctx.clip();
  for(let k = 0; k < 24; k++){
    const d = D0 - 2 + ((k * 1.8 + G.flow) % (LEN + 3));
    const a = proj(-TW, d), b = proj(TW, d);
    ctx.globalAlpha = Math.max(0, .16 * (1 - (d - D0) / LEN)) + .03;
    ctx.strokeStyle = PAL.cyan;
    ctx.lineWidth = Math.max(.6, a.s * .035);
    ctx.beginPath(); ctx.moveTo(a.x, a.y); ctx.lineTo(b.x, b.y); ctx.stroke();
  }
  ctx.globalAlpha = 1;
  /* couloirs longitudinaux */
  ctx.strokeStyle = 'rgba(34,224,255,.09)';
  ctx.lineWidth = 1;
  for(const x of [-TW/3, TW/3]){
    const a = proj(x, D0 - 2.2), b = proj(x, DEND + 1);
    ctx.beginPath(); ctx.moveTo(a.x, a.y); ctx.lineTo(b.x, b.y); ctx.stroke();
  }
  ctx.restore();

  /* rambardes lumineuses */
  for(const [x, col] of [[-TW, PAL.cyan], [TW, PAL.cyan]]){
    const a = proj(x, D0 - 2.2), b = proj(x, DEND + 1);
    const lg = ctx.createLinearGradient(a.x, a.y, b.x, b.y);
    lg.addColorStop(0, col); lg.addColorStop(1, 'rgba(34,224,255,.05)');
    ctx.strokeStyle = lg; ctx.lineWidth = 3;
    ctx.beginPath(); ctx.moveTo(a.x, a.y); ctx.lineTo(b.x, b.y); ctx.stroke();
    ctx.globalAlpha = .25; ctx.lineWidth = 9;
    ctx.beginPath(); ctx.moveTo(a.x, a.y); ctx.lineTo(b.x, b.y); ctx.stroke();
    ctx.globalAlpha = 1;
  }
}

function drawBaseCore(){
  const p = proj(0, DEND);
  const s = p.sv, hgt = 3.4 * s, hw = TW * p.s;
  const ratio = G.base.max > 0 ? Math.max(0, G.base.hp / G.base.max) : 1;

  /* arche */
  ctx.strokeStyle = PAL.mag; ctx.lineWidth = Math.max(2, s * .07);
  ctx.globalAlpha = .9;
  ctx.beginPath();
  ctx.moveTo(p.x - hw, p.y);
  ctx.lineTo(p.x - hw, p.y - hgt);
  ctx.lineTo(p.x + hw, p.y - hgt);
  ctx.lineTo(p.x + hw, p.y);
  ctx.stroke();
  ctx.globalAlpha = 1;

  /* voile d'énergie */
  const g = ctx.createLinearGradient(0, p.y - hgt, 0, p.y);
  g.addColorStop(0, 'rgba(255,45,120,.05)');
  g.addColorStop(1, 'rgba(255,45,120,' + (.18 + .2 * ratio).toFixed(3) + ')');
  ctx.fillStyle = g;
  ctx.fillRect(p.x - hw, p.y - hgt, hw * 2, hgt);

  /* noyau hexagonal */
  const r = hgt * .3 * (.8 + .2 * ratio);
  const cy = p.y - hgt * .55;
  ctx.beginPath();
  for(let i = 0; i < 6; i++){
    const a = i / 6 * 6.2832 - 1.5708 + performance.now() / 2600;
    const px = p.x + Math.cos(a) * r, py = cy + Math.sin(a) * r * .82;
    i ? ctx.lineTo(px, py) : ctx.moveTo(px, py);
  }
  ctx.closePath();
  const cg = ctx.createRadialGradient(p.x, cy, 0, p.x, cy, r);
  cg.addColorStop(0, '#ffd7e6'); cg.addColorStop(.5, PAL.mag); cg.addColorStop(1, PAL.magDim);
  ctx.fillStyle = cg; ctx.fill();
}

function drawScenery(){
  const items = [];
  for(const g of G.lv.gates)  items.push({ d:g.d, k:0, o:g });
  for(const w of G.lv.walls)  if(w.hp > 0) items.push({ d:w.d, k:1, o:w });
  for(const t of G.lv.towers) items.push({ d:t.d, k:2, o:t });
  items.sort((a, b) => b.d - a.d);
  for(const it of items){
    if(it.k === 0) drawGate(it.o);
    else if(it.k === 1) drawWall(it.o);
    else drawTower(it.o);
  }
}

function drawGate(g){
  const a = proj(g.x1, g.d), b = proj(g.x2, g.d);
  const s = a.sv, hgt = 1.62 * s;
  const good = (g.op === 'x' || g.op === '+');
  const base = good ? '157,255,60' : '255,45,120';
  const y0 = a.y - hgt, w = b.x - a.x;

  const grd = ctx.createLinearGradient(0, y0, 0, a.y);
  grd.addColorStop(0, 'rgba(' + base + ',.06)');
  grd.addColorStop(1, 'rgba(' + base + ',' + (.30 + g.flash * .35).toFixed(3) + ')');
  ctx.fillStyle = grd;
  ctx.fillRect(a.x, y0, w, hgt);

  ctx.strokeStyle = 'rgba(' + base + ',' + (.85 + g.flash * .15).toFixed(3) + ')';
  ctx.lineWidth = Math.max(1.5, s * .055);
  ctx.beginPath();
  ctx.moveTo(a.x, a.y); ctx.lineTo(a.x, y0); ctx.lineTo(b.x, y0); ctx.lineTo(b.x, a.y);
  ctx.stroke();

  const label = g.op === 'x' ? '×' + g.val
              : g.op === '+' ? '+' + g.val
              : g.op === '/' ? '÷' + g.val
              : '−' + g.val;
  const fs = Math.min(H * .045, Math.max(9, hgt * .42));
  ctx.font = '700 ' + fs.toFixed(0) + 'px Orbitron, sans-serif';
  ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
  ctx.fillStyle = 'rgba(0,0,0,.45)';
  ctx.fillText(label, a.x + w / 2, y0 + hgt * .5 + fs * .08);
  ctx.fillStyle = good ? '#e9ffd0' : '#ffe0ec';
  ctx.fillText(label, a.x + w / 2, y0 + hgt * .5);
}

function drawWall(w){
  const a = proj(w.x1, w.d), b = proj(w.x2, w.d);
  const s = a.sv, hgt = 1.35 * s, y0 = a.y - hgt;
  const wear = w.hp / w.max;

  ctx.fillStyle = '#1b2450';
  ctx.fillRect(a.x, y0, b.x - a.x, hgt);
  ctx.fillStyle = 'rgba(255,199,58,' + (.10 + (1 - wear) * .18 + w.flash * .3).toFixed(3) + ')';
  ctx.fillRect(a.x, y0, b.x - a.x, hgt);
  ctx.strokeStyle = PAL.amber; ctx.lineWidth = Math.max(1.2, s * .04);
  ctx.strokeRect(a.x, y0, b.x - a.x, hgt);

  /* stries */
  ctx.strokeStyle = 'rgba(255,199,58,.22)'; ctx.lineWidth = 1;
  for(let i = 1; i < 4; i++){
    const y = y0 + hgt * i / 4;
    ctx.beginPath(); ctx.moveTo(a.x, y); ctx.lineTo(b.x, y); ctx.stroke();
  }
  const fs = Math.max(8, hgt * .46);
  ctx.font = '700 ' + fs.toFixed(0) + 'px Orbitron, sans-serif';
  ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
  ctx.fillStyle = '#ffeec2';
  ctx.fillText(String(w.hp), (a.x + b.x) / 2, y0 + hgt * .5);
}

function drawTower(t){
  const p = proj(t.x, t.d);
  const s = p.sv, hgt = 2.4 * s, hw = .62 * p.s;
  const mine = t.owner === 'p';
  const col = mine ? PAL.cyan : PAL.mag;
  const rgb = mine ? '34,224,255' : '255,45,120';

  ctx.beginPath();
  ctx.moveTo(p.x - hw, p.y);
  ctx.lineTo(p.x - hw * .5, p.y - hgt);
  ctx.lineTo(p.x + hw * .5, p.y - hgt);
  ctx.lineTo(p.x + hw, p.y);
  ctx.closePath();
  const g = ctx.createLinearGradient(0, p.y - hgt, 0, p.y);
  g.addColorStop(0, 'rgba(' + rgb + ',' + (.55 + t.flash * .4).toFixed(3) + ')');
  g.addColorStop(1, 'rgba(10,16,45,.9)');
  ctx.fillStyle = g; ctx.fill();
  ctx.strokeStyle = col; ctx.lineWidth = Math.max(1.2, s * .04); ctx.stroke();

  /* balise */
  const r = hw * .45, cy = p.y - hgt - r * .6;
  ctx.beginPath(); ctx.arc(p.x, cy, r, 0, 6.2832);
  ctx.fillStyle = col; ctx.fill();
  ctx.globalAlpha = .25;
  ctx.beginPath(); ctx.arc(p.x, cy, r * (2 + Math.sin(performance.now() / 300) * .4), 0, 6.2832);
  ctx.fill(); ctx.globalAlpha = 1;

  const fs = Math.max(8, hgt * .24);
  ctx.font = '700 ' + fs.toFixed(0) + 'px Orbitron, sans-serif';
  ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
  ctx.fillStyle = '#fff';
  ctx.fillText(String(t.hp), p.x, p.y - hgt * .45);
}

function drawUnits(){
  /* tri en place : l'ordre du tableau n'a aucune incidence sur la simulation */
  G.units.sort((a, b) => b.d - a.d);
  const shadows = G.units.length < 240;
  if(shadows) ctx.fillStyle = 'rgba(0,0,0,.32)';
  for(let i = 0; i < G.units.length; i++){
    const u = G.units[i];
    if(u.d < D0 - 1 || u.d > DEND + 1) continue;
    const p = proj(u.x, u.d);
    const r = UR * p.sv * .8;
    if(r < .4) continue;
    if(shadows && r > 2.5){
      ctx.beginPath(); ctx.ellipse(p.x, p.y, r * 1.15, r * .42, 0, 0, 6.2832); ctx.fill();
      ctx.fillStyle = 'rgba(0,0,0,.32)';
    }
    const q = r * 2.2;
    ctx.drawImage(u.side === 'p' ? ORB.p : ORB.e, p.x - q, p.y - r * 1.2 - q, q * 2, q * 2);
  }
}

function drawCannon(){
  const p = proj(G.cannon.x, D0);
  const s = p.s, hw = .78 * s, hgt = .95 * s;

  /* ligne de visée */
  if(G.state === 'play'){
    const far = proj(G.cannon.x, DEND);
    ctx.save();
    ctx.setLineDash([6, 10]);
    ctx.strokeStyle = 'rgba(34,224,255,.22)';
    ctx.lineWidth = 2;
    ctx.beginPath(); ctx.moveTo(p.x, p.y); ctx.lineTo(far.x, far.y); ctx.stroke();
    ctx.restore();
  }

  ctx.fillStyle = 'rgba(0,0,0,.4)';
  ctx.beginPath(); ctx.ellipse(p.x, p.y, hw * 1.2, hw * .34, 0, 0, 6.2832); ctx.fill();

  /* socle trapézoïdal */
  ctx.beginPath();
  ctx.moveTo(p.x - hw, p.y);
  ctx.lineTo(p.x - hw * .55, p.y - hgt);
  ctx.lineTo(p.x + hw * .55, p.y - hgt);
  ctx.lineTo(p.x + hw, p.y);
  ctx.closePath();
  const g = ctx.createLinearGradient(0, p.y - hgt, 0, p.y);
  g.addColorStop(0, '#2ff0ff'); g.addColorStop(1, '#0b3f6b');
  ctx.fillStyle = g; ctx.fill();
  ctx.strokeStyle = PAL.cyan; ctx.lineWidth = 2; ctx.stroke();

  /* bouche du canon, pulsée par la cadence */
  const pulse = 1 - Math.min(1, G.cannon.cd / Math.max(.001, fireInterval()));
  const r = hw * (.34 + .12 * pulse);
  ctx.beginPath(); ctx.arc(p.x, p.y - hgt * 1.05, r, 0, 6.2832);
  const cg = ctx.createRadialGradient(p.x, p.y - hgt * 1.05, 0, p.x, p.y - hgt * 1.05, r);
  cg.addColorStop(0, '#fff'); cg.addColorStop(.6, PAL.cyan); cg.addColorStop(1, 'rgba(34,224,255,0)');
  ctx.fillStyle = cg; ctx.fill();
}

function drawParticles(){
  for(const p of G.parts){
    const k = 1 - p.t / p.life;
    const pr = proj(p.x, Math.max(1, p.d));
    const r = Math.max(1, .1 * pr.s * k);
    ctx.globalAlpha = k * .9;
    ctx.fillStyle = p.color;
    ctx.beginPath();
    ctx.arc(pr.x, pr.y - p.vy * p.t * pr.s * .35 - r, r, 0, 6.2832);
    ctx.fill();
  }
  ctx.globalAlpha = 1;
}

function drawPops(){
  ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
  for(const q of G.pops){
    const k = 1 - q.t / q.life;
    const p = proj(q.x, q.d);
    const fs = Math.max(12, .5 * p.sv * (1 + (1 - k) * .35));
    ctx.globalAlpha = Math.min(1, k * 1.6);
    ctx.font = '900 ' + fs.toFixed(0) + 'px Orbitron, sans-serif';
    ctx.fillStyle = 'rgba(0,0,0,.5)';
    ctx.fillText(q.text, p.x + 2, p.y - 2.1 * p.sv - (1 - k) * 60 + 2);
    ctx.fillStyle = q.color;
    ctx.fillText(q.text, p.x, p.y - 2.1 * p.sv - (1 - k) * 60);
  }
  ctx.globalAlpha = 1;
}

/* =========================================================================
   INTERFACE
   ========================================================================= */
const el = id => document.getElementById(id);
const UI = {
  hud:el('hud'), menu:el('menu'), shop:el('shop'), result:el('result'), hint:el('hint'),
  hudLevel:el('hudLevel'), hudCoins:el('hudCoins'),
  barEnemy:el('barEnemy'), barMine:el('barMine'), barTime:el('barTime'), mobCount:el('mobCount'),
  mCoins:el('mCoins'), mLevel:el('mLevel'), mBest:el('mBest'),
  sCoins:el('sCoins'), shopList:el('shopList'),
  resTitle:el('resTitle'), resSub:el('resSub'), resCoins:el('resCoins'),
  btnSound:el('btnSound')
};
const show = (node, on) => node.classList.toggle('hidden', !on);

function screen(name){
  show(UI.menu,   name === 'menu');
  show(UI.shop,   name === 'shop');
  show(UI.result, name === 'result');
  show(UI.hud,    name === 'play');
}

function refreshMenu(){
  UI.mCoins.textContent = SAVE.coins;
  UI.mLevel.textContent = SAVE.level;
  UI.mBest.textContent  = SAVE.best;
  UI.btnSound.textContent = SAVE.sound ? '♪' : '✕';
  UI.btnSound.classList.toggle('off', !SAVE.sound);
}

let lastHud = {};
function refreshHud(){
  if(lastHud.lv !== G.level){ lastHud.lv = G.level; UI.hudLevel.textContent = G.level; }
  if(lastHud.co !== SAVE.coins){ lastHud.co = SAVE.coins; UI.hudCoins.textContent = SAVE.coins; }
  const e = G.base.max ? Math.max(0, G.base.hp / G.base.max) : 0;
  const m = G.me.max   ? Math.max(0, G.me.hp   / G.me.max)   : 0;
  UI.barEnemy.style.transform = 'scaleX(' + e.toFixed(3) + ')';
  UI.barMine.style.transform  = 'scaleX(' + m.toFixed(3) + ')';
  UI.barTime.style.transform  = 'scaleX(' + Math.max(0, G.time / ROUND).toFixed(3) + ')';
  let n = 0;
  for(const u of G.units) if(u.side === 'p') n++;
  if(lastHud.n !== n){ lastHud.n = n; UI.mobCount.textContent = n; }
}

/* ------------------------------- boutique -------------------------------- */
function renderShop(){
  UI.sCoins.textContent = SAVE.coins;
  UI.shopList.innerHTML = '';
  for(const u of UPGRADES){
    const lvl  = SAVE.up[u.key];
    const maxd = lvl >= u.max;
    const cost = upCost(lvl);
    const item = document.createElement('div');
    item.className = 'shop-item';

    const left = document.createElement('div');
    const h = document.createElement('h3'); h.textContent = u.name;
    const p = document.createElement('p'); p.textContent = u.desc;
    const dots = document.createElement('div'); dots.className = 'dots';
    for(let i = 0; i < u.max; i++){
      const d = document.createElement('i');
      if(i < lvl) d.className = 'on';
      dots.appendChild(d);
    }
    left.append(h, p, dots);

    const btn = document.createElement('button');
    btn.className = 'buy';
    btn.textContent = maxd ? 'MAX' : '◈ ' + cost;
    btn.disabled = maxd || SAVE.coins < cost;
    btn.addEventListener('click', () => {
      if(SAVE.coins < cost || maxd) return;
      SAVE.coins -= cost;
      SAVE.up[u.key]++;
      storeSave();
      beep(880, .1, 'triangle', .06);
      renderShop();
    });

    item.append(left, btn);
    UI.shopList.appendChild(item);
  }
}

/* ------------------------------ fin de manche ---------------------------- */
function endRound(win){
  G.state = win ? 'win' : 'lose';
  const bonus = win ? Math.round(Math.max(0, G.time) * .6 + (G.me.hp / G.me.max) * 25) : 0;
  G.reward = win ? 25 + G.level * 6 + bonus : 8 + Math.floor(G.level * 1.5);
  SAVE.coins += G.reward;
  if(win){
    SAVE.level = G.level + 1;
    SAVE.best  = Math.max(SAVE.best, SAVE.level);
  }
  storeSave();

  UI.resTitle.textContent = win ? 'VICTOIRE' : 'ÉCHEC';
  UI.resTitle.className   = win ? 'win' : 'lose';
  UI.resSub.textContent   = win ? 'Noyau ennemi détruit'
                                : (G.me.hp <= 0 ? 'Ton noyau est tombé' : 'Temps écoulé');
  UI.resCoins.textContent = G.reward;
  el('btnNext').textContent = win ? 'NIVEAU ' + SAVE.level : 'RÉESSAYER';
  (win ? sfx.win : sfx.lose)();
  screen('result');
}

/* ------------------------------- contrôles ------------------------------- */
let drag = null;
cv.addEventListener('pointerdown', e => {
  if(G.state !== 'play') return;
  drag = { px:e.clientX, cx:G.cannon.x };
  G.started = true;
  UI.hint.classList.add('hidden');
  try{ cv.setPointerCapture(e.pointerId); }catch(err){}
  if(actx && actx.state === 'suspended') actx.resume();
});
cv.addEventListener('pointermove', e => {
  if(!drag || G.state !== 'play') return;
  G.cannon.x = clampX(drag.cx + (e.clientX - drag.px) * (D0 / FX) * 1.3);
});
const endDrag = () => { drag = null; };
cv.addEventListener('pointerup', endDrag);
cv.addEventListener('pointercancel', endDrag);
cv.addEventListener('contextmenu', e => e.preventDefault());

const keys = {};
window.addEventListener('keydown', e => {
  keys[e.key] = true;
  if(e.key === 'ArrowLeft' || e.key === 'ArrowRight') e.preventDefault();
});
window.addEventListener('keyup', e => { keys[e.key] = false; });

function keyboardMove(dt){
  if(G.state !== 'play') return;
  const v = 5.5 * dt;
  if(keys.ArrowLeft  || keys.a || keys.q) G.cannon.x = clampX(G.cannon.x - v);
  if(keys.ArrowRight || keys.d)           G.cannon.x = clampX(G.cannon.x + v);
}

/* -------------------------------- boutons -------------------------------- */
el('btnPlay').addEventListener('click', () => { startLevel(SAVE.level); screen('play'); UI.hint.classList.remove('hidden'); });
el('btnShop').addEventListener('click', () => { renderShop(); screen('shop'); });
el('btnShopBack').addEventListener('click', () => { refreshMenu(); screen('menu'); });
el('btnNext').addEventListener('click', () => {
  startLevel(G.state === 'win' ? SAVE.level : G.level);
  screen('play'); UI.hint.classList.remove('hidden');
});
el('btnMenu').addEventListener('click', () => { G.state = 'menu'; refreshMenu(); screen('menu'); });
UI.btnSound.addEventListener('click', () => {
  SAVE.sound = !SAVE.sound; storeSave();
  UI.btnSound.textContent = SAVE.sound ? '♪' : '✕';
  UI.btnSound.classList.toggle('off', !SAVE.sound);
});

/* -------------------------------- boucle --------------------------------- */
let last = performance.now();
function frame(now){
  const dt = Math.min(.05, (now - last) / 1000);
  last = now;
  keyboardMove(dt);
  update(dt);
  render();
  if(G.state === 'play') refreshHud();
  requestAnimationFrame(frame);
}

resize();
G.lv = buildLevel(SAVE.level);   // décor du menu
refreshMenu();
screen('menu');
requestAnimationFrame(frame);
