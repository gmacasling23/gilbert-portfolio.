/* ============================================
   CART - AbiYarn
   ============================================ */

const MAX_QTY_PER_ITEM = 100; // same limit the server enforces in save-order.php

function loadCart() {
  try {
    const data = JSON.parse(localStorage.getItem('abiyarn_cart') || '[]');
    return Array.isArray(data) ? data : [];
  } catch (_) {
    return []; // corrupted storage should not break the whole site
  }
}

let cart = loadCart();

/* ── Stock helpers (PRODUCTS comes from api/products.php) ── */
function findProduct(id) {
  return (typeof PRODUCTS !== 'undefined' && Array.isArray(PRODUCTS))
    ? PRODUCTS.find(p => Number(p.id) === Number(id))
    : undefined;
}

// How many of this product can be bought right now (0 = out of stock).
function stockLimit(product) {
  if (!product) return 0;
  const stock = Number(product.stock);
  if (product.status === 'out_of_stock' || stock <= 0) return 0;
  return Number.isFinite(stock) ? Math.min(stock, MAX_QTY_PER_ITEM) : MAX_QTY_PER_ITEM;
}

// Product text is editable by admins, so escape it before using it as HTML.
function escHtml(s) {
  return String(s ?? '').replace(/[&<>"']/g, c => (
    { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]
  ));
}

/* Refresh saved cart items against the live catalog: current price/name/stock,
   drop items that are gone or sold out, and cap quantities to what is in stock.
   Without this, a cart saved days ago would fail at checkout with stale prices. */
function syncCartWithCatalog() {
  if (cart.length === 0) return;
  if (typeof PRODUCTS === 'undefined' || !Array.isArray(PRODUCTS) || PRODUCTS.length === 0) return; // catalog failed to load: leave the cart alone

  const notes = [];

  cart = cart.reduce((kept, item) => {
    const product = findProduct(item.id);

    if (!product) {
      notes.push(`"${item.name}" is no longer available`);
      return kept;
    }

    const limit = stockLimit(product);
    if (limit === 0) {
      notes.push(`"${product.name}" is out of stock`);
      return kept;
    }

    const wanted = Math.max(1, Number(item.qty) || 1);
    const qty    = Math.min(wanted, limit);
    if (qty < wanted) notes.push(`"${product.name}" reduced to ${qty} (only ${limit} left)`);
    if (Number(item.price) !== Number(product.price)) notes.push(`"${product.name}" price updated`);

    kept.push({ ...product, id: Number(product.id), qty });
    return kept;
  }, []);

  saveCart();
  if (notes.length) showNotification('Cart updated: ' + notes.join('; '));
}

/* ── Persist ───────────────────────────────── */
function saveCart() {
  localStorage.setItem('abiyarn_cart', JSON.stringify(cart));
}

/* ── Add to Cart ───────────────────────────── */
function addToCart(productId) {
  if (document.body.classList.contains('auth-logged-out')) {
    document.getElementById('accountBtn')?.click();
    return;
  }

  const product = findProduct(productId);
  if (!product) return;

  const limit = stockLimit(product);
  if (limit === 0) {
    showNotification(`Sorry, "${product.name}" is out of stock.`);
    return;
  }

  const existing = cart.find(item => item.id === productId);
  if (existing) {
    if (existing.qty >= limit) {
      showNotification(`Only ${limit} of "${product.name}" available.`);
      return;
    }
    existing.qty += 1;
  } else {
    cart.push({ ...product, qty: 1 });
  }

  saveCart();
  updateCartUI();
  showNotification(`"${product.name}" added to cart!`);
}

/* ── Remove from Cart ──────────────────────── */
function removeFromCart(productId) {
  cart = cart.filter(item => item.id !== productId);
  saveCart();
  updateCartUI();
  renderCartItems();
}

/* ── Change Quantity ───────────────────────── */
function changeQty(productId, delta) {
  const item = cart.find(i => i.id === productId);
  if (!item) return;

  if (delta > 0) {
    const limit = stockLimit(findProduct(productId) || item);
    if (item.qty + delta > limit) {
      showNotification(`Only ${limit} of "${item.name}" available.`);
      return;
    }
  }

  item.qty += delta;
  if (item.qty <= 0) {
    removeFromCart(productId);
    return;
  }
  saveCart();
  updateCartUI();
  renderCartItems();
}

