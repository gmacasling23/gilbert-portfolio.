/* ============================================
   CHECKOUT - AbiYarn
   Multi-step: Contact → Shipping → Payment
   ============================================ */
const API_BASE = 'api'; // relative path — works whether the site is at the domain root or in a subfolder

document.addEventListener('DOMContentLoaded', () => {

  let currentStep = 1;
  const TOTAL_STEPS = 3;

  /* ── Elements ────────────────────────────── */
  const checkoutModal    = document.getElementById('checkoutModal');
  const checkoutOverlay  = document.getElementById('checkoutOverlay');
  const checkoutBtn      = document.getElementById('checkoutBtn');
  const closeCheckoutBtn = document.getElementById('closeCheckoutBtn');
  const checkoutNextBtn  = document.getElementById('checkoutNextBtn');
  const backBtn          = document.getElementById('backBtn');
  const orderModal       = document.getElementById('orderModal');
  const closeOrderBtn    = document.getElementById('closeOrderBtn');

  if (!checkoutModal) return;

  /* ── Step icon map ───────────────────────── */
  const STEP_ICONS = {
    1: ICONS.user,
    2: ICONS.mapPin,
    3: ICONS.creditCard,
  };

  /* ── Open / Close ────────────────────────── */
  function openCheckout() {
    if (cart.length === 0) {
      showNotification('Your cart is empty!');
      return;
    }

    const emailField = document.getElementById('coEmail');
    const accountEmail = window.ABI_CURRENT_USER?.email;
    if (emailField && accountEmail && !emailField.value.trim()) {
      emailField.value = accountEmail;
    }

    closeCart();
    goToStep(1);

    checkoutModal.classList.add('open');
    document.body.style.overflow = 'hidden';

    renderCheckoutSummary();
  }

  function closeCheckout() {
    checkoutModal.classList.remove('open');
    document.body.style.overflow = '';
  }

  if (checkoutBtn) {
    checkoutBtn.addEventListener('click', openCheckout);
  }

  if (closeCheckoutBtn) {
    closeCheckoutBtn.addEventListener('click', closeCheckout);
  }

  if (checkoutOverlay) {
    checkoutOverlay.addEventListener('click', closeCheckout);
  }

  /* ── Step Navigation ─────────────────────── */
  function goToStep(step) {
    currentStep = step;

    document.querySelectorAll('.step').forEach(s => {
      const n = parseInt(s.dataset.step);

      s.classList.toggle('active', n === step);
      s.classList.toggle('completed', n < step);

      // Swap step number for check icon when completed
      const numEl = s.querySelector('.step-number');

      if (numEl) {
        if (n < step) {
          numEl.innerHTML = ICONS.check;
          numEl.classList.add('step-number--done');
        } else {
          numEl.innerHTML = STEP_ICONS[n] || n;
          numEl.classList.remove('step-number--done');
        }
      }
    });

    document
      .querySelectorAll('.checkout-step')
      .forEach(panel => panel.classList.remove('active'));

    const active = document.getElementById(`coStep${step}`);

    if (active) {
      active.classList.add('active');
    }

    // Next button label
    if (checkoutNextBtn) {
      checkoutNextBtn.innerHTML = step === TOTAL_STEPS
        ? `${ICONS.save} Place Order`
        : `Continue`;
    }

    // Back button
    if (backBtn) {
      backBtn.style.display = step > 1 ? 'inline-flex' : 'none';
      backBtn.innerHTML = ICONS.arrowLeft;
    }
  }

  /* ── Next / Continue Button ──────────────── */
  if (checkoutNextBtn) {
    checkoutNextBtn.addEventListener('click', () => {

      if (!validateStep(currentStep)) {
        return;
      }

      if (currentStep < TOTAL_STEPS) {
        goToStep(currentStep + 1);
      } else {
        placeOrder();
      }
    });
  }

  /* ── Back Button ─────────────────────────── */
  if (backBtn) {
    backBtn.addEventListener('click', () => {
      if (currentStep > 1) {
        goToStep(currentStep - 1);
      }
    });
  }

  /* ── Validation ──────────────────────────── */
  function validateStep(step) {

    const method = getPaymentMethod();

    const step3Fields =
      method === 'card'
        ? ['coCard', 'coExpiry', 'coCvc']
        : method === 'gcash'
        ? ['coGcashRef']
        : [];

    // COD does not require extra payment fields

    const fields = {
      1: ['coName', 'coEmail', 'coPhone'],
      2: ['coAddress', 'coCity', 'coPostal', 'coCountry'],
      3: step3Fields
    };

    let valid = true;

    (fields[step] || []).forEach(id => {

      const el = document.getElementById(id);

      if (!el || !el.value.trim()) {

        if (el) {
          highlightError(el);
        }

        valid = false;
      }
    });

    // Validate email format
    if (step === 1) {

      const email = document.getElementById('coEmail');

      if (
        email &&
        email.value &&
        !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value)
      ) {
        highlightError(email);
        valid = false;
      }
    }

    if (!valid) {
      showNotification('Please fill in all required fields.');
    }

    return valid;
  }

  /* ── Highlight Validation Error ──────────── */
  function highlightError(el) {

    el.style.borderColor = 'var(--red-err)';
    el.style.boxShadow =
      '0 0 0 3px rgba(239,68,68,0.15)';

    el.addEventListener(
      'input',
      () => {
        el.style.borderColor = '';
        el.style.boxShadow = '';
      },
      { once: true }
    );
  }

  /* ── Checkout Summary ────────────────────── */
  function renderCheckoutSummary() {

    const summary = document.getElementById('checkoutSummary');
    const total = document.getElementById('checkoutTotal');

    if (!summary) {
      return;
    }

    summary.innerHTML = cart
      .map(item => `
        <div class="summary-item">
          <span>${item.name} × ${item.qty}</span>
          <span>${formatPrice(item.price * item.qty)}</span>
        </div>
      `)
      .join('');

    if (total) {
      total.textContent = formatPrice(getCartTotal());
    }
  }

  /* ── Payment Method Switching ────────────── */
  function getPaymentMethod() {

    const checked =
      document.querySelector(
        'input[name="paymentMethod"]:checked'
      );

    return checked ? checked.value : 'cod';
  }

  function showPaymentPanel(method) {

    ['cod', 'gcash', 'card'].forEach(m => {

      const panel = document.getElementById(
        `panel${m.charAt(0).toUpperCase() + m.slice(1)}`
      );

      if (panel) {
        panel.style.display =
          m === method ? '' : 'none';
      }
    });
  }

  document
    .querySelectorAll('input[name="paymentMethod"]')
    .forEach(radio => {

      radio.addEventListener('change', () => {
        showPaymentPanel(radio.value);
      });

    });

  /* ── Card Input Formatting ───────────────── */
  const coCard = document.getElementById('coCard');

  if (coCard) {

    coCard.addEventListener('input', e => {

      let v = e.target.value
        .replace(/\D/g, '')
        .substring(0, 16);

      e.target.value = v
        .replace(/(.{4})/g, '$1 ')
        .trim();

    });
  }

  /* ── Card Expiry Formatting ──────────────── */
  const coExpiry = document.getElementById('coExpiry');

  if (coExpiry) {

    coExpiry.addEventListener('input', e => {

      let v = e.target.value
        .replace(/\D/g, '')
        .substring(0, 4);

      if (v.length >= 2) {
        v =
          v.slice(0, 2) +
          '/' +
          v.slice(2);
      }

      e.target.value = v;

    });
  }

  /* ── Place Order ─────────────────────────── */
  async function placeOrder() {

    const orderNum =
      'AY-' +
      Math.random()
        .toString(36)
        .substring(2, 8)
        .toUpperCase();

    const nameField =
      document.getElementById('coName');

    const emailField =
      document.getElementById('coEmail');

    const phoneField =
      document.getElementById('coPhone');

    const addrField =
      document.getElementById('coAddress');

    const cityField =
      document.getElementById('coCity');

    const postalField =
      document.getElementById('coPostal');

    const countryField =
      document.getElementById('coCountry');

    const method =
      getPaymentMethod();

    const methodLabel = {
      cod: 'Cash on Delivery',
      gcash: 'GCash',
      card: 'Card'
    }[method] || method;

    const gcashRefEl =
      document.getElementById('coGcashRef');

    /* ── Order Payload ─────────────────────── */
    const payload = {

      order_num: orderNum,

      name: nameField
        ? nameField.value.trim()
        : '',

      email: emailField
        ? emailField.value.trim()
        : '',

      phone: phoneField
        ? phoneField.value.trim()
        : '',

      address: addrField
        ? addrField.value.trim()
        : '',

      city: cityField
        ? cityField.value.trim()
        : '',

      postal: postalField
        ? postalField.value.trim()
        : '',

      country: countryField
        ? countryField.value.trim()
        : 'Philippines',

      payment: methodLabel,

      gcash_ref:
        method === 'gcash' && gcashRefEl
          ? gcashRefEl.value.trim()
          : null,

      total: getCartTotal(),

      // `id` is what the server uses to deduct stock; name/price are for display only
      items: cart.map(i => ({
        id: i.id,
        name: i.name,
        qty: i.qty,
        price: i.price
      }))
    };

    /* ── Save to PHP + MySQL ───────────────── */
    // The server deducts stock and saves the order in one transaction.
    // If it refuses (out of stock, price changed, invalid data) we stop here:
    // the cart is kept and NO confirmation is shown.
    try {

      const res = await fetch(
        `${API_BASE}/save-order.php`,
        {
          method: 'POST',

          headers: {
            'Content-Type':
              'application/json'
          },

          body: JSON.stringify(payload)
        }
      );

      let data = {};

      try {
        data = await res.json();
      } catch (_) {}

      if (!res.ok || data.success === false) {

        console.warn('Order save failed:', res.status, data);

        showNotification(
          data.error ||
          'We could not place your order. Please try again.'
        );

        // Stock/price problems: reload the catalog so the menu is up to date
        if (res.status === 409) {
          setTimeout(() => location.reload(), 2500);
        }

        return;
      }

    } catch (err) {

      console.warn(
        'Could not reach order API:',
        err
      );

      showNotification('We could not reach the order server. Your cart has been kept; please try again.');
      return;
    }

    /* ── Clear Cart ────────────────────────── */
    cart = [];

    saveCart();
    updateCartUI();

    closeCheckout();

    /* ── Order Confirmation ───────────────── */
    const confirmEmail =
      document.getElementById('confirmEmail');

    const orderNumber =
      document.getElementById('orderNumber');

    const orderDetails =
      document.getElementById('orderDetails');

    if (confirmEmail) {
      confirmEmail.textContent =
        payload.email;
    }

    if (orderNumber) {
      orderNumber.textContent =
        orderNum;
    }

    if (orderDetails) {

      orderDetails.innerHTML = `
        <div class="order-confirmed-msg">

          <span class="order-confirmed-icon">
            ${ICONS.package}
          </span>

          <p>
            Your handmade order is confirmed
            and Abi will be in touch shortly
            to arrange delivery.
          </p>

        </div>
      `;
    }

    /* ── Open Order Confirmation Modal ─────── */
    const orderModal =
      document.getElementById('orderModal');

    if (orderModal) {

      orderModal.classList.add('open');

      document.body.style.overflow =
        'hidden';
    }
  }

  /* ── Close Order Modal ───────────────────── */
  if (closeOrderBtn) {

    closeOrderBtn.addEventListener(
      'click',
      () => {

        if (orderModal) {
          orderModal.classList.remove('open');
        }

        document.body.style.overflow = '';

      }
    );
  }

  /* ── Close Modals by Overlay ─────────────── */
  document
    .querySelectorAll('.modal-overlay')
    .forEach(overlay => {

      overlay.addEventListener('click', e => {

        const modal =
          e.target.closest('.modal');

        if (modal) {

          modal.classList.remove('open');

          document.body.style.overflow = '';
        }
      });

    });

});