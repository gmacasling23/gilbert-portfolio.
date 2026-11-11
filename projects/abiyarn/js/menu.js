/* ============================================
   MENU PAGE - AbiYarn
   Products come from the database via api/products.php
   (columns: id, name, category, price, description, image, stock, status)
   ============================================ */

document.addEventListener('DOMContentLoaded', () => {

  const productsGrid = document.getElementById('productsGrid');
  const filterBtns   = document.querySelectorAll('.filter-btn');
  let   activeCategory = 'all';

  /* ── Filter panel elements ───────────────── */
  const panel      = document.getElementById('filtersPanel');
  const overlay    = document.getElementById('filtersOverlay');
  const openBtn    = document.getElementById('openFilters');
  const closeBtn   = document.getElementById('closeFilters');
  const badge      = document.getElementById('filterBadge');
  const rangeMin   = document.getElementById('rangeMin');
  const rangeMax   = document.getElementById('rangeMax');
  const rangeFill  = document.getElementById('rangeFill');
  const bubbleMin  = document.getElementById('bubbleMin');
  const bubbleMax  = document.getElementById('bubbleMax');
  const rangeLo    = document.getElementById('rangeLo');
  const rangeHi    = document.getElementById('rangeHi');
  const inputMin   = document.getElementById('priceMin');
  const inputMax   = document.getElementById('priceMax');
  const stockRadios = document.querySelectorAll('input[name="stockFilter"]');

  /* ── Catalog (from the database) ─────────── */
  // Normalise the values PHP/MySQL send as strings, without touching the
  // global PRODUCTS array that cart.js relies on.
  const RAW = (typeof PRODUCTS !== 'undefined' && Array.isArray(PRODUCTS)) ? PRODUCTS : [];
  const catalog = RAW.map(p => ({
    ...p,
    id:    Number(p.id),
    price: Number(p.price),
    stock: (p.stock === undefined || p.stock === null) ? null : Number(p.stock)
  }));

  const LOW_STOCK = 5; // show "Only N left" at or below this

  // Out of stock when the admin marked it so, or when nothing is left.
  const isOut = p => p.status === 'out_of_stock' || (p.stock !== null && p.stock <= 0);
  const isLow = p => !isOut(p) && p.stock !== null && p.stock <= LOW_STOCK;

  // Product text is editable by admins, so escape it before inserting as HTML.
  const esc = s => String(s ?? '').replace(/[&<>"']/g, c => (
    { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]
  ));

  /* ── Helpers ─────────────────────────────── */
  const THUMB = 18; // slider thumb width in px (keep in sync with menu.css)

  const prices = catalog.map(p => p.price).filter(n => !isNaN(n));
  const BOUND_LO = 0;
  const BOUND_HI = prices.length ? Math.max(50, Math.ceil(Math.max(...prices) / 50) * 50) : 1000;

  // Applied filter state (what the grid is currently showing)
  let applied = { min: BOUND_LO, max: BOUND_HI, stock: 'all' };

  function peso(n) { return '₱' + Number(n).toLocaleString('en-PH'); }

  function inCategory(p) {
    return activeCategory === 'all' || p.category === activeCategory;
  }
  function inPrice(p, min, max) {
    return p.price >= min && p.price <= max;
  }
  function matchesStock(p, stock) {
    if (stock === 'available')    return !isOut(p);
    if (stock === 'out_of_stock') return isOut(p);
    return true;
  }

  /* ── Draft (what the panel currently shows) ─ */
  function draftRange() {
    return { min: Number(rangeMin.value), max: Number(rangeMax.value) };
  }
  function draftStock() {
    const r = document.querySelector('input[name="stockFilter"]:checked');
    return r ? r.value : 'all';
  }

  function pos(value) {
    const pct = (value - BOUND_LO) / (BOUND_HI - BOUND_LO) * 100;
    return `calc(${pct}% + ${(0.5 - pct / 100) * THUMB}px)`;
  }

  function updateSliderUI() {
    const { min, max } = draftRange();
    const pMin = (min - BOUND_LO) / (BOUND_HI - BOUND_LO) * 100;
    const pMax = (max - BOUND_LO) / (BOUND_HI - BOUND_LO) * 100;
    rangeFill.style.left  = pMin + '%';
    rangeFill.style.width = (pMax - pMin) + '%';
    bubbleMin.style.left = pos(min);
    bubbleMax.style.left = pos(max);
    bubbleMin.textContent = peso(min);
    bubbleMax.textContent = peso(max);
    // keep the lower thumb reachable when both sit at the far end
    rangeMin.style.zIndex = min > (BOUND_LO + BOUND_HI) / 2 ? 5 : 3;
    updateCounts();
  }

  function updateCounts() {
    const { min, max } = draftRange();
    const base = catalog.filter(p => inCategory(p) && inPrice(p, min, max));
    document.getElementById('cntAvail').textContent = `(${base.filter(p => !isOut(p)).length})`;
    document.getElementById('cntOut').textContent   = `(${base.filter(isOut).length})`;
    document.getElementById('cntAll').textContent   = `(${base.length})`;
  }

  /* ── Slider / number-box syncing ─────────── */
  rangeMin.min = rangeMax.min = BOUND_LO;
  rangeMin.max = rangeMax.max = BOUND_HI;
  rangeMin.value = BOUND_LO;
  rangeMax.value = BOUND_HI;
  rangeLo.textContent = peso(BOUND_LO);
  rangeHi.textContent = peso(BOUND_HI);
  inputMin.min = inputMax.min = BOUND_LO;
  inputMin.max = inputMax.max = BOUND_HI;

  rangeMin.addEventListener('input', () => {
    if (Number(rangeMin.value) > Number(rangeMax.value)) rangeMin.value = rangeMax.value;
    inputMin.value = rangeMin.value;
    updateSliderUI();
  });
  rangeMax.addEventListener('input', () => {
    if (Number(rangeMax.value) < Number(rangeMin.value)) rangeMax.value = rangeMin.value;
    inputMax.value = rangeMax.value;
    updateSliderUI();
  });

  // Typed values: empty box = no limit on that side
  inputMin.addEventListener('input', () => {
    let v = inputMin.value === '' ? BOUND_LO : Number(inputMin.value);
    v = Math.max(BOUND_LO, Math.min(v, Number(rangeMax.value)));
    rangeMin.value = v;
    updateSliderUI();
  });
  inputMax.addEventListener('input', () => {
    let v = inputMax.value === '' ? BOUND_HI : Number(inputMax.value);
    v = Math.min(BOUND_HI, Math.max(v, Number(rangeMin.value)));
    rangeMax.value = v;
    updateSliderUI();
  });
  // On leaving a box, tidy the typed number to the clamped value
  inputMin.addEventListener('blur', () => { if (inputMin.value !== '') inputMin.value = rangeMin.value; });
  inputMax.addEventListener('blur', () => { if (inputMax.value !== '') inputMax.value = rangeMax.value; });

  stockRadios.forEach(r => r.addEventListener('change', updateCounts));

  /* ── Open / close panel ──────────────────── */
  function openPanel() {
    panel.classList.add('open');
    overlay.classList.add('open');
    panel.setAttribute('aria-hidden', 'false');
  }
  function closePanel() {
    panel.classList.remove('open');
    overlay.classList.remove('open');
    panel.setAttribute('aria-hidden', 'true');
  }
  openBtn.addEventListener('click', openPanel);
  closeBtn.addEventListener('click', closePanel);
  overlay.addEventListener('click', closePanel);
  document.addEventListener('keydown', e => { if (e.key === 'Escape') closePanel(); });

  /* ── Apply / Clear ───────────────────────── */
  function syncPanelToApplied() {
    rangeMin.value = applied.min;
    rangeMax.value = applied.max;
    inputMin.value = applied.min === BOUND_LO ? '' : applied.min;
    inputMax.value = applied.max === BOUND_HI ? '' : applied.max;
    stockRadios.forEach(r => { r.checked = r.value === applied.stock; });
    updateSliderUI();
  }

  function updateBadge() {
    let n = 0;
    if (applied.min !== BOUND_LO || applied.max !== BOUND_HI) n++;
    if (applied.stock !== 'all') n++;
    badge.textContent = n;
    badge.hidden = n === 0;
  }

  document.getElementById('applyFilters').addEventListener('click', () => {
    const { min, max } = draftRange();
    applied = { min, max, stock: draftStock() };
    updateBadge();
    renderProducts();
    closePanel();
  });

  function clearFilters() {
    applied = { min: BOUND_LO, max: BOUND_HI, stock: 'all' };
    syncPanelToApplied();
    updateBadge();
    renderProducts();
  }
  document.getElementById('clearFilters').addEventListener('click', clearFilters);

  /* ── Render Products ─────────────────────── */
  function renderProducts() {
    if (catalog.length === 0) {
      productsGrid.innerHTML = `
        <div class="no-products">
          <p>Our collection is being updated. Please check back soon!</p>
        </div>`;
      return;
    }

    const list = catalog.filter(p =>
      inCategory(p) &&
      inPrice(p, applied.min, applied.max) &&
      matchesStock(p, applied.stock)
    );

    if (list.length === 0) {
      productsGrid.innerHTML = `
        <div class="no-products">
          <p>No products match your filters.</p>
          <button class="btn btn-secondary btn-sm" type="button" id="noResultsClear">Clear filters</button>
        </div>`;
      document.getElementById('noResultsClear').addEventListener('click', clearFilters);
      return;
    }

    productsGrid.innerHTML = list.map(p => {
      const out = isOut(p);
      const low = isLow(p);
      return `
      <div class="product-card${out ? ' is-out' : ''}" data-id="${p.id}">
        <div class="product-image">
          <img src="${esc(p.image)}" alt="${esc(p.name)}" loading="lazy" />
          ${out ? '<span class="stock-badge">Out of Stock</span>'
                : low ? `<span class="stock-badge low">Only ${p.stock} left</span>` : ''}
          <div class="product-overlay">
            <button class="add-to-cart" ${out ? 'disabled' : `onclick="addToCart(${p.id})"`}>
              <span class="btn-icon">${ICONS.shoppingBag}</span>
              ${out ? 'Out of Stock' : 'Add to Cart'}
            </button>
          </div>
        </div>
        <div class="product-info">
          <span class="product-category">${esc(p.category)}</span>
          <h3>${esc(p.name)}</h3>
          <p>${esc(p.description)}</p>
          <div class="product-footer">
            <span class="product-price">${formatPrice(p.price)}</span>
            <button class="btn btn-primary btn-sm" ${out ? 'disabled' : `onclick="addToCart(${p.id})"`}>
              <span class="btn-icon">${ICONS.shoppingBag}</span>
              ${out ? 'Sold Out' : 'Add to Cart'}
            </button>
          </div>
        </div>
      </div>`;
    }).join('');

    // Animate cards in
    productsGrid.querySelectorAll('.product-card').forEach((card, i) => {
      card.style.opacity = '0';
      card.style.transform = 'translateY(20px)';
      card.style.transition = `opacity 0.4s ease ${i * 0.06}s, transform 0.4s ease ${i * 0.06}s`;
      requestAnimationFrame(() => {
        card.style.opacity = '1';
        card.style.transform = 'translateY(0)';
      });
    });
  }

  /* ── Category Buttons ────────────────────── */
  filterBtns.forEach(btn => {
    btn.addEventListener('click', () => {
      filterBtns.forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      activeCategory = btn.dataset.category;
      updateCounts();
      renderProducts();
    });
  });

  /* ── Continue Shopping ───────────────────── */
  const continueBtn = document.getElementById('continueShoppingBtn');
  if (continueBtn) continueBtn.addEventListener('click', closeCart);

  /* ── Initial Render ──────────────────────── */
  syncPanelToApplied();
  updateBadge();
  renderProducts();

});