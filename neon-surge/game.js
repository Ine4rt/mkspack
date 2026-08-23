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
let W = 0, H = 0, DPR = 1, FX = 0, FY = 0;

function resize(){
  DPR = Math.min(window.devicePixelRatio || 1, 2.5);
  W = cv.clientWidth; H = cv.clientHeight;
  cv.width  = Math.round(W * DPR);
  cv.height = Math.round(H * DPR);
  ctx.setTransform(DPR, 0, 0, DPR, 0, 0);
  solveCamera();
  const A0  = D0 - CAMZ;
  const zv0 = CAMY * SN + A0 * CS;
  const yv0 = -CAMY * CS + A0 * SN;
  FY = (0.5 - Y_NEAR) * H * zv0 / yv0;   // yv0 est négatif : FY ressort positif
  FX = 0.47 * W * zv0 / TW;              // la piste remplit la largeur au canon
}
window.addEventListener('resize', resize);

/* ---------------------------------------------------------------------------
   Caméra inclinée. Au lieu d'une perspective rasante au ras du sol, on regarde
   la piste depuis un point surélevé et penché : PITCH règle à lui seul « plus
   ou moins vue de haut ». La hauteur de caméra et la focale ne sont pas des
   constantes tâtonnées mais sont RÉSOLUES pour que le canon et le noyau adverse
   tombent toujours aux mêmes hauteurs à l'écran, quel que soit l'angle choisi.
   --------------------------------------------------------------------------- */
const PITCH    = 46 * Math.PI / 180;   // 0 = ras du sol, 90 = plongée verticale
const CAM_BACK = 8;                    // recul de la caméra derrière le canon
const Y_NEAR   = 0.86;                 // hauteur écran du canon
const Y_FAR    = 0.21;                 // hauteur écran du noyau adverse

let CS = 0, SN = 0, CAMY = 0, CAMZ = 0, FYH = 0;
solveCamera();

function solveCamera(){
  CS = Math.cos(PITCH); SN = Math.sin(PITCH);
  CAMZ = D0 - CAM_BACK;
  const A0 = D0 - CAMZ, A1 = DEND - CAMZ;
  const R = (0.5 - Y_FAR) / (0.5 - Y_NEAR);
  const a = CS * SN * (R - 1);
  const b = -A0*CS*CS + A1*SN*SN + R*A1*CS*CS - R*A0*SN*SN;
  const c = A0 * A1 * SN * CS * (1 - R);
  const disc = Math.sqrt(Math.max(0, b * b - 4 * a * c));
  CAMY = Math.max((-b + disc) / (2 * a), (-b - disc) / (2 * a));
  const zv0 = CAMY * SN + A0 * CS, yv0 = -CAMY * CS + A0 * SN;
  FYH = (0.5 - Y_NEAR) * zv0 / yv0;      // FY/H : ne dépend pas de la taille écran
}

/** Inverse de la projection : profondeur monde correspondant à une hauteur
    d'écran donnée (en fraction de H). Le rapport ne dépendant que de la caméra,
    le résultat est identique sur tous les formats d'écran. */
function depthAtY(f){
  const m = (0.5 - f) / FYH;
  return CAMZ + CAMY * (m * SN + CS) / (SN - m * CS);
}

