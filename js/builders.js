/* Builder directory: browse builders -> communities -> map.
   Leaflet is loaded from a CDN by new-construction.html. If it fails to load
   the first two views still work and the map view explains itself. */
(function () {
  var root = document.querySelector('.bd');
  if (!root) return;

  var DATA = window.BD_DATA || {};
  var views = {};
  root.querySelectorAll('[data-view]').forEach(function (v) { views[v.dataset.view] = v; });
  var panels = {};
  root.querySelectorAll('[data-panel]').forEach(function (p) { panels[p.dataset.panel] = p; });

  var map = null, layer = null, markers = {}, current = null;

  function show(name) {
    Object.keys(views).forEach(function (k) { views[k].hidden = k !== name; });
    if (name === 'map' && map) setTimeout(function () { map.invalidateSize(); }, 30);
  }

  function openBuilder(slug) {
    current = slug;
    Object.keys(panels).forEach(function (k) { panels[k].hidden = k !== slug; });
    show('communities');
    root.closest('.nc-builders').scrollIntoView({ block: 'start' });
  }

  /* ---- view 1: builders */
  root.querySelectorAll('.bd-card').forEach(function (c) {
    c.addEventListener('click', function () { openBuilder(c.dataset.builder); });
  });

  /* ---- back links */
  root.querySelectorAll('.bd-back').forEach(function (b) {
    b.addEventListener('click', function () {
      if (b.dataset.to === 'builders') show('builders');
      else openBuilder(current);
    });
  });

  /* ---- search + filters */
  function filter(container, cards, empty) {
    var q = (container.querySelector('[data-bfilter],[data-cfilter]') || {}).value || '';
    var t = (container.querySelector('[data-tfilter]') || {}).value || '';
    q = q.trim().toLowerCase();
    var shown = 0;
    cards.forEach(function (card) {
      var hit = (!q || card.dataset.search.indexOf(q) !== -1)
             && (!t || card.dataset.town === t);
      card.hidden = !hit;
      if (hit) shown++;
    });
    if (empty) empty.hidden = shown !== 0;
  }

  var bView = views.builders;
  var bCards = [].slice.call(bView.querySelectorAll('.bd-card'));
  var bEmpty = bView.querySelector('.cm-none');
  bView.querySelector('[data-bfilter]').addEventListener('input', function () {
    filter(bView, bCards, bEmpty);
  });

  Object.keys(panels).forEach(function (k) {
    var p = panels[k];
    var cards = [].slice.call(p.querySelectorAll('.cm-card'));
    var empty = p.querySelector('.cm-none');
    ['[data-cfilter]', '[data-tfilter]'].forEach(function (sel) {
      var el = p.querySelector(sel);
      if (el) el.addEventListener('input', function () { filter(p, cards, empty); });
      if (el && el.tagName === 'SELECT')
        el.addEventListener('change', function () { filter(p, cards, empty); });
    });
    cards.forEach(function (card) {
      card.addEventListener('click', function () { openMap(k, card.dataset.c); });
    });
  });

  /* ---- view 3: map */
  var emptyState = root.querySelector('[data-empty]');
  var detail = root.querySelector('[data-detail]');

  function clearPins() {
    Object.keys(markers).forEach(function (s) {
      var el = markers[s].getElement();
      if (el) el.querySelector('.bd-pin').classList.remove('is-on');
    });
  }

  function showEmpty() {
    detail.hidden = true;
    emptyState.hidden = false;
    clearPins();
  }

  function fillInfo(b, c) {
    detail.querySelector('[data-i-name]').textContent = c.name;
    detail.querySelector('[data-i-town]').textContent = c.town;
    detail.querySelector('[data-i-builder]').textContent = b.name;
    emptyState.hidden = true;
    detail.hidden = false;
    // on a phone the panel sits under the map, so bring it into view
    if (window.matchMedia('(max-width: 760px)').matches)
      detail.scrollIntoView({ block: 'nearest' });
  }

  root.querySelector('[data-clear]').addEventListener('click', showEmpty);

  function openMap(builderSlug, communitySlug) {
    var b = DATA[builderSlug];
    if (!b) return;
    show('map');
    root.querySelector('[data-maptitle]').textContent =
      b.communities.length + ' location' + (b.communities.length === 1 ? '' : 's');
    root.querySelector('[data-backlabel]').textContent = b.name + ' communities';

    if (typeof L === 'undefined') {
      document.getElementById('bdMap').innerHTML =
        '<p style="padding:28px;text-align:center;color:#58607A">' +
        'The map could not load. Check your connection and refresh.</p>';
      return;
    }
    if (!map) {
      map = L.map('bdMap', { scrollWheelZoom: false });
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 18,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
      }).addTo(map);
    }
    if (layer) map.removeLayer(layer);
    layer = L.layerGroup().addTo(map);
    markers = {};

    var pts = [];
    b.communities.forEach(function (c) {
      var m = L.marker([c.lat, c.lon], {
        icon: L.divIcon({ className: '', html: '<div class="bd-pin"></div>',
                          iconSize: [26, 26], iconAnchor: [13, 26] }),
        title: c.name, alt: c.name, keyboard: true
      }).addTo(layer);
      m.on('click', function () {
        clearPins();
        var el = m.getElement();
        if (el) el.querySelector('.bd-pin').classList.add('is-on');
        fillInfo(b, c);
      });
      markers[c.slug] = m;
      pts.push([c.lat, c.lon]);
    });

    if (pts.length === 1) map.setView(pts[0], 13);
    else if (pts.length) map.fitBounds(L.latLngBounds(pts).pad(0.25));
    setTimeout(function () { map.invalidateSize(); }, 30);

    if (communitySlug && markers[communitySlug]) markers[communitySlug].fire('click');
    else showEmpty();
  }
})();
