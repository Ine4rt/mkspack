/* Budgeat — logique de l'application. Vanilla JS, aucune dépendance. */
(function () {
  'use strict';

  var form = document.getElementById('config');
  if (!form) return;

  var result = document.getElementById('result');
  var paywall = document.getElementById('paywall');
  var tpl = document.getElementById('tpl-recipe');
  var current = null;               // dernier menu reçu du serveur
  var PREFS_KEY = 'budgeat.prefs';
  var CHECKED_KEY = 'budgeat.checked';

  // ------------------------------------------------------------- utilitaires

  function money(value) {
    return value.toFixed(2).replace('.', ',') + ' €';
  }

  function el(tag, className, text) {
    var node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined) node.textContent = text;
    return node;
  }

  function store(key, value) {
    try {
      if (value === undefined) {
        var raw = localStorage.getItem(key);
        return raw ? JSON.parse(raw) : null;
      }
      localStorage.setItem(key, JSON.stringify(value));
    } catch (e) { /* navigation privée : on continue sans mémoire */ }
    return null;
  }

  // ------------------------------------------------------- champs du formulaire

  var budget = document.getElementById('budget');
  var budgetRange = document.getElementById('budgetRange');
  var budgetHint = document.getElementById('budgetHint');
  var people = document.getElementById('people');
  var days = document.getElementById('days');

  function syncBudgetHint() {
    var perMeal = Number(budget.value) / Math.max(1, Number(people.value) * Number(days.value));
    var verdict = '';
    if (perMeal < 1.6) verdict = ' — très serré, on ira au plus simple';
    else if (perMeal < 2.6) verdict = ' — petit budget, ça reste faisable';
    else if (perMeal > 6) verdict = ' — large, on peut viser du poisson et de belles pièces';
    budgetHint.textContent = 'Soit ' + money(perMeal) + ' par assiette' + verdict + '.';
  }

  budgetRange.addEventListener('input', function () {
    budget.value = budgetRange.value;
    syncBudgetHint();
  });
  budget.addEventListener('input', function () {
    budgetRange.value = Math.min(200, Math.max(20, Number(budget.value) || 20));
    syncBudgetHint();
  });

  document.querySelectorAll('[data-step]').forEach(function (button) {
    button.addEventListener('click', function () {
      var input = document.getElementById(button.dataset.step);
      var next = Number(input.value) + Number(button.dataset.delta);
      input.value = Math.min(Number(input.max), Math.max(Number(input.min), next));
      syncBudgetHint();
    });
  });

  function readPrefs() {
    var allergens = [].slice.call(form.querySelectorAll('input[name=allergens]:checked')).map(function (i) { return i.value; });
    var exclude = [].slice.call(document.getElementById('exclude').selectedOptions).map(function (o) { return o.value; });
    var prefs = {
      store: form.querySelector('input[name=store]:checked').value,
      budget: Number(budget.value),
      people: Number(people.value),
      days: Number(days.value),
      diet: form.querySelector('input[name=diet]:checked').value,
      maxTime: Number(document.getElementById('maxTime').value),
      allergens: allergens,
      exclude: exclude
    };
    if (document.getElementById('emptyPantry').checked) prefs.pantry = [];
    return prefs;
  }

  function applyPrefs(saved) {
    if (!saved) return;
    var storeInput = form.querySelector('input[name=store][value="' + saved.store + '"]');
    if (storeInput) storeInput.checked = true;
    var dietInput = form.querySelector('input[name=diet][value="' + saved.diet + '"]');
    if (dietInput) dietInput.checked = true;
    if (saved.budget) { budget.value = saved.budget; budgetRange.value = Math.min(200, saved.budget); }
    if (saved.people) people.value = saved.people;
    if (saved.days) days.value = saved.days;
    if (saved.maxTime) document.getElementById('maxTime').value = saved.maxTime;
    (saved.allergens || []).forEach(function (a) {
      var box = form.querySelector('input[name=allergens][value="' + a + '"]');
      if (box) box.checked = true;
    });
    (saved.exclude || []).forEach(function (id) {
      var opt = document.querySelector('#exclude option[value="' + id + '"]');
      if (opt) opt.selected = true;
    });
    if (saved.exclude && saved.exclude.length || (saved.allergens || []).length) {
      var adv = document.querySelector('details.advanced');
      if (adv) adv.open = true;
    }
  }

  applyPrefs(store(PREFS_KEY));
  syncBudgetHint();

  // ----------------------------------------------------------------- réseau

  function post(url, body) {
    return fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body)
    }).then(function (response) {
      return response.json().then(function (data) {
        return { status: response.status, data: data };
      });
    });
  }

  form.addEventListener('submit', function (event) {
    event.preventDefault();
    var prefs = readPrefs();
    store(PREFS_KEY, prefs);
    generate(prefs);
  });

  function generate(prefs) {
    var button = document.getElementById('go');
    button.disabled = true;
    button.textContent = 'Composition en cours…';

    post('/api/generate.php', prefs).then(function (res) {
      button.disabled = false;
      button.textContent = 'Composer ma semaine';

      if (res.status === 402) {
        showPaywall(res.data.message);
        return;
      }
      if (!res.data.ok) {
        alert(res.data.message || "Impossible de composer cette semaine.");
        return;
      }
      current = res.data;
      render(current);
    }).catch(function () {
      button.disabled = false;
      button.textContent = 'Composer ma semaine';
      alert('Connexion interrompue. Réessayez dans un instant.');
    });
  }

  function showPaywall(message) {
    document.getElementById('paywallMsg').textContent = message || '';
    paywall.hidden = false;
  }

  document.addEventListener('click', function (event) {
    if (event.target.matches('[data-close]') || event.target === paywall) {
      paywall.hidden = true;
    }
  });

  // ------------------------------------------------------------------ rendu

  function render(plan) {
    result.innerHTML = '';
    result.hidden = false;
    form.hidden = true;

    result.appendChild(summary(plan));
    if (plan.notice) result.appendChild(notice(plan.notice));
    result.appendChild(tabs(plan));

    result.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }

  function summary(plan) {
    var t = plan.totals;
    var box = el('div', 'card summary');

    var head = el('div', 'summary-head');
    var left = el('div');
    left.appendChild(el('div', 'small muted',
      t.days + ' dîners · ' + t.people + ' personne' + (t.people > 1 ? 's' : '') +
      ' · ' + (plan.store ? plan.store.name : '')));
    var total = el('div', 'summary-total');
    total.appendChild(el('strong', null, money(t.total)));
    total.appendChild(el('small', null, ' sur ' + money(t.budget) + ' de budget'));
    left.appendChild(total);
    head.appendChild(left);

    var actions = el('div', 'summary-actions');
    var again = el('button', 'btn btn-ghost btn-sm', 'Autre semaine');
    again.addEventListener('click', function () {
      var prefs = readPrefs();
      prefs.seed = String(Date.now());
      generate(prefs);
    });
    var edit = el('button', 'btn btn-ghost btn-sm', 'Modifier mes critères');
    edit.addEventListener('click', function () {
      form.hidden = false;
      result.hidden = true;
      form.scrollIntoView({ behavior: 'smooth' });
    });
    var print = el('button', 'btn btn-ghost btn-sm', 'Imprimer');
    print.addEventListener('click', function () { window.print(); });
    actions.appendChild(again);
    actions.appendChild(edit);
    actions.appendChild(print);
    head.appendChild(actions);
    box.appendChild(head);

    var bar = el('div', 'bar');
    var fill = el('span');
    fill.style.width = Math.min(100, t.usedPct) + '%';
    if (!t.feasible) fill.style.background = 'var(--danger)';
    bar.appendChild(fill);
    box.appendChild(bar);

    var stats = el('div', 'summary-stats');
    stats.appendChild(stat(money(t.perMeal), 'par assiette'));
    stats.appendChild(stat(money(t.remaining), t.remaining >= 0 ? 'restant sur le budget' : 'au-dessus du budget'));
    stats.appendChild(stat(money(t.savedVsReady), 'économisés vs plats préparés'));
    stats.appendChild(stat(t.wastePct + ' %', 'de pertes estimées'));
    stats.appendChild(stat(t.protPerMeal + ' g', 'de protéines par assiette'));
    stats.appendChild(stat(Math.round(t.cookMinutes / t.days) + ' min', 'de cuisine par soir'));
    box.appendChild(stats);

    if (t.carryOver > 0.5) {
      box.appendChild(el('p', 'small muted carry',
        'Environ ' + money(t.carryOver) + ' de produits non périssables (riz, conserves, épices) ' +
        'resteront dans vos placards pour la semaine suivante.'));
    }

    return box;
  }

  function stat(value, label) {
    var box = el('div', 'summary-stat');
    box.appendChild(el('strong', null, value));
    box.appendChild(el('span', null, label));
    return box;
  }

  function notice(data) {
    var box = el('div', 'flash err');
    box.textContent = data.message;
    return box;
  }

  function tabs(plan) {
    var wrap = el('div', 'tabs-wrap');

    var bar = el('div', 'tabs');
    var tabMenu = el('button', 'tab active', 'Mes dîners');
    var tabList = el('button', 'tab', 'Liste de courses (' + plan.shopping.lines + ')');
    bar.appendChild(tabMenu);
    bar.appendChild(tabList);
    wrap.appendChild(bar);

    var paneMenu = daysPane(plan);
    var paneList = shoppingPane(plan);
    paneList.hidden = true;
    wrap.appendChild(paneMenu);
    wrap.appendChild(paneList);

    function select(active) {
      tabMenu.classList.toggle('active', active === 'menu');
      tabList.classList.toggle('active', active === 'list');
      paneMenu.hidden = active !== 'menu';
      paneList.hidden = active !== 'list';
    }
    tabMenu.addEventListener('click', function () { select('menu'); });
    tabList.addEventListener('click', function () { select('list'); });

    return wrap;
  }

  function daysPane(plan) {
    var pane = el('div', 'pane days-pane');

    plan.days.forEach(function (day) {
      var node = tpl.content.cloneNode(true);
      var card = node.querySelector('.day-card');
      node.querySelector('.day-emoji').textContent = day.emoji;
      node.querySelector('.day-name').textContent = day.day;
      node.querySelector('.day-title').textContent = day.name;
      node.querySelector('.day-price').innerHTML =
        '<strong>' + money(day.costPerPerson) + '</strong><span>par personne</span>';

      var tags = node.querySelector('.day-tags');
      tags.appendChild(el('span', 'tag', day.time + ' min'));
      tags.appendChild(el('span', 'tag', day.cuisine));
      if (day.tags.indexOf('batch') >= 0) tags.appendChild(el('span', 'tag warn', 'reste pour le midi'));

      var list = node.querySelector('.ing-list');
      day.items.forEach(function (item) {
        var li = el('li');
        li.appendChild(el('span', null, item.name));
        li.appendChild(el('span', 'qty', item.qty + (item.pantry ? ' · placard' : '')));
        list.appendChild(li);
      });

      var steps = node.querySelector('.step-list');
      day.steps.forEach(function (step) { steps.appendChild(el('li', null, step)); });

      var body = node.querySelector('.day-body');
      var toggle = node.querySelector('[data-toggle]');
      toggle.addEventListener('click', function () {
        body.hidden = !body.hidden;
        toggle.textContent = body.hidden ? 'Voir la recette' : 'Masquer la recette';
      });

      var swap = node.querySelector('[data-swap]');
      swap.addEventListener('click', function () {
        swap.disabled = true;
        swap.textContent = 'Recherche…';
        post('/api/swap.php', { token: plan.token, replace: day.id }).then(function (res) {
          if (res.status === 402) {
            swap.disabled = false;
            swap.textContent = 'Changer ce dîner';
            showPaywall(res.data.message);
            return;
          }
          if (!res.data.ok) {
            swap.disabled = false;
            swap.textContent = 'Changer ce dîner';
            alert(res.data.message || 'Aucune alternative trouvée.');
            return;
          }
          current = res.data;
          render(current);
        });
      });

      pane.appendChild(card);
    });

    // Le prix affiché est un coût marginal : il peut surprendre quand un dîner
    // profite de paquets déjà achetés pour un autre. Autant l'expliquer.
    pane.appendChild(el('p', 'hint center marginal-note',
      'Le prix indiqué est ce que le dîner ajoute réellement à votre panier. ' +
      'Un plat qui réutilise des ingrédients déjà achetés coûte donc moins cher.'));

    if (plan.swapsLeft !== null && plan.swapsLeft !== undefined) {
      pane.appendChild(el('p', 'hint center',
        plan.swapsLeft + ' remplacement(s) restant(s) avec la formule gratuite.'));
    }

    return pane;
  }

  function shoppingPane(plan) {
    var pane = el('div', 'pane');
    var checked = store(CHECKED_KEY) || {};

    var intro = el('div', 'list-head card card-pad');
    intro.appendChild(el('div', 'small muted', 'Triée dans l\'ordre du magasin. Cochez au fur et à mesure.'));
    var totalLine = el('div', 'list-total');
    totalLine.appendChild(el('strong', null, money(plan.totals.total)));
    totalLine.appendChild(el('span', 'small muted', ' à prévoir en caisse'));
    intro.appendChild(totalLine);
    pane.appendChild(intro);

    plan.shopping.aisles.forEach(function (aisle) {
      var section = el('section', 'aisle card');
      var head = el('div', 'aisle-head');
      head.appendChild(el('h3', null, aisle.label));
      head.appendChild(el('span', 'small muted', money(aisle.total)));
      section.appendChild(head);

      aisle.items.forEach(function (item) {
        var key = plan.token + ':' + item.id;
        var row = el('label', 'list-row');
        var box = el('input');
        box.type = 'checkbox';
        box.checked = !!checked[key];
        if (box.checked) row.classList.add('done');
        box.addEventListener('change', function () {
          row.classList.toggle('done', box.checked);
          checked[key] = box.checked;
          store(CHECKED_KEY, checked);
        });

        var main = el('div', 'list-main');
        main.appendChild(el('span', 'list-name', item.name));
        var detail = item.packs + ' × ' + item.packLabel;
        if (item.packs > 1 || item.usedPct < 100) {
          detail += ' · besoin : ' + item.needLabel;
        }
        main.appendChild(el('span', 'list-detail', detail));
        if (item.leftoverLabel && item.perishable) {
          main.appendChild(el('span', 'list-warn', 'il restera ' + item.leftoverLabel));
        }

        var price = el('div', 'list-price');
        price.appendChild(el('strong', null, money(item.price)));
        price.appendChild(el('span', 'small muted', item.refPrice.toFixed(2).replace('.', ',') + ' €/' + item.refUnit));

        row.appendChild(box);
        row.appendChild(main);
        row.appendChild(price);
        section.appendChild(row);
      });

      pane.appendChild(section);
    });

    if (plan.shopping.pantry.length) {
      var pantry = el('section', 'aisle card pantry');
      var phead = el('div', 'aisle-head');
      phead.appendChild(el('h3', null, 'Déjà dans vos placards'));
      phead.appendChild(el('span', 'small muted', 'non compté'));
      pantry.appendChild(phead);
      var names = plan.shopping.pantry.map(function (i) { return i.name + ' (' + i.needLabel + ')'; });
      pantry.appendChild(el('p', 'small muted pantry-list', names.join(' · ')));
      pantry.appendChild(el('p', 'hint',
        'Vérifiez que vous les avez : sinon relancez en cochant « Je pars de zéro » pour les inclure au budget.'));
      pane.appendChild(pantry);
    }

    var share = el('div', 'card card-pad share');
    var shareBtn = el('button', 'btn btn-ghost btn-sm', 'Copier la liste');
    shareBtn.addEventListener('click', function () {
      var lines = [];
      plan.shopping.aisles.forEach(function (aisle) {
        lines.push('— ' + aisle.label + ' —');
        aisle.items.forEach(function (item) {
          lines.push('[ ] ' + item.name + ' : ' + item.packs + ' × ' + item.packLabel);
        });
      });
      lines.push('');
      lines.push('Total estimé : ' + money(plan.totals.total));
      var text = lines.join('\n');
      if (navigator.clipboard) {
        navigator.clipboard.writeText(text).then(function () {
          shareBtn.textContent = 'Liste copiée !';
          setTimeout(function () { shareBtn.textContent = 'Copier la liste'; }, 2000);
        });
      }
    });
    share.appendChild(shareBtn);
    pane.appendChild(share);

    return pane;
  }
})();