/* ── Cart Totals ───────────────────────────── */
function getCartTotal() {
  return cart.reduce((sum, item) => sum + item.price * item.qty, 0);
}

function getCartCount() {
  return cart.reduce((sum, item) => sum + item.qty, 0);
}

/* ── Update Badge ──────────────────────────── */
function updateCartUI() {
  const count = getCartCount();
  document.querySelectorAll('#cartCount').forEach(el => el.textContent = count);
  document.querySelectorAll('#cartItemCount').forEach(el => el.textContent = count);
  document.querySelectorAll('#cartTotal').forEach(el => el.textContent = formatPrice(getCartTotal()));
}

/* ── Render Cart Items ─────────────────────── */
function renderCartItems() {
  const container = document.getElementById('cartItems');
  if (!container) return;

  if (cart.length === 0) {
    container.innerHTML = `
      <div class="cart-empty">
        <div class="cart-empty-icon">
          <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M16 10a4 4 0 0 1-8 0"/><path d="M3.103 6.034h17.794"/><path d="M3.4 5.467a2 2 0 0 0-.4 1.2V20a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6.667a2 2 0 0 0-.4-1.2l-2-2.667A2 2 0 0 0 17 2H7a2 2 0 0 0-1.6.8z"/></svg>
        </div>
        <p>Your cart is empty</p>
        <a href="menu.html" class="btn btn-primary btn-sm">Browse Collection</a>
      </div>`;
    return;
  }

  container.innerHTML = cart.map(item => {
    const atMax = item.qty >= stockLimit(findProduct(item.id) || item);
    return `
    <div class="cart-item" data-id="${item.id}">
      <div class="cart-item-image">
        <img src="${escHtml(item.image)}" alt="${escHtml(item.name)}" loading="lazy" />
      </div>
      <div class="cart-item-info">
        <h4>${escHtml(item.name)}</h4>
        <span class="product-category">${escHtml(item.category)}</span>
        <div class="cart-item-controls">
          <div class="qty-controls">
            <button class="qty-btn" onclick="changeQty(${item.id}, -1)" aria-label="Decrease quantity">
              <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/></svg>
            </button>
            <span class="qty-value">${item.qty}</span>
            <button class="qty-btn qty-btn--plus" onclick="changeQty(${item.id}, 1)" aria-label="Increase quantity"${atMax ? ' disabled title="Maximum available"' : ''}>+</button>
          </div>
          <span class="cart-item-price">${formatPrice(item.price * item.qty)}</span>
          <button class="remove-btn" onclick="removeFromCart(${item.id})" aria-label="Remove item">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 11v6"/><path d="M14 11v6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
          </button>
        </div>
      </div>
    </div>`;
  }).join('');
}

/* ── Notification Toast ────────────────────── */
function showNotification(message) {
  const existing = document.querySelector('.notification');
  if (existing) existing.remove();

  const notif = document.createElement('div');
  notif.className = 'notification';
  notif.textContent = message;
  document.body.appendChild(notif);

  setTimeout(() => {
    notif.style.opacity = '0';
    notif.style.transition = 'opacity 0.4s ease';
    setTimeout(() => notif.remove(), 400);
  }, 2800);
}

/* ── Cart Drawer Open / Close ──────────────── */
function openCart() {
  if (document.body.classList.contains('auth-logged-out')) {
    document.getElementById('accountBtn')?.click();
    return;
  }

  const drawer = document.getElementById('cartDrawer');
  if (!drawer) return;
  renderCartItems();
  drawer.classList.add('open');
  document.body.style.overflow = 'hidden';
}

function closeCart() {
  const drawer = document.getElementById('cartDrawer');
  if (!drawer) return;
  drawer.classList.remove('open');
  document.body.style.overflow = '';
}

/* ── Init ──────────────────────────────────── */
document.addEventListener('DOMContentLoaded', () => {
  syncCartWithCatalog();
  updateCartUI();

  const cartBtn      = document.getElementById('cartBtn');
  const closeCartBtn = document.getElementById('closeCartBtn');
  const cartOverlay  = document.getElementById('cartOverlay');

  if (cartBtn)      cartBtn.addEventListener('click', openCart);
  if (closeCartBtn) closeCartBtn.addEventListener('click', closeCart);
  if (cartOverlay)  cartOverlay.addEventListener('click', closeCart);
});