/* Interface de la démo publique : même parcours que l'app, sans serveur. */
(function () {
  'use strict';

  var catalog = new Budgeat.Catalog(window.BUDGEAT_DATA);
  var engine = new Budgeat.Engine(catalog);
  var current = null;
  var currentPrefs = null;

  var form = document.getElementById('config');
  var result = document.getElementById('result');

  function el(tag, className, text) {
    var node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined) node.textContent = text;
    return node;
  }

  function money(value) {
    return value.toFixed(2).replace('.', ',') + ' €';
  }

  // ------------------------------------------------------- construction du form

  var storesBox = document.getElementById('stores');
  catalog.storesByCountry('BE')
    .sort(function (a, b) { return a.base - b.base; })
    .forEach(function (store, i) {
      var label = el('label', 'chip');
      var input = document.createElement('input');
      input.type = 'radio';
      input.name = 'store';
      input.value = store.id;
      if (i === 0) input.checked = true;
      var span = el('span');
      var dot = el('span', 'store-dot');
      dot.style.background = store.color;
      span.appendChild(dot);
      span.appendChild(document.createTextNode(store.name));
      label.appendChild(input);
      label.appendChild(span);
      storesBox.appendChild(label);
    });

  var dietBox = document.getElementById('diets');
  Object.keys(Budgeat.DIET_LABELS).forEach(function (id, i) {
    var label = el('label', 'chip');
    var input = document.createElement('input');
    input.type = 'radio';
    input.name = 'diet';
    input.value = id;
    if (i === 0) input.checked = true;
    label.appendChild(input);
    label.appendChild(el('span', null, Budgeat.DIET_LABELS[id]));
    dietBox.appendChild(label);
  });

  var allergenBox = document.getElementById('allergens');
  Object.keys(Budgeat.ALLERGENS).forEach(function (id) {
    var label = el('label', 'chip');
    var input = document.createElement('input');
    input.type = 'checkbox';
    input.name = 'allergens';
    input.value = id;
    label.appendChild(input);
    label.appendChild(el('span', null, Budgeat.ALLERGENS[id]));
    allergenBox.appendChild(label);
  });

  var excludeBox = document.getElementById('exclude');
  Object.keys(catalog.ingredients)
    .map(function (id) { return catalog.ingredients[id]; })
    .filter(function (ing) { return !ing.pantryDefault && ing.id !== 'sel-poivre'; })
    .sort(function (a, b) { return a.name.localeCompare(b.name); })
    .forEach(function (ing) {
      var option = document.createElement('option');
      option.value = ing.id;
      option.textContent = ing.name;
      excludeBox.appendChild(option);
    });

  // ------------------------------------------------------------------ contrôles

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
  syncBudgetHint();

  function readPrefs() {
    var prefs = {
      store: form.querySelector('input[name=store]:checked').value,
      budget: Number(budget.value),
      people: Number(people.value),
      days: Number(days.value),
      diet: form.querySelector('input[name=diet]:checked').value,
      maxTime: Number(document.getElementById('maxTime').value),
      allergens: [].slice.call(form.querySelectorAll('input[name=allergens]:checked'))
        .map(function (i) { return i.value; }),
      exclude: [].slice.call(excludeBox.selectedOptions).map(function (o) { return o.value; }),
      seed: String(Date.now())
    };
    if (document.getElementById('emptyPantry').checked) prefs.pantry = [];
    return prefs;
  }

  form.addEventListener('submit', function (event) {
    event.preventDefault();
    currentPrefs = readPrefs();
    run(engine.generate(currentPrefs));
  });

  function run(plan) {
    if (!plan.ok) {
      alert(plan.message);
      return;
    }
    current = plan;
    render(plan);
  }

  // ---------------------------------------------------------------------- rendu

  function render(plan) {
    result.innerHTML = '';
    result.hidden = false;
    form.hidden = true;
    result.appendChild(summary(plan));
    if (plan.notice) {
      var warn = el('div', 'flash err');
      warn.textContent = plan.notice.message;
      result.appendChild(warn);
    }
    result.appendChild(tabs(plan));
    result.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }

  function stat(value, label) {
    var box = el('div', 'summary-stat');
    box.appendChild(el('strong', null, value));
    box.appendChild(el('span', null, label));
    return box;
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
      currentPrefs.seed = String(Date.now());
      run(engine.generate(currentPrefs));
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

  function tabs(plan) {
    var wrap = el('div');
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

    tabMenu.addEventListener('click', function () {
      tabMenu.classList.add('active'); tabList.classList.remove('active');
      paneMenu.hidden = false; paneList.hidden = true;
    });
    tabList.addEventListener('click', function () {
      tabList.classList.add('active'); tabMenu.classList.remove('active');
      paneList.hidden = false; paneMenu.hidden = true;
    });
    return wrap;
  }

  function daysPane(plan) {
    var pane = el('div', 'pane days-pane');

    plan.days.forEach(function (day) {
      var card = el('article', 'day-card');

      var head = el('div', 'day-head');
      head.appendChild(el('span', 'day-emoji', day.emoji));
      var meta = el('div', 'day-meta');
      meta.appendChild(el('span', 'day-name', day.day));
      meta.appendChild(el('h3', 'day-title', day.name));
      var tags = el('div', 'day-tags');
      tags.appendChild(el('span', 'tag', day.time + ' min'));
      tags.appendChild(el('span', 'tag', day.cuisine));
      if (day.tags.indexOf('batch') >= 0) tags.appendChild(el('span', 'tag warn', 'reste pour le midi'));
      meta.appendChild(tags);
      head.appendChild(meta);
      var price = el('div', 'day-price');
      price.appendChild(el('strong', null, money(day.costPerPerson)));
      price.appendChild(el('span', null, 'par personne'));
      head.appendChild(price);
      card.appendChild(head);

      var body = el('div', 'day-body');
      body.hidden = true;
      var cols = el('div', 'day-cols');
      var left = el('div');
      left.appendChild(el('h4', null, 'Ingrédients'));
      var ings = el('ul', 'ing-list');
      day.items.forEach(function (item) {
        var li = el('li');
        li.appendChild(el('span', null, item.name));
        li.appendChild(el('span', 'qty', item.qty + (item.pantry ? ' · placard' : '')));
        ings.appendChild(li);
      });
      left.appendChild(ings);
      var right = el('div');
      right.appendChild(el('h4', null, 'Préparation'));
      var steps = el('ol', 'step-list');
      day.steps.forEach(function (s) { steps.appendChild(el('li', null, s)); });
      right.appendChild(steps);
      cols.appendChild(left);
      cols.appendChild(right);
      body.appendChild(cols);

      var actions = el('div', 'day-actions');
      var toggle = el('button', 'btn btn-ghost btn-sm', 'Voir la recette');
      toggle.addEventListener('click', function () {
        body.hidden = !body.hidden;
        toggle.textContent = body.hidden ? 'Voir la recette' : 'Masquer la recette';
      });
      var swap = el('button', 'btn btn-soft btn-sm', 'Changer ce dîner');
      swap.addEventListener('click', function () {
        var keep = plan.days.map(function (d) { return d.id; });
        var next = engine.swapDay(currentPrefs, keep, day.id);
        if (!next.ok) { alert(next.message); return; }
        current = next;
        render(next);
      });
      actions.appendChild(toggle);
      actions.appendChild(swap);
      card.appendChild(actions);
      card.appendChild(body);

      pane.appendChild(card);
    });

    pane.appendChild(el('p', 'hint center marginal-note',
      'Le prix indiqué est ce que le dîner ajoute réellement à votre panier. ' +
      'Un plat qui réutilise des ingrédients déjà achetés coûte donc moins cher.'));
    return pane;
  }

  function shoppingPane(plan) {
    var pane = el('div', 'pane');

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
        var row = el('label', 'list-row');
        var box = document.createElement('input');
        box.type = 'checkbox';
        box.addEventListener('change', function () { row.classList.toggle('done', box.checked); });

        var main = el('div', 'list-main');
        main.appendChild(el('span', 'list-name', item.name));
        var detail = item.packs + ' × ' + item.packLabel;
        if (item.packs > 1 || item.usedPct < 100) detail += ' · besoin : ' + item.needLabel;
        main.appendChild(el('span', 'list-detail', detail));
        if (item.leftoverLabel && item.perishable) {
          main.appendChild(el('span', 'list-warn', 'il restera ' + item.leftoverLabel));
        }

        var price = el('div', 'list-price');
        price.appendChild(el('strong', null, money(item.price)));
        price.appendChild(el('span', 'small muted',
          item.refPrice.toFixed(2).replace('.', ',') + ' €/' + item.refUnit));

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
      pantry.appendChild(el('p', 'small muted pantry-list',
        plan.shopping.pantry.map(function (i) { return i.name + ' (' + i.needLabel + ')'; }).join(' · ')));
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
      lines.push('', 'Total estimé : ' + money(plan.totals.total));
      if (navigator.clipboard) {
        navigator.clipboard.writeText(lines.join('\n')).then(function () {
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
