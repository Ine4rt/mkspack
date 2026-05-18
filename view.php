<?php
/* ── AJAX endpoint ───────────────────────────────────────────────────────── */
if (isset($_GET['ajax']) && !empty($_GET['firstname']) && !empty($_GET['name'])) {
    header('Content-Type: application/json; charset=utf-8');
    $conn = new mysqli('marksports.eu.mysql', 'marksports_eu', 'Marksports12', 'marksports_eu');
    if ($conn->connect_error) { echo json_encode(['error'=>'Connexion impossible']); exit; }
    $conn->set_charset('utf8mb4');
    $f = $conn->real_escape_string(trim($_GET['firstname']));
    $n = $conn->real_escape_string(trim($_GET['name']));
    $sql = "SELECT id, order_number, club, role, firstname, name,
                   coti_status, cotisation_payee, scan, completed,
                   reprise, reprise_status,
                   jacket_missing, pants_missing, bas_missing,
                   under_shirt_missing, jersey_missing, short_missing,
                   option_kway_missing, option_bas_missing, polo_missing, kit_missing
            FROM orders
            WHERE LOWER(firstname)=LOWER('$f') AND LOWER(name)=LOWER('$n')
            ORDER BY id ASC";
    $res = $conn->query($sql);
    $orders = [];
    if ($res) while ($row = $res->fetch_assoc()) $orders[] = $row;
    $conn->close();
    echo json_encode($orders);
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Suivi de commande</title>
  <style>
    /* ── Base — même charte que all_mobile.php ── */
    * { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: Arial, sans-serif;
      background-color: #f4f4f4;
      color: #333;
      min-height: 100vh;
      font-size: 15px;
    }

    /* ── Header ── */
    .page-header {
      background: linear-gradient(135deg, #007BFF 0%, #0056b3 100%);
      padding: 40px 24px 36px;
      text-align: center;
      position: relative;
      overflow: hidden;
    }
    /* Cercles décoratifs en fond */
    .page-header::before {
      content: '';
      position: absolute;
      top: -40px; right: -40px;
      width: 180px; height: 180px;
      border-radius: 50%;
      background: rgba(255,255,255,.07);
      pointer-events: none;
    }
    .page-header::after {
      content: '';
      position: absolute;
      bottom: -60px; left: -30px;
      width: 220px; height: 220px;
      border-radius: 50%;
      background: rgba(255,255,255,.05);
      pointer-events: none;
    }
    .page-header-inner {
      position: relative;
      z-index: 1;
    }
    .page-header-badge {
      display: inline-flex;
      align-items: center;
      gap: 7px;
      background: rgba(255,255,255,.18);
      border: 1px solid rgba(255,255,255,.3);
      border-radius: 99px;
      padding: 5px 14px;
      margin-bottom: 16px;
    }
    .page-header-badge-dot {
      width: 7px; height: 7px;
      border-radius: 50%;
      background: #fff;
      box-shadow: 0 0 6px rgba(255,255,255,.8);
      animation: hblink 2s ease-in-out infinite;
    }
    @keyframes hblink { 0%,100%{opacity:1} 50%{opacity:.3} }
    .page-header-badge-txt {
      font-size: .72rem;
      font-weight: 700;
      letter-spacing: .12em;
      text-transform: uppercase;
      color: rgba(255,255,255,.9);
    }
    .page-header h1 {
      font-size: clamp(1.6rem, 5vw, 2.4rem);
      font-weight: 700;
      color: #fff;
      margin-bottom: 8px;
      letter-spacing: -.01em;
      line-height: 1.15;
    }
    .page-header h1 span {
      display: block;
      font-size: .7em;
      font-weight: 400;
      opacity: .75;
      letter-spacing: .02em;
    }
    .page-header p {
      font-size: .88rem;
      color: rgba(255,255,255,.75);
      max-width: 380px;
      margin: 0 auto;
      line-height: 1.55;
    }

    /* ── Search section ── */
    #search-section {
      max-width: 480px;
      margin: 32px auto 0;
      padding: 0 16px;
    }

    .search-card {
      background: #fff;
      border-radius: 12px;
      box-shadow: 0 2px 10px rgba(0,0,0,.08);
      padding: 28px 24px;
    }
    .search-card h2 {
      font-size: 1.1rem;
      font-weight: 700;
      color: #333;
      margin-bottom: 18px;
    }

    .fields-row {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 12px;
      margin-bottom: 14px;
    }
    @media (max-width: 420px) {
      .fields-row { grid-template-columns: 1fr; }
    }

    .field label {
      display: block;
      font-size: .75rem;
      font-weight: 600;
      color: #888;
      margin-bottom: 5px;
      text-transform: uppercase;
      letter-spacing: .04em;
    }
    .field input {
      width: 100%;
      padding: 10px 14px;
      font-size: 1rem;
      font-family: Arial, sans-serif;
      border: 2px solid #ccc;
      border-radius: 25px;
      outline: none;
      background: #f9f9f9;
      color: #333;
      transition: border-color .2s;
    }
    .field input:focus { border-color: #007BFF; background: #fff; }
    .field input::placeholder { color: #aaa; }

    .btn-search {
      width: 100%;
      padding: 11px 20px;
      background-color: #007BFF;
      color: #fff;
      border: none;
      border-radius: 25px;
      font-family: Arial, sans-serif;
      font-size: .93rem;
      font-weight: 600;
      cursor: pointer;
      transition: background-color .2s, transform .1s;
    }
    .btn-search:hover  { background-color: #0056b3; }
    .btn-search:active { transform: scale(.98); }
    .btn-search:disabled { opacity: .55; cursor: not-allowed; }

    /* ── Results ── */
    #result-section {
      max-width: 600px;
      margin: 28px auto 60px;
      padding: 0 16px;
    }

    .msg {
      background: #fff;
      border-radius: 10px;
      padding: 18px 22px;
      text-align: center;
      font-size: .9rem;
      color: #666;
      box-shadow: 0 1px 4px rgba(0,0,0,.07);
    }
    .msg.error { color: #c0392b; border-left: 4px solid #ff4d4d; }

    /* ── Order card ── */
    .order-card {
      background: #fff;
      border-radius: 12px;
      box-shadow: 0 2px 10px rgba(0,0,0,.08);
      overflow: hidden;
      margin-bottom: 20px;
      animation: fadeUp .35s ease both;
    }
    @keyframes fadeUp {
      from { opacity:0; transform:translateY(12px); }
      to   { opacity:1; transform:translateY(0); }
    }

    /* Card header */
    .card-header {
      background: #007BFF;
      color: #fff;
      padding: 16px 20px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-wrap: wrap;
      gap: 8px;
    }
    .card-name {
      font-size: 1.2rem;
      font-weight: 700;
    }
    .card-tags {
      display: flex;
      gap: 6px;
      flex-wrap: wrap;
    }
    .tag {
      background: rgba(255,255,255,.2);
      border-radius: 20px;
      padding: 2px 10px;
      font-size: .74rem;
      font-weight: 600;
      white-space: nowrap;
    }

    /* ── Progress bar ── */
    .progress-section {
      padding: 18px 20px 14px;
      border-bottom: 1px solid #f0f0f0;
    }
    .progress-top {
      display: flex;
      justify-content: space-between;
      align-items: baseline;
      margin-bottom: 8px;
    }
    .progress-label {
      font-size: .75rem;
      font-weight: 600;
      color: #888;
      text-transform: uppercase;
      letter-spacing: .05em;
    }
    .progress-pct {
      font-size: .95rem;
      font-weight: 700;
      color: #007BFF;
    }
    .progress-pct.done { color: #28a745; }

    .progress-track {
      height: 10px;
      background: #eef0f3;
      border-radius: 99px;
      overflow: hidden;
    }
    .progress-fill {
      height: 100%;
      width: 0%;
      border-radius: 99px;
      background: linear-gradient(90deg, #0056b3, #007BFF, #4da6ff);
      transition: width 1.3s cubic-bezier(.4,0,.2,1);
    }
    .progress-fill.complete {
      background: linear-gradient(90deg, #218838, #28a745, #5dd879);
    }

    /* ── Steps ── */
    .steps-section {
      padding: 20px 20px 16px;
    }

    .step {
      display: flex;
      gap: 14px;
      position: relative;
      padding-bottom: 20px;
    }
    .step:last-child { padding-bottom: 0; }

    /* Vertical connector */
    .step::before {
      content: '';
      position: absolute;
      left: 14px; top: 30px; bottom: 0;
      width: 2px;
      background: #eef0f3;
    }
    .step:last-child::before { display: none; }
    .step.done::before   { background: #d0e7ff; }
    .step.active::before { background: linear-gradient(#d0e7ff, #eef0f3); }

    /* Icon */
    .step-ico {
      flex-shrink: 0;
      width: 30px; height: 30px;
      border-radius: 50%;
      border: 2px solid #ddd;
      background: #f9f9f9;
      display: flex; align-items: center; justify-content: center;
      font-size: .82rem;
      z-index: 1;
    }
    .step.done .step-ico {
      border-color: #007BFF;
      background: #eef6ff;
    }
    .step.active .step-ico {
      border-color: #007BFF;
      background: #007BFF;
      color: #fff;
      box-shadow: 0 0 0 4px rgba(0,123,255,.15);
    }
    .step.warn .step-ico {
      border-color: #e67e22;
      background: #fff8f0;
    }
    .step.final .step-ico {
      border-color: #28a745;
      background: #28a745;
      color: #fff;
      box-shadow: 0 0 0 4px rgba(40,167,69,.15);
    }
    /* Checkmark for done steps */
    .step.done .step-ico::before {
      content: '✓';
      font-size: .82rem;
      color: #007BFF;
      font-weight: 700;
    }
    .step.done .step-ico .ico-em { display: none; }

    /* Text */
    .step-body { flex: 1; padding-top: 3px; }
    .step-title {
      font-size: 1rem;
      font-weight: 700;
      color: #bbb;
      margin-bottom: 4px;
    }
    .step.done .step-title   { color: #007BFF; }
    .step.active .step-title { color: #333; }
    .step.warn .step-title   { color: #e67e22; }
    .step.final .step-title  { color: #28a745; }

    .step-desc {
      font-size: .92rem;
      color: #aaa;
      line-height: 1.6;
    }
    .step.done .step-desc,
    .step.active .step-desc,
    .step.warn .step-desc,
    .step.final .step-desc { color: #666; }

    .step-date {
      display: inline-block;
      margin-top: 5px;
      padding: 2px 10px;
      background: #eef6ff;
      border-radius: 20px;
      font-size: .72rem;
      color: #007BFF;
      font-weight: 600;
    }

    /* Missing chips */
    .miss-wrap { display: flex; flex-wrap: wrap; gap: 5px; margin-top: 7px; }
    .miss-chip {
      padding: 2px 10px;
      background: #ffcccc;
      border-radius: 20px;
      font-size: .72rem;
      font-weight: 600;
      color: #c0392b;
    }

    /* ── Final banner ── */
    .final-banner {
      margin: 0 20px 20px;
      border-radius: 10px;
      padding: 16px 18px;
      display: flex;
      gap: 12px;
      align-items: flex-start;
    }
    .final-banner.ready {
      background: #d4edda;
      border-left: 4px solid #28a745;
    }
    .final-banner.retrieved {
      background: #d0e7ff;
      border-left: 4px solid #007BFF;
    }
    .banner-ico { font-size: 1.4rem; flex-shrink: 0; }
    .banner-title {
      font-size: .95rem;
      font-weight: 700;
      margin-bottom: 3px;
    }
    .final-banner.ready .banner-title      { color: #155724; }
    .final-banner.retrieved .banner-title  { color: #004085; }
    .banner-text { font-size: .92rem; line-height: 1.55; }
    .final-banner.ready .banner-text      { color: #1d6432; }
    .final-banner.retrieved .banner-text  { color: #004085; }
    .pack-num {
      display: inline-block;
      background: rgba(0,0,0,.08);
      border-radius: 5px;
      padding: 0 7px;
      font-weight: 700;
      font-size: .88rem;
    }

    footer {
      text-align: center;
      padding: 20px;
      font-size: .75rem;
      color: #aaa;
      border-top: 1px solid #e0e0e0;
    }
  </style>
</head>
<body>

<!-- ── Header ── -->
<div class="page-header">
  <div class="page-header-inner">
    <div class="page-header-badge">
      <div class="page-header-badge-dot"></div>
      <div class="page-header-badge-txt">MKS — Football Orders</div>
    </div>
    <h1>Suivi de votre commande<span>Consultez l'avancement de votre pack en temps réel</span></h1>
    <p>Entrez votre prénom et votre nom ci-dessous pour retrouver votre commande.</p>
  </div>
</div>

<!-- ── Search ── -->
<div id="search-section">
  <div class="search-card">
    <h2>Rechercher ma commande</h2>
    <div class="fields-row">
      <div class="field">
        <label>Prénom</label>
        <input type="text" id="inp-fn" placeholder="Jean" autocomplete="given-name">
      </div>
      <div class="field">
        <label>Nom</label>
        <input type="text" id="inp-ln" placeholder="Dupont" autocomplete="family-name">
      </div>
    </div>
    <button class="btn-search" id="btn-go" onclick="doSearch()">Rechercher</button>
  </div>
</div>

<!-- ── Results ── -->
<div id="result-section"></div>

<footer>© MKS — Suivi de commande</footer>

<script>
const MISSING = {
  jacket_missing:'Veste', pants_missing:'Pantalon', bas_missing:'Bas',
  under_shirt_missing:'Sous-maillot', jersey_missing:'Maillot', short_missing:'Short',
  option_kway_missing:'K-Way', option_bas_missing:'Bas 2',
  polo_missing:'Polo', kit_missing:'Kit',
};

function buildSteps(o) {
  const cotiOk    = o.coti_status == 1;
  const scanned   = o.scan == 1;
  const completed = o.completed == 1;
  const retrieved = o.reprise_status == 1
                 || (o.reprise && o.reprise !== '0000-00-00' && o.reprise !== '');
  const miss = Object.entries(MISSING).filter(([k]) => o[k] == 1).map(([,v]) => v);
  const hasMiss = miss.length > 0;

  let pct = 0, steps = [];

  /* Étape 1 — Cotisation */
  if (!cotiOk) {
    steps.push({ cls:'active', ico:'💳',
      title:'En attente de cotisation',
      desc:'Nous n\'avons pas encore été informés par votre club que votre cotisation a été réglée. Nous vous invitons à contacter votre club pour vérifier la situation.' });
    pct = 0;
  } else {
    steps.push({ cls:'done', ico:'💳',
      title:'Cotisation confirmée',
      desc:'Votre cotisation a bien été enregistrée. Votre commande est en cours de traitement.',
      date: o.cotisation_payee ? fmt(o.cotisation_payee) : null });
    pct = 25;
  }

  /* Étape 2 — Marchandise */
  if (!cotiOk) {
    steps.push({ cls:'', ico:'📦',
      title:'Réception de la marchandise',
      desc:'Cette étape démarrera dès que votre cotisation sera confirmée.' });
  } else if (hasMiss) {
    steps.push({ cls:'warn', ico:'⚠️',
      title:'Marchandise incomplète',
      desc:'Nous n\'avons pas encore reçu l\'intégralité de vos articles. Les pièces suivantes sont toujours attendues :',
      miss });
    pct = 42;
  } else if (!scanned) {
    steps.push({ cls:'active', ico:'📦',
      title:'En attente de réception',
      desc:'Votre cotisation est validée. Nous attendons la livraison complète de votre marchandise.' });
    pct = 42;
  } else {
    steps.push({ cls:'done', ico:'📦',
      title:'Marchandise reçue',
      desc:'Tous vos articles ont bien été réceptionnés et vérifiés dans notre atelier.' });
    pct = 62;
  }

  /* Étape 3 — Impression */
  // Bloquée si : pas de coti, marchandise incomplète (hasMiss), ou pas encore reçue (!scanned)
  if (!cotiOk || hasMiss || !scanned) {
    steps.push({ cls:'', ico:'🖨️',
      title:'Impression & personnalisation',
      desc:'Votre pack sera envoyé à l\'impression dès que la marchandise complète sera réceptionnée.' });
  } else if (scanned && !completed) {
    steps.push({ cls:'active', ico:'🖨️',
      title:'Pack envoyé à l\'impression',
      desc:'Votre pack est actuellement en cours de flocage. Numéros, noms et blasons sont en cours de réalisation.' });
    pct = 78;
  } else {
    steps.push({ cls:'done', ico:'🖨️',
      title:'Impression terminée',
      desc:'La personnalisation de votre pack est finalisée.' });
  }

  /* Étape 4 — Retrait */
  if (retrieved) {
    steps.push({ cls:'done', ico:'🏪',
      title:'Pack retiré en magasin',
      desc:'Votre commande a bien été récupérée.',
      date: o.reprise && o.reprise !== '0000-00-00' ? fmt(o.reprise) : null });
    pct = 100;
  } else if (completed) {
    steps.push({ cls:'final', ico:'🏪',
      title:'Pack disponible en magasin !',
      desc:'Votre commande est prête. Vous pouvez venir la retirer en magasin en présentant votre numéro de pack.' });
    pct = 95;
  } else {
    steps.push({ cls:'', ico:'🏪',
      title:'Retrait en magasin',
      desc:'Votre pack sera disponible dès la fin de la personnalisation.' });
  }

  return { steps, pct, completed, retrieved };
}

function renderCard(o, idx) {
  const { steps, pct, completed, retrieved } = buildSteps(o);
  const full = pct === 100;

  // Steps
  const stepsHtml = steps.map(s => {
    const missHtml = s.miss && s.miss.length
      ? `<div class="miss-wrap">${s.miss.map(m=>`<span class="miss-chip">${esc(m)}</span>`).join('')}</div>` : '';
    const dateHtml = s.date ? `<span class="step-date">📅 ${s.date}</span>` : '';
    return `
    <div class="step ${s.cls}">
      <div class="step-ico"><span class="ico-em">${s.ico}</span></div>
      <div class="step-body">
        <div class="step-title">${s.title}</div>
        <div class="step-desc">${s.desc}${missHtml}</div>
        ${dateHtml}
      </div>
    </div>`;
  }).join('');

  // Banner
  let bannerHtml = '';
  if (retrieved) {
    const dstr = o.reprise && o.reprise !== '0000-00-00' ? ` le <strong>${fmt(o.reprise)}</strong>` : '';
    bannerHtml = `<div class="final-banner retrieved">
      <div class="banner-ico">✅</div>
      <div><div class="banner-title">Commande retirée</div>
      <div class="banner-text">Votre pack <span class="pack-num">${esc(o.order_number)}</span> a été retiré${dstr}. Merci !</div></div></div>`;
  } else if (completed) {
    bannerHtml = `<div class="final-banner ready">
      <div class="banner-ico">🎉</div>
      <div><div class="banner-title">Votre pack est prêt !</div>
      <div class="banner-text">La commande <span class="pack-num">${esc(o.order_number)}</span> est disponible en magasin. Présentez ce numéro lors de votre passage.</div></div></div>`;
  }

  return `
  <div class="order-card" style="animation-delay:${idx*.08}s">
    <div class="card-header">
      <div class="card-name">${esc(o.firstname)} ${esc(o.name)}</div>
      <div class="card-tags">
        <span class="tag">Pack #${esc(o.order_number||'—')}</span>
        <span class="tag">${esc(o.club||'—')}</span>
        <span class="tag">${esc(o.role||'—')}</span>
      </div>
    </div>
    <div class="progress-section">
      <div class="progress-top">
        <span class="progress-label">Avancement</span>
        <span class="progress-pct${full?' done':''}" id="pn-${o.id}">0%</span>
      </div>
      <div class="progress-track">
        <div class="progress-fill${full?' complete':''}" id="pf-${o.id}"></div>
      </div>
    </div>
    <div class="steps-section">${stepsHtml}</div>
    ${bannerHtml}
  </div>`;
}

async function doSearch() {
  const fn  = document.getElementById('inp-fn').value.trim();
  const ln  = document.getElementById('inp-ln').value.trim();
  const res = document.getElementById('result-section');
  const btn = document.getElementById('btn-go');

  if (!fn || !ln) {
    res.innerHTML = '<div class="msg error">Veuillez saisir votre prénom et votre nom.</div>';
    return;
  }

  btn.disabled = true; btn.textContent = 'Recherche…';
  res.innerHTML = '<div class="msg">Recherche en cours…</div>';

  // Masquer le formulaire de recherche
  document.getElementById('search-section').style.display = 'none';

  try {
    const r    = await fetch(`view.php?ajax=1&firstname=${encodeURIComponent(fn)}&name=${encodeURIComponent(ln)}`);
    const data = await r.json();

    if (data.error) {
      res.innerHTML = `<div class="msg error">Erreur : ${esc(data.error)}</div>`;
      showSearchAgain(); return;
    }
    if (!data.length) {
      res.innerHTML = `<div class="msg">Aucune commande trouvée pour <strong>${esc(fn)} ${esc(ln)}</strong>.<br>Vérifiez l'orthographe ou contactez votre club.</div>`;
      showSearchAgain(); return;
    }

    // Bouton "Nouvelle recherche" + résultats
    res.innerHTML =
      `<div style="text-align:right;margin-bottom:12px;">
         <button class="btn-search" style="width:auto;padding:8px 20px;font-size:.83rem;" onclick="resetSearch()">← Nouvelle recherche</button>
       </div>` +
      data.map((o,i) => renderCard(o,i)).join('');

    // Animate bars
    data.forEach(o => {
      const {pct} = buildSteps(o);
      setTimeout(() => {
        const fill = document.getElementById('pf-' + o.id);
        const pnum = document.getElementById('pn-' + o.id);
        if (fill) fill.style.width = pct + '%';
        if (pnum) animNum(pnum, 0, pct, 1200);
      }, 150);
    });

  } catch(e) {
    res.innerHTML = '<div class="msg error">Impossible de contacter le serveur. Veuillez réessayer.</div>';
    showSearchAgain();
  } finally {
    btn.disabled = false; btn.textContent = 'Rechercher';
  }
}

function showSearchAgain() {
  document.getElementById('search-section').style.display = '';
}

function resetSearch() {
  document.getElementById('result-section').innerHTML = '';
  const ss = document.getElementById('search-section');
  ss.style.display = '';
  document.getElementById('inp-fn').value = '';
  document.getElementById('inp-ln').value = '';
  document.getElementById('inp-fn').focus();
}

function animNum(el, from, to, dur) {
  const t0 = performance.now();
  (function tick(now) {
    const t = Math.min((now - t0) / dur, 1);
    const e = 1 - Math.pow(1 - t, 3);
    el.textContent = Math.round(from + (to - from) * e) + '%';
    if (t < 1) requestAnimationFrame(tick);
  })(t0);
}

function fmt(d) {
  if (!d || d === '0000-00-00') return null;
  try { return new Date(d).toLocaleDateString('fr-BE',{day:'2-digit',month:'long',year:'numeric'}); }
  catch { return d; }
}

function esc(s) {
  if (s == null) return '';
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

document.addEventListener('keydown', e => { if (e.key === 'Enter') doSearch(); });
</script>
</body>
</html>