/** Projette un point au sol (x latéral, d profondeur) vers l'écran. */
function proj(x, d){
  const A  = d - CAMZ;
  const zv = CAMY * SN + A * CS;          // profondeur dans l'axe de visée
  const yv = -CAMY * CS + A * SN;
  const s  = FX / zv;                     // px par unité monde, à l'horizontale
  const t  = (d - D0) / LEN;
  return {
    x: W * 0.5 + x * s,
    y: H * 0.5 - FY * yv / zv,
    s,
    sg: FY * CAMY / (zv * zv),            // px par unité de PROFONDEUR au sol
    t,
    sv: s * (1 + 0.35 * Math.max(0, t))   // léger réhaussement pour la lisibilité
  };
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
  /* Réparties régulièrement à l'ÉCRAN et non en profondeur monde : la piste
     restant perspective, un espacement régulier en profondeur tasse les rangées
     lointaines les unes sur les autres. */
  const rowD = [];
  for(let i = 0; i < rows; i++){
    const t = rows === 1 ? .5 : i / (rows - 1);
    rowD.push(depthAtY(0.72 - 0.45 * t));
  }
  for(let i = 0; i < rows; i++){
    const d = rowD[i];
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

  /* Intervalles disponibles entre deux rangées : chaque obstacle en consomme un,
     sinon deux murs (ou un mur et une tour) se retrouvent empilés au même
     endroit. Le premier intervalle est exclu : un obstacle en amont de la
     première rangée ponctionne une foule encore non multipliée. */
  const slots = [];
  for(let k = 1; k < rowD.length; k++) slots.push((rowD[k - 1] + rowD[k]) / 2);
  for(let i = slots.length - 1; i > 0; i--){          // mélange de Fisher-Yates
    const j = Math.floor(rnd() * (i + 1));
    const tmp = slots[i]; slots[i] = slots[j]; slots[j] = tmp;
  }
  const takeSlot = () => slots.length ? slots.pop() : (rowD[0] + rowD[rowD.length - 1]) / 2;

  /* --- murs à percer --------------------------------------------------- */
  if(n >= 3){
    const count = Math.min(2, 1 + Math.floor((n - 3) / 6));
    for(let i = 0; i < count; i++){
      const d = takeSlot();
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
        d: takeSlot(),
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
  /* Au-delà du niveau 20 le plafond de multiplicateur est atteint : sans ce
     terme, les PV se figent et la difficulté cesse de monter. */
  const late = 1 + Math.max(0, n - 20) * 0.03;
  lv.baseHp = Math.round(Math.min(3400, Math.max(45 + n * 8, flow * 14 * late)));

  lv.myHp       = 26 + Math.floor(n * 1.5);
  lv.spawnEvery = Math.max(0.50, 1.50 - n * 0.040);
  /* Plafonné : le débit adverse doit rester sous ce que le joueur peut livrer,
     sinon le jeu devient ingagnable passé un certain niveau. */
  lv.spawnBatch = Math.min(6, 1 + Math.floor(n / 7));
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

function roundRect(g, x, y, w, h, r){
  r = Math.min(r, w / 2, h / 2);
  g.beginPath();
  g.moveTo(x + r, y);
  g.arcTo(x + w, y,     x + w, y + h, r);
  g.arcTo(x + w, y + h, x,     y + h, r);
  g.arcTo(x,     y + h, x,     y,     r);
  g.arcTo(x,     y,     x + w, y,     r);
  g.closePath();
}

/* Personnage pré-rendu une fois puis blitté : un dessin par unité à l'écran
   coûterait bien trop cher avec plusieurs centaines d'unités. */
function makeChar(hex, rgb, dark){
  const W0 = 96, H0 = 120;
  const c = document.createElement('canvas');
  c.width = W0; c.height = H0;
  const g = c.getContext('2d');

  const halo = g.createRadialGradient(W0/2, H0*.58, 0, W0/2, H0*.58, W0*.52);
  halo.addColorStop(0,   'rgba(' + rgb + ',.45)');
  halo.addColorStop(.55, 'rgba(' + rgb + ',.13)');
  halo.addColorStop(1,   'rgba(' + rgb + ',0)');
  g.fillStyle = halo;
  g.fillRect(0, 0, W0, H0);

  /* jambes */
  g.fillStyle = dark;
  roundRect(g, W0*.34, H0*.74, W0*.12, H0*.18, W0*.06); g.fill();
  roundRect(g, W0*.54, H0*.74, W0*.12, H0*.18, W0*.06); g.fill();

  /* corps */
  const bx = W0*.20, by = H0*.20, bw = W0*.60, bh = H0*.58;
  roundRect(g, bx, by, bw, bh, bw*.34);
  const bg = g.createLinearGradient(0, by, 0, by + bh);
  bg.addColorStop(0,   '#ffffff');
  bg.addColorStop(.22, hex);
  bg.addColorStop(1,   dark);
  g.fillStyle = bg; g.fill();
  g.lineWidth = W0*.035;
  g.strokeStyle = 'rgba(255,255,255,.5)';
  g.stroke();

  /* visière */
  roundRect(g, bx + bw*.14, by + bh*.20, bw*.72, bh*.26, bh*.13);
  g.fillStyle = 'rgba(5,9,20,.88)'; g.fill();
  roundRect(g, bx + bw*.24, by + bh*.27, bw*.40, bh*.09, bh*.045);
  g.fillStyle = 'rgba(255,255,255,.92)'; g.fill();

  /* liseré d'énergie */
  roundRect(g, bx + bw*.26, by + bh*.66, bw*.48, bh*.10, bh*.05);
  g.fillStyle = 'rgba(' + rgb + ',.9)'; g.fill();
  return c;
}
const CHAR = {
  p: makeChar(PAL.cyan, '34,224,255', '#0a5f7d'),
  e: makeChar(PAL.mag,  '255,45,120', '#7d1038')
};

function render(){
  ctx.save();
  if(G.shake > 0){
    const m = G.shake * 9;
    ctx.translate((Math.random()*2-1)*m, (Math.random()*2-1)*m);
  }
  drawBackdrop();
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

/* En vue plongeante la ligne d'horizon sort de l'écran : il n'y a plus de ciel
   à dessiner, seulement un fond sombre et la lueur du noyau adverse. */
function drawBackdrop(){
  const g = ctx.createLinearGradient(0, 0, 0, H);
  g.addColorStop(0,   '#05070f');
  g.addColorStop(.45, '#080d20');
  g.addColorStop(1,   '#04060e');
  ctx.fillStyle = g;
  ctx.fillRect(-40, -40, W + 80, H + 80);

  const b = proj(0, DEND);
  const gl = ctx.createRadialGradient(b.x, b.y, 0, b.x, b.y, W * .9);
  gl.addColorStop(0,   'rgba(255,45,120,.20)');
  gl.addColorStop(.45, 'rgba(118,42,150,.08)');
  gl.addColorStop(1,   'rgba(0,0,0,0)');
  ctx.fillStyle = gl;
  ctx.fillRect(0, 0, W, H);
}

function drawTrack(){
  const D1 = D0 - 2.6, D2 = DEND + 1.2;
  const nl = proj(-TW, D1), nr = proj(TW, D1);
  const fl = proj(-TW, D2), fr = proj(TW, D2);

  /* plateau */
  ctx.beginPath();
  ctx.moveTo(nl.x, nl.y); ctx.lineTo(nr.x, nr.y);
  ctx.lineTo(fr.x, fr.y); ctx.lineTo(fl.x, fl.y);
  ctx.closePath();
  const g = ctx.createLinearGradient(0, fl.y, 0, nl.y);
  g.addColorStop(0, '#0e1740');
  g.addColorStop(1, '#0a1130');
  ctx.fillStyle = g;
  ctx.fill();

  ctx.save();
  ctx.clip();
  /* barres transversales en défilement */
  for(let k = 0; k < 26; k++){
    const d = D1 + ((k * 1.7 + G.flow) % (LEN + 4));
    const p = proj(-TW, d), q = proj(TW, d);
    ctx.globalAlpha = .05 + .10 * (1 - (d - D0) / LEN);
    ctx.strokeStyle = PAL.cyan;
    ctx.lineWidth = Math.max(.7, p.sg * .06);
    ctx.beginPath(); ctx.moveTo(p.x, p.y); ctx.lineTo(q.x, q.y); ctx.stroke();
  }
  ctx.globalAlpha = 1;
  /* séparations de couloirs */
  ctx.strokeStyle = 'rgba(34,224,255,.10)';
  ctx.lineWidth = 1.2;
  for(const x of [-TW/3, TW/3]){
    const p = proj(x, D1), q = proj(x, D2);
    ctx.beginPath(); ctx.moveTo(p.x, p.y); ctx.lineTo(q.x, q.y); ctx.stroke();
  }
  ctx.restore();

  /* bordures en relief : c'est l'épaisseur du plateau qui donne le volume */
  const LIP = 0.42;
  for(const sx of [-1, 1]){
    const n = proj(sx * TW, D1), f = proj(sx * TW, D2);
    ctx.beginPath();
    ctx.moveTo(n.x, n.y);
    ctx.lineTo(f.x, f.y);
    ctx.lineTo(f.x, f.y - LIP * f.s);
    ctx.lineTo(n.x, n.y - LIP * n.s);
    ctx.closePath();
    const lg = ctx.createLinearGradient(n.x, n.y, f.x, f.y);
    lg.addColorStop(0, 'rgba(34,224,255,.55)');
    lg.addColorStop(1, 'rgba(34,224,255,.10)');
    ctx.fillStyle = lg;
    ctx.fill();
    ctx.strokeStyle = 'rgba(120,245,255,.85)';
    ctx.lineWidth = 2;
    ctx.beginPath();
    ctx.moveTo(n.x, n.y - LIP * n.s);
    ctx.lineTo(f.x, f.y - LIP * f.s);
    ctx.stroke();
  }
}

function drawBaseCore(){
  const p = proj(0, DEND);
  const s = p.s, hgt = 3.1 * s, hw = TW * s;
  const ratio = G.base.max > 0 ? Math.max(0, G.base.hp / G.base.max) : 1;

  const g = ctx.createLinearGradient(0, p.y - hgt, 0, p.y);
  g.addColorStop(0, 'rgba(255,45,120,.04)');
  g.addColorStop(1, 'rgba(255,45,120,' + (.16 + .18 * ratio).toFixed(3) + ')');
  ctx.fillStyle = g;
  ctx.fillRect(p.x - hw, p.y - hgt, hw * 2, hgt);

  ctx.strokeStyle = PAL.mag;
  ctx.lineWidth = Math.max(2, s * .05);
  ctx.beginPath();
  ctx.moveTo(p.x - hw, p.y);
  ctx.lineTo(p.x - hw, p.y - hgt);
  ctx.lineTo(p.x + hw, p.y - hgt);
  ctx.lineTo(p.x + hw, p.y);
  ctx.stroke();

  const r = hgt * .30 * (.8 + .2 * ratio);
  const cy = p.y - hgt * .55;
  ctx.beginPath();
  for(let i = 0; i < 6; i++){
    const a = i / 6 * 6.2832 - 1.5708 + performance.now() / 2600;
    const px = p.x + Math.cos(a) * r, py = cy + Math.sin(a) * r;
    i ? ctx.lineTo(px, py) : ctx.moveTo(px, py);
  }
  ctx.closePath();
  const cg = ctx.createRadialGradient(p.x, cy, 0, p.x, cy, r);
  cg.addColorStop(0, '#ffe2ec');
  cg.addColorStop(.5, PAL.mag);
  cg.addColorStop(1, PAL.magDim);
  ctx.fillStyle = cg;
  ctx.fill();
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
  const good = (g.op === 'x' || g.op === '+');
  const rgb  = good ? '157,255,60' : '255,45,120';
  const hgt  = 1.05 * a.sv;
  const y0   = a.y - hgt, w = b.x - a.x;

  /* empreinte au sol : rappelle que le portail est posé sur la piste */
  ctx.fillStyle = 'rgba(' + rgb + ',' + (.22 + g.flash * .3).toFixed(3) + ')';
  ctx.fillRect(a.x, a.y - a.sg * .16, w, a.sg * .32);

  const grd = ctx.createLinearGradient(0, y0, 0, a.y);
  grd.addColorStop(0, 'rgba(' + rgb + ',.05)');
  grd.addColorStop(1, 'rgba(' + rgb + ',' + (.26 + g.flash * .3).toFixed(3) + ')');
  ctx.fillStyle = grd;
  ctx.fillRect(a.x, y0, w, hgt);

  ctx.strokeStyle = 'rgba(' + rgb + ',' + (.85 + g.flash * .15).toFixed(3) + ')';
  ctx.lineWidth = Math.max(1.4, a.s * .028);
  ctx.beginPath();
  ctx.moveTo(a.x, a.y); ctx.lineTo(a.x, y0);
  ctx.lineTo(b.x, y0);  ctx.lineTo(b.x, a.y);
  ctx.stroke();

  /* linteau plein : donne au portail une vraie lecture de cadre */
  ctx.fillStyle = 'rgba(' + rgb + ',' + (.75 + g.flash * .25).toFixed(3) + ')';
  ctx.fillRect(a.x, y0 - hgt * .07, w, hgt * .1);

  const label = g.op === 'x' ? '×' + g.val
              : g.op === '+' ? '+' + g.val
              : g.op === '/' ? '÷' + g.val
              : '−' + g.val;
  const fs = Math.min(H * .04, Math.max(10, hgt * .46));
  ctx.font = '700 ' + fs.toFixed(0) + 'px Orbitron, sans-serif';
  ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
  ctx.fillStyle = 'rgba(0,0,0,.5)';
  ctx.fillText(label, a.x + w/2, y0 + hgt*.5 + fs*.07);
  ctx.fillStyle = good ? '#eeffd8' : '#ffe3ed';
  ctx.fillText(label, a.x + w/2, y0 + hgt*.5);
}

function drawWall(w){
  const a = proj(w.x1, w.d), b = proj(w.x2, w.d);
  const hgt = 1.05 * a.sv, y0 = a.y - hgt;
  const wear = w.hp / w.max;

  /* face supérieure : bien visible en vue plongeante */
  const dep = a.sg * .45;
  ctx.fillStyle = '#2c3663';
  ctx.beginPath();
  ctx.moveTo(a.x, y0); ctx.lineTo(b.x, y0);
  ctx.lineTo(b.x, y0 - dep); ctx.lineTo(a.x, y0 - dep);
  ctx.closePath(); ctx.fill();

  ctx.fillStyle = '#1a2350';
  ctx.fillRect(a.x, y0, b.x - a.x, hgt);
  ctx.fillStyle = 'rgba(255,199,58,' + (.08 + (1 - wear) * .20 + w.flash * .3).toFixed(3) + ')';
  ctx.fillRect(a.x, y0, b.x - a.x, hgt);
  ctx.strokeStyle = PAL.amber;
  ctx.lineWidth = Math.max(1.4, a.s * .03);
  ctx.strokeRect(a.x, y0, b.x - a.x, hgt);

  ctx.strokeStyle = 'rgba(255,199,58,.20)';
  ctx.lineWidth = 1;
  for(let i = 1; i < 3; i++){
    const y = y0 + hgt * i / 3;
    ctx.beginPath(); ctx.moveTo(a.x, y); ctx.lineTo(b.x, y); ctx.stroke();
  }
  const fs = Math.min(H * .035, Math.max(9, hgt * .5));
  ctx.font = '700 ' + fs.toFixed(0) + 'px Orbitron, sans-serif';
  ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
  ctx.fillStyle = '#fff1cd';
  ctx.fillText(String(w.hp), (a.x + b.x)/2, y0 + hgt*.5);
}

function drawTower(t){
  const p = proj(t.x, t.d);
  const hgt = 1.25 * p.sv, hw = .55 * p.s;
  const mine = t.owner === 'p';
  const col = mine ? PAL.cyan : PAL.mag;
  const rgb = mine ? '34,224,255' : '255,45,120';

  /* socle au sol */
  ctx.beginPath();
  ctx.ellipse(p.x, p.y, hw * 1.25, p.sg * .55, 0, 0, 6.2832);
  ctx.fillStyle = 'rgba(' + rgb + ',.22)';
  ctx.fill();

  ctx.beginPath();
  ctx.moveTo(p.x - hw, p.y);
  ctx.lineTo(p.x - hw * .52, p.y - hgt);
  ctx.lineTo(p.x + hw * .52, p.y - hgt);
  ctx.lineTo(p.x + hw, p.y);
  ctx.closePath();
  const g = ctx.createLinearGradient(0, p.y - hgt, 0, p.y);
  g.addColorStop(0, 'rgba(' + rgb + ',' + (.6 + t.flash * .4).toFixed(3) + ')');
  g.addColorStop(1, 'rgba(10,16,45,.92)');
  ctx.fillStyle = g; ctx.fill();
  ctx.strokeStyle = col;
  ctx.lineWidth = Math.max(1.4, p.s * .03);
  ctx.stroke();

  const r = hw * .26, cy = p.y - hgt - r * .8;
  ctx.beginPath(); ctx.arc(p.x, cy, r, 0, 6.2832);
  ctx.fillStyle = col; ctx.fill();
  ctx.globalAlpha = .22;
  ctx.beginPath();
  ctx.arc(p.x, cy, r * (2 + Math.sin(performance.now()/300) * .4), 0, 6.2832);
  ctx.fill();
  ctx.globalAlpha = 1;

  const fs = Math.min(H * .022, Math.max(8, hgt * .3));
  ctx.font = '700 ' + fs.toFixed(0) + 'px Orbitron, sans-serif';
  ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
  ctx.fillStyle = '#fff';
  ctx.fillText(String(t.hp), p.x, p.y - hgt * .45);
}

function drawUnits(){
  /* tri en place : l'ordre du tableau n'a aucune incidence sur la simulation */
  G.units.sort((a, b) => b.d - a.d);
  const shadows = G.units.length < 260;
  for(let i = 0; i < G.units.length; i++){
    const u = G.units[i];
    if(u.d < D0 - 1.5 || u.d > DEND + 1) continue;
    const p = proj(u.x, u.d);
    const w = 2.15 * UR * p.sv;
    if(w < 2) continue;
    if(shadows){
      ctx.fillStyle = 'rgba(0,0,0,.34)';
      ctx.beginPath();
      ctx.ellipse(p.x, p.y, w * .40, p.sg * UR * .95, 0, 0, 6.2832);
      ctx.fill();
    }
    const h = w * 1.25;
    ctx.drawImage(u.side === 'p' ? CHAR.p : CHAR.e, p.x - w/2, p.y - h * .92, w, h);
  }
}

function drawCannon(){
  const p = proj(G.cannon.x, D0);
  const hw = .85 * p.s, hgt = .80 * p.s;

  if(G.state === 'play'){
    const far = proj(G.cannon.x, DEND);
    ctx.save();
    ctx.setLineDash([7, 11]);
    ctx.strokeStyle = 'rgba(34,224,255,.20)';
    ctx.lineWidth = 2;
    ctx.beginPath(); ctx.moveTo(p.x, p.y); ctx.lineTo(far.x, far.y); ctx.stroke();
    ctx.restore();
  }

  ctx.fillStyle = 'rgba(0,0,0,.42)';
  ctx.beginPath();
  ctx.ellipse(p.x, p.y, hw * 1.15, p.sg * .5, 0, 0, 6.2832);
  ctx.fill();

  ctx.beginPath();
  ctx.moveTo(p.x - hw, p.y);
  ctx.lineTo(p.x - hw * .58, p.y - hgt);
  ctx.lineTo(p.x + hw * .58, p.y - hgt);
  ctx.lineTo(p.x + hw, p.y);
  ctx.closePath();
  const g = ctx.createLinearGradient(0, p.y - hgt, 0, p.y);
  g.addColorStop(0, '#3ff5ff');
  g.addColorStop(1, '#0a3a63');
  ctx.fillStyle = g; ctx.fill();
  ctx.strokeStyle = PAL.cyan; ctx.lineWidth = 2; ctx.stroke();

  /* face supérieure du canon : visible car on regarde d'en haut */
  ctx.beginPath();
  ctx.ellipse(p.x, p.y - hgt, hw * .58, p.sg * .3, 0, 0, 6.2832);
  ctx.fillStyle = '#7ffaff';
  ctx.fill();

  const pulse = 1 - Math.min(1, G.cannon.cd / Math.max(.001, fireInterval()));
  const r = hw * (.32 + .12 * pulse);
  const cy = p.y - hgt;
  ctx.beginPath(); ctx.arc(p.x, cy, r, 0, 6.2832);
  const cg = ctx.createRadialGradient(p.x, cy, 0, p.x, cy, r);
  cg.addColorStop(0, '#fff');
  cg.addColorStop(.6, PAL.cyan);
  cg.addColorStop(1, 'rgba(34,224,255,0)');
  ctx.fillStyle = cg; ctx.fill();
}

function drawParticles(){
  for(const p of G.parts){
    const k = 1 - p.t / p.life;
    const pr = proj(p.x, p.d);
    const r = Math.max(1, .09 * pr.s * k);
    ctx.globalAlpha = k * .9;
    ctx.fillStyle = p.color;
    ctx.beginPath();
    ctx.arc(pr.x, pr.y - p.vy * p.t * pr.s * .3 - r, r, 0, 6.2832);
    ctx.fill();
  }
  ctx.globalAlpha = 1;
}

function drawPops(){
  ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
  for(const q of G.pops){
    const k = 1 - q.t / q.life;
    const p = proj(q.x, q.d);
    const fs = Math.min(H * .05, Math.max(13, .42 * p.sv * (1 + (1 - k) * .3)));
    ctx.globalAlpha = Math.min(1, k * 1.6);
    ctx.font = '900 ' + fs.toFixed(0) + 'px Orbitron, sans-serif';
    const y = p.y - 1.35 * p.sv - (1 - k) * 42;
    ctx.fillStyle = 'rgba(0,0,0,.5)';
    ctx.fillText(q.text, p.x + 2, y + 2);
    ctx.fillStyle = q.color;
    ctx.fillText(q.text, p.x, y);
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
