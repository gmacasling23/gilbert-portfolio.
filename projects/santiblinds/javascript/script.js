/* ============================================
   SANTI BLINDS — script.js
   ============================================ */

'use strict';

/* ---- PUBLIC SITE: Color theme toggle ---- */
(function initThemeToggle() {
  const nav = document.getElementById('nav');
  const hamburger = document.getElementById('hamburger');
  if (!nav || !hamburger) return;

  const themeToggle = document.createElement('button');
  themeToggle.type = 'button';
  themeToggle.className = 'theme-toggle';
  themeToggle.innerHTML = `
    <svg class="theme-toggle__moon" viewBox="0 0 24 24" aria-hidden="true">
      <path d="M20.2 15.1A8.5 8.5 0 0 1 8.9 3.8 8.5 8.5 0 1 0 20.2 15.1Z" />
    </svg>
    <svg class="theme-toggle__sun" viewBox="0 0 24 24" aria-hidden="true">
      <circle cx="12" cy="12" r="4" />
      <path d="M12 2v2m0 16v2M4.93 4.93l1.42 1.42m11.3 11.3 1.42 1.42M2 12h2m16 0h2M4.93 19.07l1.42-1.42m11.3-11.3 1.42-1.42" />
    </svg>`;

  function applyTheme(theme) {
    const isDark = theme === 'dark';
    document.documentElement.dataset.theme = isDark ? 'dark' : 'light';
    themeToggle.setAttribute('aria-label', `Switch to ${isDark ? 'light' : 'dark'} mode`);
    themeToggle.title = `Switch to ${isDark ? 'light' : 'dark'} mode`;
  }

  const savedTheme = localStorage.getItem('santi-theme');
  applyTheme(savedTheme === 'dark' ? 'dark' : 'light');

  themeToggle.addEventListener('click', () => {
    const nextTheme = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark';
    localStorage.setItem('santi-theme', nextTheme);
    applyTheme(nextTheme);
  });

  hamburger.parentElement.insertBefore(themeToggle, hamburger);
})();

/* ---- NAV: Scroll & Mobile Toggle ---- */
(function initNav() {
  const nav = document.getElementById('nav');
  const hamburger = document.getElementById('hamburger');
  const navLinks = document.getElementById('navLinks');
  if (!nav || !hamburger || !navLinks) return;

  // Scroll state
  window.addEventListener('scroll', () => {
    nav.classList.toggle('scrolled', window.scrollY > 40);
  }, { passive: true });

  // Hamburger toggle
  hamburger.addEventListener('click', () => {
    const isOpen = navLinks.classList.toggle('open');
    hamburger.classList.toggle('open', isOpen);
    hamburger.setAttribute('aria-expanded', isOpen);
  });

  // Close on link click (mobile)
  navLinks.querySelectorAll('a').forEach(link => {
    link.addEventListener('click', () => {
      navLinks.classList.remove('open');
      hamburger.classList.remove('open');
    });
  });

  // Close on outside click
  document.addEventListener('click', (e) => {
    if (!nav.contains(e.target)) {
      navLinks.classList.remove('open');
      hamburger.classList.remove('open');
    }
  });
})();

/* ---- CUSTOMER: Shared login modal ---- */
(function initLoginModal() {
  const accountHost = document.getElementById('navAccount');
  if (!accountHost) return;

  const modal = document.createElement('div');
  modal.className = 'login-modal';
  modal.id = 'loginModal';
  modal.hidden = true;
  modal.innerHTML = `
    <div class="login-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="loginModalTitle">
      <button type="button" class="login-modal__close" aria-label="Close login dialog">&times;</button>
      <div class="login-modal__card order-panel order-panel--narrow">
        <h2 class="order-panel__title" id="loginModalTitle">Log in</h2>
        <form class="login-modal__form" novalidate>
          <div class="form-group">
            <label for="loginModalEmail">Email</label>
            <input type="email" id="loginModalEmail" name="email" autocomplete="username" required />
          </div>
          <div class="form-group">
            <label for="loginModalPassword">Password</label>
            <div class="password-field">
              <input type="password" id="loginModalPassword" name="password" autocomplete="current-password" required />
              <button type="button" class="password-toggle" data-password-toggle="loginModalPassword" aria-label="Show password" aria-pressed="false" title="Show password">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path class="password-eye__outline" d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/><path class="password-eye__slash" d="m3 3 18 18"/></svg>
              </button>
            </div>
          </div>
          <p class="login-modal__error" role="alert" hidden></p>
          <button type="submit" class="btn btn--primary btn--full login-modal__submit">Log In</button>
          <p class="order-switch">New to Santi Blinds? <a class="order-link login-modal__register" href="order.html#register">Create an account</a></p>
        </form>
      </div>
    </div>`;
  document.body.append(modal);

  const form = modal.querySelector('.login-modal__form');
  const emailInput = modal.querySelector('#loginModalEmail');
  const passwordInput = modal.querySelector('#loginModalPassword');
  const submitButton = modal.querySelector('.login-modal__submit');
  const errorMessage = modal.querySelector('.login-modal__error');
  const closeButton = modal.querySelector('.login-modal__close');
  const dialog = modal.querySelector('.login-modal__dialog');
  let returnFocus = null;
  let previousOverflow = '';
  let previousPaddingRight = '';
  let closeTimer = 0;

  function open() {
    if (!modal.hidden) return;
    window.clearTimeout(closeTimer);
    returnFocus = document.activeElement;
    previousOverflow = document.body.style.overflow;
    previousPaddingRight = document.body.style.paddingRight;
    const scrollbarWidth = window.innerWidth - document.documentElement.clientWidth;
    document.body.style.overflow = 'hidden';
    if (scrollbarWidth > 0) {
      const currentPadding = Number.parseFloat(getComputedStyle(document.body).paddingRight) || 0;
      document.body.style.paddingRight = `${currentPadding + scrollbarWidth}px`;
    }
    modal.hidden = false;
    modal.getBoundingClientRect();
    modal.classList.add('is-open');
    emailInput.focus();
  }

  function close() {
    if (modal.hidden || modal.classList.contains('is-closing')) return;
    modal.classList.remove('is-open');
    modal.classList.add('is-closing');
    document.body.style.overflow = previousOverflow;
    document.body.style.paddingRight = previousPaddingRight;
    closeTimer = window.setTimeout(() => {
      modal.hidden = true;
      modal.classList.remove('is-closing');
      if (returnFocus && returnFocus.isConnected && typeof returnFocus.focus === 'function') {
        returnFocus.focus();
      } else {
        accountHost.querySelector('.acct__btn')?.focus();
      }
    }, 220);
  }

  accountHost.addEventListener('click', event => {
    const loginLink = event.target.closest('.nav__login');
    if (!loginLink) return;
    event.preventDefault();
    open();
  });
  document.addEventListener('santi:open-login', open);
  closeButton.addEventListener('click', close);
  modal.addEventListener('click', event => {
    if (event.target === modal) close();
  });
  document.addEventListener('keydown', event => {
    if (modal.hidden) return;
    if (event.key === 'Escape') {
      close();
      return;
    }
    if (event.key !== 'Tab') return;
    const focusable = [...dialog.querySelectorAll('button:not(:disabled), input:not(:disabled), a[href]')]
      .filter(element => !element.hidden);
    const first = focusable[0];
    const last = focusable[focusable.length - 1];
    if (event.shiftKey && document.activeElement === first) {
      event.preventDefault();
      last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
      event.preventDefault();
      first.focus();
    }
  });

  form.addEventListener('submit', async event => {
    event.preventDefault();
    errorMessage.hidden = true;
    const email = emailInput.value.trim();
    const password = passwordInput.value;
    if (!/^\S+@\S+\.\S+$/.test(email) || !password) {
      errorMessage.textContent = 'Please enter your email and password.';
      errorMessage.hidden = false;
      return;
    }

    submitButton.disabled = true;
    try {
      const response = await fetch('portal/customer_login.php', {
        method: 'POST',
        credentials: 'include',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email, password }),
      });
      const data = await response.json();
      if (!response.ok || !data.success) {
        const message = Array.isArray(data.errors) ? data.errors.join(' ') : data.errors || data.message;
        throw new Error(message || 'Could not log you in. Please check your details and try again.');
      }

      form.reset();
      const eventName = document.getElementById('orderApp') ? 'santi:login' : 'santi:auth';
      window.dispatchEvent(new CustomEvent(eventName, { detail: { customer: data.customer } }));
      close();
    } catch (error) {
      errorMessage.textContent = error.message || 'Could not reach the server. Please try again.';
      errorMessage.hidden = false;
    } finally {
      submitButton.disabled = false;
    }
  });

  modal.querySelector('.login-modal__register').addEventListener('click', event => {
    if (!document.getElementById('orderApp')) return;
    event.preventDefault();
    window.dispatchEvent(new CustomEvent('santi:register'));
    close();
  });
})();

/* ---- ADMIN: Mobile sidebar ---- */
(function initAdminMobileNav() {
  const shell = document.querySelector('.admin-shell');
  const sidebar = shell?.querySelector('.admin-sidebar');
  if (!shell || !sidebar) return;

  sidebar.id = 'adminSidebar';

  const header = document.createElement('div');
  header.className = 'admin-mobile-header';

  const toggle = document.createElement('button');
  toggle.type = 'button';
  toggle.className = 'admin-mobile-menu-toggle';
  toggle.setAttribute('aria-label', 'Open admin menu');
  toggle.setAttribute('aria-controls', sidebar.id);
  toggle.setAttribute('aria-expanded', 'false');
  for (let i = 0; i < 3; i += 1) {
    const line = document.createElement('span');
    line.setAttribute('aria-hidden', 'true');
    toggle.append(line);
  }

  const title = document.createElement('span');
  title.className = 'admin-mobile-header__title';
  title.textContent = 'Admin Panel';
  header.append(toggle, title);

  const backdrop = document.createElement('button');
  backdrop.type = 'button';
  backdrop.className = 'admin-sidebar-backdrop';
  backdrop.setAttribute('aria-label', 'Close admin menu');

  shell.insertBefore(header, sidebar);
  shell.append(backdrop);

  const closeMenu = () => {
    shell.classList.remove('is-sidebar-open');
    document.body.classList.remove('admin-menu-open');
    toggle.setAttribute('aria-expanded', 'false');
    toggle.setAttribute('aria-label', 'Open admin menu');
  };

  toggle.addEventListener('click', () => {
    const isOpen = shell.classList.toggle('is-sidebar-open');
    document.body.classList.toggle('admin-menu-open', isOpen);
    toggle.setAttribute('aria-expanded', String(isOpen));
    toggle.setAttribute('aria-label', isOpen ? 'Close admin menu' : 'Open admin menu');
  });
  backdrop.addEventListener('click', closeMenu);
  sidebar.querySelectorAll('.admin-nav__link').forEach(link => {
    link.addEventListener('click', closeMenu);
  });
  document.addEventListener('keydown', event => {
    if (event.key === 'Escape') closeMenu();
  });
  window.addEventListener('resize', () => {
    if (window.innerWidth > 900) closeMenu();
  });
})();


/* ---- ACCOUNT MENU (nav) ----
   Facebook-style account dropdown: avatar button -> name card, "View Orders", "Log out".
   Shows a "Log in" button when nobody is signed in. The order page announces
   login/logout through the 'santi:auth' event so the menu stays in sync. */
(function initAccountMenu() {
  const host = document.getElementById('navAccount');
  if (!host) return;

  const esc = (v) => String(v ?? '').replace(/[&<>"']/g, ch => (
    { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch]));
  const initials = (name) => {
    const parts = String(name || '').trim().split(/\s+/).filter(Boolean);
    if (!parts.length) return '?';
    return (parts[0][0] + (parts.length > 1 ? parts[parts.length - 1][0] : '')).toUpperCase();
  };

  const ICON_ORDERS = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 3h9l4 4v14H6z"/><path d="M14 3v5h5"/><path d="M9 13h7M9 17h5"/></svg>';
  const ICON_LOGOUT = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10 4H5v16h5"/><path d="M15 8l4 4-4 4"/><path d="M19 12H9"/></svg>';

  let customer = null;

  function close() {
    const menu = host.querySelector('.acct__menu');
    const btn = host.querySelector('.acct__btn');
    if (menu) menu.hidden = true;
    if (btn) btn.setAttribute('aria-expanded', 'false');
  }

  function render() {
    if (!customer) {
      host.innerHTML = '<a href="order.html" class="nav__login">Log in</a>';
      return;
    }
    const name = esc(customer.full_name);
    host.innerHTML = `
      <button type="button" class="acct__btn" aria-haspopup="menu" aria-expanded="false" aria-label="Account menu for ${name}">
        <span class="acct__avatar">${esc(initials(customer.full_name))}</span>
      </button>
      <div class="acct__menu" role="menu" hidden>
        <div class="acct__card">
          <span class="acct__avatar acct__avatar--lg">${esc(initials(customer.full_name))}</span>
          <span class="acct__who"><strong>${name}</strong><small>${esc(customer.email)}</small></span>
        </div>
        <a href="my-orders.html" class="acct__item" role="menuitem">
          <span class="acct__icon">${ICON_ORDERS}</span><span>View Orders</span>
        </a>
        <button type="button" class="acct__item" role="menuitem" data-acct-logout>
          <span class="acct__icon">${ICON_LOGOUT}</span><span>Log out</span>
        </button>
      </div>`;
  }

  async function logout() {
    try {
      await fetch('portal/customer_logout.php', { method: 'POST', credentials: 'include' });
    } catch (_) { /* leave the page either way */ }
    customer = null;
    // Pages that depend on the session start over; everything else just updates the nav.
    if (document.getElementById('ordersApp')) { window.location.href = 'order.html'; return; }
    if (document.getElementById('orderApp')) { window.location.reload(); return; }
    render();
  }

  host.addEventListener('click', (e) => {
    if (e.target.closest('[data-acct-logout]')) { logout(); return; }
    const btn = e.target.closest('.acct__btn');
    if (btn) {
      const menu = host.querySelector('.acct__menu');
      const open = menu.hidden;
      menu.hidden = !open;
      btn.setAttribute('aria-expanded', String(open));
    }
  });
  document.addEventListener('click', (e) => { if (!host.contains(e.target)) close(); });
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') { close(); const b = host.querySelector('.acct__btn'); if (b) b.focus(); }
  });
  window.addEventListener('santi:auth', (e) => {
    customer = (e.detail && e.detail.customer) || null;
    render();
  });

  render();   // "Log in" until we know better, so the nav never jumps around empty
  fetch('portal/customer_session.php', { credentials: 'include' })
    .then(r => r.json())
    .then(d => { customer = d && d.success && d.logged_in ? d.customer : null; render(); })
    .catch(() => { /* server unreachable: keep the "Log in" link */ });
})();


/* ---- SCROLL REVEAL ---- */
(function initReveal() {
  const elements = document.querySelectorAll('.reveal');
  if (!elements.length) return;

  function revealIfVisible(el) {
    const rect = el.getBoundingClientRect();
    return rect.top < window.innerHeight * 0.92;
  }

  const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        const el = entry.target;
        const delay = el.dataset.delay ? parseInt(el.dataset.delay) : 0;
        setTimeout(() => {
          el.classList.add('in-view');
        }, delay);
        observer.unobserve(el);
      }
    });
  }, {
    threshold: 0.08,
    rootMargin: '0px 0px -40px 0px'
  });

  elements.forEach(el => {
    if (revealIfVisible(el)) {
      const delay = el.dataset.delay ? parseInt(el.dataset.delay) : 0;
      setTimeout(() => el.classList.add('in-view'), 100 + delay);
    } else {
      observer.observe(el);
    }
  });
})();


/* ---- HERO: Animate in on load (index page only) ---- */
(function initHeroLoad() {
  const heroReveals = document.querySelectorAll('.hero .reveal');
  if (!heroReveals.length) return;
  let delay = 200;
  heroReveals.forEach(el => {
    setTimeout(() => {
      el.classList.add('in-view');
    }, delay);
    delay += 120;
  });
})();


/* ---- PAGE HERO INNER PAGES: Animate in on load ---- */
(function initPageHeroLoad() {
  const pageHeroReveals = document.querySelectorAll('.page-hero .reveal');
  if (!pageHeroReveals.length) return;
  let delay = 150;
  pageHeroReveals.forEach(el => {
    setTimeout(() => {
      el.classList.add('in-view');
    }, delay);
    delay += 140;
  });
})();


/* ---- TESTIMONIALS SLIDER ---- */
(function initTestimonials() {
  const slides = document.querySelectorAll('.testimonial');
  const dotsContainer = document.getElementById('tDots');
  const prevBtn = document.getElementById('tPrev');
  const nextBtn = document.getElementById('tNext');
  if (!slides.length || !dotsContainer) return;

  let current = 0;
  let autoTimer;

  slides.forEach((_, i) => {
    const dot = document.createElement('button');
    dot.className = 't-dot' + (i === 0 ? ' active' : '');
    dot.setAttribute('aria-label', `Go to testimonial ${i + 1}`);
    dot.addEventListener('click', () => goTo(i));
    dotsContainer.appendChild(dot);
  });

  function goTo(index) {
    slides[current].classList.remove('active');
    dotsContainer.children[current].classList.remove('active');
    current = (index + slides.length) % slides.length;
    slides[current].classList.add('active');
    dotsContainer.children[current].classList.add('active');
    resetAuto();
  }

  function resetAuto() {
    clearInterval(autoTimer);
    autoTimer = setInterval(() => goTo(current + 1), 5000);
  }

  if (prevBtn) prevBtn.addEventListener('click', () => goTo(current - 1));
  if (nextBtn) nextBtn.addEventListener('click', () => goTo(current + 1));

  let touchStartX = 0;
  const slider = document.getElementById('testimonialSlider');
  if (slider) {
    slider.addEventListener('touchstart', e => {
      touchStartX = e.touches[0].clientX;
    }, { passive: true });
    slider.addEventListener('touchend', e => {
      const diff = touchStartX - e.changedTouches[0].clientX;
      if (Math.abs(diff) > 40) goTo(diff > 0 ? current + 1 : current - 1);
    }, { passive: true });
  }

  resetAuto();
})();


/* ---- CONTACT FORM ---- */
(function initForm() {

  const form = document.getElementById('contactForm');
  if (!form) return;

  // ── Field error helpers ──────────────────────────────────

  function setError(field, msg) {
    const group = field.closest('.form-group') || field.closest('.form-check') || field.parentElement;
    group.classList.add('field--error');
    let hint = group.querySelector('.field-error-msg');
    if (!hint) {
      hint = document.createElement('span');
      hint.className = 'field-error-msg';
      group.appendChild(hint);
    }
    hint.textContent = msg;
  }

  function clearError(field) {
    const group = field.closest('.form-group') || field.closest('.form-check') || field.parentElement;
    group.classList.remove('field--error');
    const hint = group.querySelector('.field-error-msg');
    if (hint) hint.textContent = '';
  }

  function isValidEmail(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
  }

  function shakeField(field) {
    field.style.animation = 'none';
    field.offsetHeight;
    field.style.animation = 'shake 0.4s ease';
  }

  // Product list comes from the database (inquiry.product_id).
  (async function loadProducts() {
    try {
      const res = await fetch('portal/catalog.php', { credentials: 'include' });
      const data = await res.json();
      if (!data.success) return;
      form.interest.insertAdjacentHTML('beforeend',
        data.products.map(p => `<option value="${p.product_id}">${p.name.replace(/[&<>"]/g, '')}</option>`).join(''));
    } catch (_) { /* the select just stays empty; submit will ask for a product */ }
  })();

  // Clear error on input/change
  form.querySelectorAll('input, select, textarea').forEach(el => {
    el.addEventListener('input', () => clearError(el));
    el.addEventListener('change', () => clearError(el));
  });

  // ── Validate all fields ──────────────────────────────────

  function validateForm() {
    let valid = true;

    const fields = [
      { el: form.fname,     msg: 'First name is required.' },
      { el: form.lname,     msg: 'Last name is required.' },
      { el: form.phone,     msg: 'Phone / mobile number is required.' },
      { el: form.city,      msg: 'City / municipality is required.' },
    ];

    fields.forEach(({ el, msg }) => {
      if (!el.value.trim()) {
        setError(el, msg);
        shakeField(el);
        valid = false;
      }
    });

    if (form.phone.value.trim() && !window.SantiPhone.isValid(form.phone.value)) {
      setError(form.phone, 'Enter a valid Philippine mobile number, such as +63 917 123 4567.');
      shakeField(form.phone);
      valid = false;
    }

    const emailVal = form.email.value.trim();
    if (!emailVal) {
      setError(form.email, 'Email address is required.');
      shakeField(form.email);
      valid = false;
    } else if (!isValidEmail(emailVal)) {
      setError(form.email, 'Please enter a valid email address.');
      shakeField(form.email);
      valid = false;
    }

    const selects = [
      { el: form.interest,  msg: "Please select a product you're interested in." },
      { el: form.preferred, msg: 'Please select a preferred schedule.' },
      { el: form.rooms,     msg: 'Please select the number of windows / rooms.' },
      { el: form.budget,    msg: 'Please select a budget range.' },
    ];

    selects.forEach(({ el, msg }) => {
      if (!el.value) {
        setError(el, msg);
        shakeField(el);
        valid = false;
      }
    });

    const consent = form.consent;
    if (!consent.checked) {
      setError(consent, 'You must agree to be contacted before submitting.');
      valid = false;
    }

    return valid;
  }

  // ── Success modal ────────────────────────────────────────

  function showSuccessModal() {
    const existing = document.getElementById('enquirySuccessModal');
    if (existing) existing.remove();

    const modal = document.createElement('div');
    modal.id = 'enquirySuccessModal';
    modal.style.cssText = `
      position:fixed;inset:0;z-index:9999;
      display:flex;align-items:center;justify-content:center;
      background:rgba(var(--c-rgb-overlay),0.48);
      backdrop-filter:blur(6px);
      padding:20px;
      animation:fadeInModal 0.3s ease;
    `;

    modal.innerHTML = `
      <div style="
        background:var(--c-surface);
        border:1px solid rgba(var(--c-rgb-accent),0.3);
        border-radius:12px;
        padding:48px 40px 40px;
        max-width:460px;width:100%;
        text-align:center;
        position:relative;
        animation:scaleInModal 0.35s cubic-bezier(.34,1.56,.64,1);
      ">
        <button id="enquiryModalClose" aria-label="Close" style="
          position:absolute;top:16px;right:18px;
          background:none;border:none;cursor:pointer;
          color:var(--c-muted);font-size:22px;line-height:1;padding:4px 8px;
        ">&#x2715;</button>

        <div style="
          width:56px;height:56px;border-radius:50%;
          background:rgba(var(--c-rgb-accent),0.12);
          border:1.5px solid rgba(var(--c-rgb-accent),0.4);
          display:flex;align-items:center;justify-content:center;
          margin:0 auto 24px;font-size:22px;color:var(--c-accent);
        ">&#x2726;</div>

        <h2 style="
          margin:0 0 10px;
          font-family:'Anton',sans-serif;
          font-size:clamp(1.6rem,4vw,2rem);
          letter-spacing:.06em;
          color:var(--c-accent);
        ">Enquiry Sent</h2>

        <p style="
          margin:0 0 6px;
          font-family:'Quicksand',sans-serif;
          font-size:1rem;
          color:var(--c-body);
          line-height:1.6;
        ">Thank you! We'll be in touch within one business day.</p>

        <p style="
          margin:0 0 32px;
          font-family:'Quicksand',sans-serif;
          font-size:.875rem;
          color:var(--c-muted);
          line-height:1.6;
        ">In the meantime, feel free to browse our collection.</p>

        <a href="products.html" style="
          display:inline-block;
          padding:.75rem 2rem;
          background:var(--c-accent);
          color:var(--c-surface);
          font-family:'Quicksand',sans-serif;
          font-weight:700;
          font-size:.875rem;
          letter-spacing:.08em;
          text-transform:uppercase;
          text-decoration:none;
          border-radius:4px;
        ">View Our Products</a>
      </div>
    `;

    if (!document.getElementById('enquiryModalStyles')) {
      const style = document.createElement('style');
      style.id = 'enquiryModalStyles';
      style.textContent = `
        @keyframes fadeInModal  { from { opacity:0 } to { opacity:1 } }
        @keyframes scaleInModal { from { opacity:0; transform:scale(.88) } to { opacity:1; transform:scale(1) } }
      `;
      document.head.appendChild(style);
    }

    document.body.appendChild(modal);
    document.body.style.overflow = 'hidden';

    function closeModal() {
      modal.style.animation = 'fadeInModal 0.2s ease reverse';
      setTimeout(() => {
        modal.remove();
        document.body.style.overflow = '';
      }, 200);
    }

    document.getElementById('enquiryModalClose').addEventListener('click', closeModal);
    modal.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });
    document.addEventListener('keydown', function handler(e) {
      if (e.key === 'Escape') { closeModal(); document.removeEventListener('keydown', handler); }
    });
  }

  // ── Form submit ──────────────────────────────────────────

  form.addEventListener('submit', async (e) => {
    e.preventDefault();

    if (!validateForm()) {
      const firstError = form.querySelector('.field--error input, .field--error select, .field--error textarea, .field--error');
      if (firstError) firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
      return;
    }

    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn ? submitBtn.textContent : '';
    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.textContent = 'Sending\u2026';
    }

    try {
      const v = (el) => el.value.trim();
      // ERD's INQUIRY has no schedule/rooms/budget columns, so they go into the message.
      const extra = [
        form.preferred.value && `Preferred schedule: ${form.preferred.value}`,
        form.rooms.value && `Windows / rooms: ${form.rooms.value}`,
        form.budget.value && `Budget: ${form.budget.value}`,
      ].filter(Boolean);
      const response = await fetch('portal/inquiry.php', {
        method: 'POST',
        credentials: 'include',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          customer: { full_name: `${v(form.fname)} ${v(form.lname)}`, email: v(form.email), phone: window.SantiPhone.toInternational(v(form.phone)), address: v(form.city) },
          product_id: Number(form.interest.value),
          message: [v(form.message), ...extra].filter(Boolean).join('\n'),
        }),
      });
      let json = null;
      try { json = JSON.parse(await response.text()); } catch (_) { /* handled below */ }

      if (json && json.success) {
        form.reset();
        showSuccessModal();
      } else {
        showFormError(form, (json && (Array.isArray(json.errors) ? json.errors.join(' ') : json.message)) || 'Something went wrong. Please try again.');
      }
    } catch (err) {
      showFormError(form, 'Could not reach the server. Please check your connection and try again.');
    } finally {
      if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = originalText; }
    }
  });

  function showFormError(form, message) {
    let errEl = form.querySelector('.form-error-banner');
    if (!errEl) {
      errEl = document.createElement('p');
      errEl.className = 'form-error-banner';
      errEl.style.cssText = 'color:var(--c-danger);background:var(--c-danger-bg);border:1px solid var(--c-border);border-radius:6px;padding:.75rem 1rem;margin-bottom:1rem;font-size:.9rem;';
      form.prepend(errEl);
    }
    errEl.textContent = message;
    errEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
  }
})();


/* ---- GALLERY: Filter + Lightbox ---- */
(function initGallery() {
  const filterBtns = document.querySelectorAll('.filter-btn');
  const galleryItems = document.querySelectorAll('.gallery-grid__item');

  if (filterBtns.length) {
    filterBtns.forEach(btn => {
      btn.addEventListener('click', () => {
        filterBtns.forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        const filter = btn.dataset.filter;
        galleryItems.forEach(item => {
          if (filter === 'all' || item.dataset.room === filter) {
            item.classList.remove('hidden');
          } else {
            item.classList.add('hidden');
          }
        });
      });
    });
  }

  const productFilterBtns = document.querySelectorAll('.filter-btn[data-filter]');
  const productCards = document.querySelectorAll('.product-card[data-category]');
  if (productCards.length && productFilterBtns.length) {
    productFilterBtns.forEach(btn => {
      btn.addEventListener('click', () => {
        productFilterBtns.forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        const filter = btn.dataset.filter;
        productCards.forEach(card => {
          if (filter === 'all' || card.dataset.category === filter) {
            card.style.display = '';
          } else {
            card.style.display = 'none';
          }
        });
      });
    });
  }

  const lightbox   = document.getElementById('lightbox');
  const lbBackdrop = document.getElementById('lightboxBackdrop');
  const lbImg      = document.getElementById('lightboxImg');
  const lbInfo     = document.getElementById('lightboxInfo');
  const lbClose    = document.getElementById('lightboxClose');
  const lbPrev     = document.getElementById('lightboxPrev');
  const lbNext     = document.getElementById('lightboxNext');
  if (!lightbox) return;

  let currentIdx = 0;
  const getVisible = () => [...galleryItems].filter(i => !i.classList.contains('hidden'));

  function openLightbox(idx) {
    const visible = getVisible();
    if (!visible.length) return;
    currentIdx = ((idx % visible.length) + visible.length) % visible.length;
    const item = visible[currentIdx];
    const fill = item.querySelector('.gallery-grid__fill');
    const label = item.querySelector('.gallery-grid__label');
    const product = item.querySelector('.gallery-grid__product');

    if (fill && lbImg) {
      lbImg.style.background = getComputedStyle(fill).background;
      lbImg.style.backgroundImage = getComputedStyle(fill).backgroundImage;
    }
    if (lbInfo) {
      lbInfo.textContent = (label ? label.textContent : '') + (product ? '  \xb7  ' + product.textContent : '');
    }
    lightbox.classList.add('open');
    lightbox.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
  }

  function closeLightbox() {
    lightbox.classList.remove('open');
    lightbox.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
  }

  galleryItems.forEach((item) => {
    item.addEventListener('click', () => {
      const visible = getVisible();
      const visIdx = visible.indexOf(item);
      openLightbox(visIdx);
    });
    item.setAttribute('tabindex', '0');
    item.setAttribute('role', 'button');
    item.addEventListener('keydown', e => {
      if (e.key === 'Enter' || e.key === ' ') {
        const visible = getVisible();
        openLightbox(visible.indexOf(item));
      }
    });
  });

  if (lbClose) lbClose.addEventListener('click', closeLightbox);
  if (lbBackdrop) lbBackdrop.addEventListener('click', closeLightbox);
  if (lbPrev) lbPrev.addEventListener('click', () => openLightbox(currentIdx - 1));
  if (lbNext) lbNext.addEventListener('click', () => openLightbox(currentIdx + 1));

  document.addEventListener('keydown', e => {
    if (!lightbox.classList.contains('open')) return;
    if (e.key === 'Escape') closeLightbox();
    if (e.key === 'ArrowLeft') openLightbox(currentIdx - 1);
    if (e.key === 'ArrowRight') openLightbox(currentIdx + 1);
  });

  let lbTouchX = 0;
  lightbox.addEventListener('touchstart', e => { lbTouchX = e.touches[0].clientX; }, { passive: true });
  lightbox.addEventListener('touchend', e => {
    const diff = lbTouchX - e.changedTouches[0].clientX;
    if (Math.abs(diff) > 40) openLightbox(diff > 0 ? currentIdx + 1 : currentIdx - 1);
  }, { passive: true });
})();


/* ---- FAQ ACCORDION ---- */
(function initFAQ() {
  const items = document.querySelectorAll('.faq__item, .faq-item');
  if (!items.length) return;

  items.forEach(item => {
    const btn = item.querySelector('.faq__q, .faq-item__q');
    if (!btn) return;
    btn.addEventListener('click', () => {
      const isOpen = item.classList.contains('open');
      items.forEach(i => {
        i.classList.remove('open');
        const b = i.querySelector('.faq__q, .faq-item__q');
        if (b) b.setAttribute('aria-expanded', 'false');
      });
      if (!isOpen) {
        item.classList.add('open');
        btn.setAttribute('aria-expanded', 'true');
      }
    });
  });
})();


/* ---- SMOOTH SCROLL ---- */
(function initSmoothScroll() {
  document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', (e) => {
      const target = document.querySelector(anchor.getAttribute('href'));
      if (!target) return;
      e.preventDefault();
      const navH = parseInt(getComputedStyle(document.documentElement).getPropertyValue('--nav-h')) || 72;
      const top = target.getBoundingClientRect().top + window.scrollY - navH;
      window.scrollTo({ top, behavior: 'smooth' });
    });
  });
})();


/* ---- PARALLAX: Hero slat overlay subtle shift ---- */
(function initParallax() {
  const overlay = document.querySelector('.hero__slats-overlay');
  if (!overlay) return;
  window.addEventListener('scroll', () => {
    const y = window.scrollY;
    overlay.style.transform = `translateY(${y * 0.15}px)`;
  }, { passive: true });
})();


/* ---- MARQUEE: Seamless infinite loop ---- */
(function initMarquee() {
  document.querySelectorAll('.marquee').forEach(marquee => {
    const track = marquee.querySelector('.marquee__track');
    if (!track) return;

    track.style.animation = 'none';

    const originalChildren = Array.from(track.children);
    if (!originalChildren.length) return;

    const minWidth = window.innerWidth * 2;

    function getTotalWidth() {
      return track.scrollWidth;
    }

    let safetyLimit = 20;
    while (getTotalWidth() < minWidth && safetyLimit-- > 0) {
      originalChildren.forEach(child => {
        track.appendChild(child.cloneNode(true));
      });
    }

    const oneSetWidth = originalChildren.reduce((sum, el) => {
      return sum + el.getBoundingClientRect().width;
    }, 0);

    const uid = 'mq' + Math.random().toString(36).slice(2, 7);
    const style = document.createElement('style');
    style.textContent = `
      @keyframes ${uid} {
        from { transform: translateX(0); }
        to   { transform: translateX(-${oneSetWidth}px); }
      }
    `;
    document.head.appendChild(style);

    const basePx = 120;
    const duration = Math.round(oneSetWidth / basePx);
    track.style.animation = `${uid} ${duration}s linear infinite`;
  });
})();

/* ---- GALLERY LIGHTBOX: Real image support ---- */
(function initPhotoLightbox() {
  if (!document.getElementById('galleryGrid')) return;

  const lightbox   = document.getElementById('lightbox');
  const lbBackdrop = document.getElementById('lightboxBackdrop');
  const lbImg      = document.getElementById('lightboxImg');
  const lbInfo     = document.getElementById('lightboxInfo');
  const lbCounter  = document.getElementById('lightboxCounter');
  const lbClose    = document.getElementById('lightboxClose');
  const lbPrev     = document.getElementById('lightboxPrev');
  const lbNext     = document.getElementById('lightboxNext');
  if (!lightbox) return;

  let currentIdx = 0;

  function getVisible() {
    return [...document.querySelectorAll('.gallery-grid__item')]
      .filter(i => !i.classList.contains('hidden'));
  }

  function openLightbox(idx) {
    const visible = getVisible();
    if (!visible.length) return;
    currentIdx = ((idx % visible.length) + visible.length) % visible.length;
    const item = visible[currentIdx];

    const photo   = item.querySelector('.gallery-grid__photo');
    const tag     = item.querySelector('.gallery-grid__tag');
    const label   = item.querySelector('.gallery-grid__label');
    const product = item.querySelector('.gallery-grid__product');

    lbImg.innerHTML = '';
    if (photo) {
      const img = document.createElement('img');
      img.src = photo.src;
      img.alt = photo.alt || '';
      lbImg.appendChild(img);
    }

    if (lbInfo) {
      lbInfo.textContent =
        (tag     ? tag.textContent + '  \xb7  ' : '') +
        (label   ? label.textContent             : '') +
        (product ? '  \u2014  ' + product.textContent : '');
    }

    if (lbCounter) {
      lbCounter.textContent = (currentIdx + 1) + ' / ' + visible.length;
    }

    lightbox.classList.add('open');
    lightbox.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
  }

  function closeLightbox() {
    lightbox.classList.remove('open');
    lightbox.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
  }

  document.querySelectorAll('.gallery-grid__item').forEach(item => {
    item.addEventListener('click', () => {
      openLightbox(getVisible().indexOf(item));
    });
  });

  if (lbClose)    lbClose.addEventListener('click', closeLightbox);
  if (lbBackdrop) lbBackdrop.addEventListener('click', closeLightbox);
  if (lbPrev)     lbPrev.addEventListener('click', () => openLightbox(currentIdx - 1));
  if (lbNext)     lbNext.addEventListener('click', () => openLightbox(currentIdx + 1));

  document.addEventListener('keydown', e => {
    if (!lightbox.classList.contains('open')) return;
    if (e.key === 'Escape')     closeLightbox();
    if (e.key === 'ArrowLeft')  openLightbox(currentIdx - 1);
    if (e.key === 'ArrowRight') openLightbox(currentIdx + 1);
  });

  let lbTouchX = 0;
  lightbox.addEventListener('touchstart', e => { lbTouchX = e.touches[0].clientX; }, { passive: true });
  lightbox.addEventListener('touchend', e => {
    const diff = lbTouchX - e.changedTouches[0].clientX;
    if (Math.abs(diff) > 40) openLightbox(diff > 0 ? currentIdx + 1 : currentIdx - 1);
  }, { passive: true });
})();

/* ---- VIDEO SHOWCASE PLAYER ---- */
(function initVideoShowcase() {
  const video    = document.getElementById('vsVideo');
  const overlay  = document.getElementById('vsOverlay');
  const playBtn  = document.getElementById('vsPlayBtn');
  const pauseBtn = document.getElementById('vsPauseBtn');
  const durEl    = document.getElementById('vsDur');

  if (!video) return;

  video.addEventListener('loadedmetadata', function () {
    const t = Math.round(video.duration);
    const m = Math.floor(t / 60);
    const s = String(t % 60).padStart(2, '0');
    if (durEl) durEl.textContent = m + ':' + s;
  });

  function play() {
    video.play();
    overlay.style.opacity       = '0';
    overlay.style.pointerEvents = 'none';
    if (pauseBtn) pauseBtn.style.display = 'flex';
  }

  function pause() {
    video.pause();
    overlay.style.opacity       = '1';
    overlay.style.pointerEvents = 'auto';
    if (pauseBtn) pauseBtn.style.display = 'none';
  }

  if (playBtn)  playBtn.addEventListener('click',  play);
  if (pauseBtn) pauseBtn.addEventListener('click', pause);

  video.addEventListener('click', function () {
    video.paused ? play() : pause();
  });

  video.addEventListener('ended', function () {
    video.currentTime = 0;
    pause();
  });
})();
// ============================================================
//  SANTI BLINDS — ADMIN PANEL (aligned with the ERD)
//  Appended below the public-site script. Everything here is
//  guarded behind element checks, so it's harmless on the
//  public pages — it does nothing when the admin elements
//  aren't on the page.
//  Talks to admin/api/*.php using the PHP session cookie.
//  Pages: dashboard, orders, inventory, sales, customers (+ login).
// ============================================================

(function () {
  const ADMIN_API = 'api';

  document.querySelectorAll('[data-password-toggle]').forEach((button) => {
    const input = document.getElementById(button.dataset.passwordToggle);
    const form = button.closest('form');
    if (form && !form.dataset.passwordResetBound) {
      form.dataset.passwordResetBound = 'true';
      form.addEventListener('reset', () => {
        form.querySelectorAll('[data-password-toggle]').forEach(toggle => {
          const field = document.getElementById(toggle.dataset.passwordToggle);
          if (!field) return;
          field.type = 'password';
          toggle.setAttribute('aria-label', 'Show password');
          toggle.title = 'Show password';
          toggle.setAttribute('aria-pressed', 'false');
        });
      });
    }
    button.addEventListener('click', () => {
      if (!input) return;
      const visible = input.type === 'password';
      input.type = visible ? 'text' : 'password';
      button.setAttribute('aria-label', visible ? 'Hide password' : 'Show password');
      button.title = visible ? 'Hide password' : 'Show password';
      button.setAttribute('aria-pressed', String(visible));
    });
  });

  // Escape anything that comes from the database before putting it in innerHTML.
  function esc(v) {
    return String(v ?? '').replace(/[&<>"']/g, ch => (
      { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch]
    ));
  }

  // MySQL gives "2026-10-02 14:30:00"; Safari needs the "T". Treat as local time.
  function parseDate(v) {
    return new Date(String(v || '').replace(' ', 'T'));
  }
  function fmtDate(v) {
    const d = parseDate(v);
    return isNaN(d) ? '—' : d.toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' });
  }

  // Read the response as text first so a PHP error page gives a useful message
  // instead of "Unexpected token <" from res.json().
  async function parseJson(res) {
    const raw = await res.text();
    try {
      return JSON.parse(raw);
    } catch (_) {
      console.error('Non-JSON response from', res.url, '\n', raw.slice(0, 500));
      throw new Error('Server returned an unexpected response (check Apache error.log).');
    }
  }

  async function adminApiGet(endpoint, params = {}) {
    const qs = new URLSearchParams(params).toString();
    const res = await fetch(`${ADMIN_API}/${endpoint}${qs ? '?' + qs : ''}`, { credentials: 'include' });
    if (res.status === 401) { window.location.href = 'index.html'; return null; }
    return parseJson(res);
  }

  async function adminApiPost(endpoint, body = {}) {
    const res = await fetch(`${ADMIN_API}/${endpoint}`, {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body),
    });
    // login.php answers 401 for a wrong password; let the login form show that message.
    if (res.status === 401 && endpoint !== 'login.php') { window.location.href = 'index.html'; return null; }
    return parseJson(res);
  }

  function money(n) {
    return '₱' + Number(n || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  function statusBadgeClass(status) {
    return {
      'Pending': 'admin-badge--pending',
      'Processing': 'admin-badge--processing',
      'Completed': 'admin-badge--completed',
      'Cancelled': 'admin-badge--cancelled',
    }[status] || '';
  }
  const badge = s => `<span class="admin-badge ${statusBadgeClass(s)}">${esc(s)}</span>`;

  function statCard(label, value, extraClass = '') {
    return `<div class="admin-stat-card${extraClass ? ' ' + extraClass : ''}"><span class="admin-stat-card__label">${label}</span><span class="admin-stat-card__value">${value}</span></div>`;
  }
  function emptyRow(cols, msg) {
    return `<tr><td colspan="${cols}" class="admin-empty">${esc(msg || 'No data yet.')}</td></tr>`;
  }
  function renderAdminPagination(container, pagination, onChange) {
    if (!container || !pagination) return;
    const { page, pages, per_page: perPage, total } = pagination;
    const numbers = new Set([1, pages]);
    for (let number = Math.max(1, page - 2); number <= Math.min(pages, page + 2); number++) numbers.add(number);
    const pageButtons = [];
    let previous = 0;
    [...numbers].sort((a, b) => a - b).forEach(number => {
      if (previous && number - previous > 1) pageButtons.push('<span class="admin-pagination__ellipsis">…</span>');
      pageButtons.push(`<button type="button" class="admin-pagination__page${number === page ? ' is-active' : ''}" data-page="${number}"${number === page ? ' aria-current="page"' : ''}>${number}</button>`);
      previous = number;
    });
    container.innerHTML = `
      <span class="admin-pagination__total">${Number(total).toLocaleString()} total</span>
      <div class="admin-pagination__controls">
        <button type="button" class="admin-pagination__page" data-page="${page - 1}"${page <= 1 ? ' disabled' : ''}>‹ Prev</button>
        ${pageButtons.join('')}
        <select class="admin-pagination__size" aria-label="Rows per page">
          ${[25, 50, 100, 200].map(size => `<option value="${size}"${size === perPage ? ' selected' : ''}>${size}</option>`).join('')}
        </select>
        <button type="button" class="admin-pagination__page" data-page="${page + 1}"${page >= pages ? ' disabled' : ''}>Next ›</button>
      </div>`;
    container.onclick = event => {
      const button = event.target.closest('[data-page]');
      if (button && !button.disabled) onChange(Number(button.dataset.page), perPage);
    };
    container.onchange = event => {
      if (event.target.matches('.admin-pagination__size')) onChange(1, Number(event.target.value));
    };
  }
  async function saveQuotationStatus(select, quotationId, endpoint, reload) {
    select.disabled = true;
    try {
      const result = await adminApiPost(endpoint, {
        quotation_id: Number(quotationId),
        status: select.value,
      });
      if (!result || !result.success) {
        alert((result && result.message) || 'Could not update quotation status.');
      }
    } catch (error) {
      alert(error.message || 'Could not update quotation status.');
    }
    await reload();
  }
  function debounce(fn, ms) {
    let t;
    return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), ms); };
  }

  // ---------- Runs on every admin page except the login page ----------
  const sidebar = document.querySelector('.admin-sidebar');
  if (sidebar) {
    (async () => {
      const session = await adminApiGet('session.php');
      if (!session) return; // redirected to admin/index.html
      const nameEl = document.getElementById('adminName');
      if (nameEl) nameEl.textContent = session.name;
    })();

    (async () => {
      try {
        const data = await adminApiGet('counts.php');
        if (!data || !data.success) return;
        const countByPage = {
          'admin-orders.html': data.counts.orders,
          'admin-quotations.html': data.counts.quotations,
          'admin-customizations.html': data.counts.customizations,
          'admin-reports.html': data.counts.orders,
          'admin-customers.html': data.counts.customers,
          'admin-audit.html': data.counts.audit,
        };
        sidebar.querySelectorAll('.admin-nav__link').forEach(link => {
          const key = link.getAttribute('href');
          if (!(key in countByPage)) return;
          const count = document.createElement('span');
          count.className = 'admin-nav-count';
          count.textContent = Number(countByPage[key]).toLocaleString();
          link.append(count);
        });
      } catch (error) {
        console.error('Could not load admin section totals:', error);
      }
    })();

    const logoutBtn = document.getElementById('logoutBtn');
    if (logoutBtn) {
      logoutBtn.addEventListener('click', async () => {
        try {
          await adminApiPost('logout.php');
        } catch (err) {
          console.error('Admin logout request failed:', err);
        } finally {
          window.location.href = 'index.php';
        }
      });
    }

    // Close buttons, click on the backdrop, and Escape.
    document.querySelectorAll('[data-close]').forEach((btn) => {
      btn.addEventListener('click', () => btn.closest('.admin-modal').hidden = true);
    });
    document.querySelectorAll('.admin-modal').forEach((m) => {
      m.addEventListener('click', (e) => { if (e.target === m) m.hidden = true; });
    });
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') document.querySelectorAll('.admin-modal:not([hidden])').forEach(m => m.hidden = true);
    });
  }

  // ---------- admin/index.html (owner/manager signs in with email) ----------
  const loginForm = document.getElementById('loginForm');
  if (loginForm) {
    loginForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const errEl = document.getElementById('loginError');
      errEl.hidden = true;

      const email = document.getElementById('email').value.trim();
      const password = document.getElementById('password').value;

      try {
        const data = await adminApiPost('login.php', { email, password });
        if (data && data.success) {
          window.location.href = 'admin-dashboard.html';
        } else {
          errEl.textContent = (data && data.message) || 'Login failed.';
          errEl.hidden = false;
        }
      } catch (err) {
        errEl.textContent = (err && err.message && !/Failed to fetch/i.test(err.message)) ? err.message : 'Could not reach the server. Is Apache running?';
        errEl.hidden = false;
      }
    });
  }

  // ---------- admin-dashboard.html ----------
  const dashboardStats = document.getElementById('dashboardStats');
  if (dashboardStats) {
    dashboardStats.classList.add('admin-stats--dashboard');
    (async () => {
      const data = await adminApiGet('dashboard.php');
      if (!data || !data.success) return;

      const s = data.stats;
      let topSellerData = null;
      try {
        topSellerData = await adminApiGet('best_sellers.php');
      } catch (error) {
        console.error('Could not load dashboard best seller:', error);
      }
      const topSeller = topSellerData && topSellerData.success && topSellerData.products && topSellerData.products[0];
      const topSellerLabel = topSeller
        ? `${esc(topSeller.product)} · ${Number(topSeller.units)} sold`
        : topSellerData && topSellerData.success ? 'No sales yet' : 'Unavailable';

      dashboardStats.innerHTML = `
        <div class="admin-dashboard-highlights">
          ${statCard('Revenue (All Time)', money(s.revenue_total), 'admin-dashboard-hero')}
          <div class="admin-dashboard-side">
            ${statCard('Revenue (This Month)', money(s.revenue_this_month))}
            ${statCard('Top Seller', topSellerLabel)}
          </div>
        </div>
        <div class="admin-dashboard-secondary">
          ${statCard('Total Orders', s.total_orders)}
          ${statCard('Pending Orders', s.pending_orders)}
          ${statCard('Total Customers', s.total_customers)}
          ${statCard('Low-Stock Products', s.low_stock_items)}
        </div>
      `;

      const recent = document.querySelector('#recentOrdersTable tbody');
      if (recent) {
        recent.innerHTML = data.recent_orders.length ? data.recent_orders.map(o => `
          <tr><td>#${o.order_id}</td><td>${esc(o.full_name)}</td><td>${esc(o.products)}</td>
          <td>${badge(o.status)}</td><td>${money(o.amount)}</td></tr>
        `).join('') : emptyRow(5, 'No orders yet.');
      }

      const breakdown = document.getElementById('statusBreakdown');
      if (breakdown) {
        breakdown.innerHTML = data.status_breakdown.map(r => `
          <li>${badge(r.status)} <strong>${r.count}</strong></li>
        `).join('') || '<li class="admin-empty">No orders yet.</li>';
      }
    })();
  }

  // ---------- admin-orders.html ----------
  const ordersTable = document.getElementById('ordersTable');
  if (ordersTable) {
    const tbody = ordersTable.querySelector('tbody');
    const modal = document.getElementById('orderModal');
    const statusSel = document.getElementById('omStatus');
    const payWrap = document.getElementById('omPayWrap');
    const msgEl = document.getElementById('omMsg');
    let currentId = null;
    let ordersPage = 1;
    let ordersPerPage = 25;

    async function loadOrders(page = ordersPage, perPage = ordersPerPage) {
      ordersPage = page;
      ordersPerPage = perPage;
      const search = document.getElementById('orderSearch').value.trim();
      const status = document.getElementById('orderStatusFilter').value;
      const archived = document.getElementById('orderArchiveFilter').value;
      const from = document.getElementById('orderFrom').value;
      const to = document.getElementById('orderTo').value;
      const payment_method = document.getElementById('orderPaymentFilter').value;
      const data = await adminApiGet('orders.php', { search, status, archived, from, to, payment_method, page, per_page: perPage });
      if (!data) return;
      if (!data.success) { tbody.innerHTML = emptyRow(8, data.message); return; }
      ordersPage = data.pagination.page;
      tbody.innerHTML = data.orders.length ? data.orders.map(o => `
        <tr>
          <td>#${o.order_id}</td>
          <td>${esc(o.full_name)}${o.quotation_ref ? `<br><small>from ${esc(o.quotation_ref)}</small>` : ''}</td>
          <td>${esc(o.products)}</td>
          <td>${o.quantity}</td><td>${money(o.amount)}</td>
          <td><select class="admin-inline-status" data-inline-order-status="${o.order_id}" data-payment-method="${esc(o.payment_method || 'Cash')}" aria-label="Change order ${o.order_id} status">
            ${['Pending', 'Processing', 'Completed', 'Cancelled'].map(status => `<option${status === o.status ? ' selected' : ''}>${status}</option>`).join('')}
          </select></td>
          <td>${fmtDate(o.order_date)}</td>
          <td><div class="admin-order-actions">
            <button type="button" class="btn btn--ghost btn--sm" data-view="${o.order_id}">View</button>
            <button type="button" class="btn btn--ghost btn--sm" data-toggle-order-archive="${o.order_id}" data-archived="${Number(o.is_archived) ? '1' : '0'}">
              ${Number(o.is_archived) ? 'Unarchive' : 'Archive'}
            </button>
          </div></td>
        </tr>
      `).join('') : emptyRow(8, 'No orders found.');
      renderAdminPagination(document.getElementById('ordersPagination'), data.pagination, loadOrders);
    }

    // Payment method only matters when the order is being marked Completed (it records the sale).
    const syncPay = () => { payWrap.hidden = statusSel.value !== 'Completed'; };
    statusSel.addEventListener('change', syncPay);

    tbody.addEventListener('click', async (e) => {
      const archiveButton = e.target.closest('[data-toggle-order-archive]');
      if (archiveButton) {
        const archived = archiveButton.dataset.archived !== '1';
        archiveButton.disabled = true;
        try {
          const result = await adminApiPost('order_archive.php', {
            id: Number(archiveButton.dataset.toggleOrderArchive),
            archived,
          });
          if (!result || !result.success) {
            alert((result && result.message) || 'Could not update archive status.');
          }
          await loadOrders(ordersPage, ordersPerPage);
        } catch (error) {
          alert(error.message || 'Could not update archive status.');
          await loadOrders(ordersPage, ordersPerPage);
        }
        return;
      }

      const btn = e.target.closest('[data-view]');
      if (!btn) return;
      const data = await adminApiGet('orders.php', { id: btn.dataset.view });
      if (!data) return;
      if (!data.success) { alert(data.message); return; }

      const o = data.order;
      currentId = o.order_id;
      document.getElementById('omId').textContent = o.order_id;
      document.getElementById('omCustomer').textContent = o.full_name;
      document.getElementById('omPhone').textContent = o.phone || '—';
      document.getElementById('omEmail').textContent = o.email || '—';
      document.getElementById('omAddress').textContent = o.address || '—';
      document.getElementById('omDate').textContent = fmtDate(o.order_date);
      document.getElementById('omTotal').textContent = money(o.amount);
      document.getElementById('omSource').textContent = o.quotation_ref
        ? `From quotation ${o.quotation_ref}` : 'Direct order (from design)';

      // Customer specification: one row per item
      document.querySelector('#omItems tbody').innerHTML = data.items.map(i => `
        <tr>
          <td>${esc(i.product)}<br><small>${esc(i.blind_type)}</small></td>
          <td>${esc(i.material)}</td>
          <td><span style="display:inline-block;width:.8rem;height:.8rem;border:1px solid #888;border-radius:50%;margin-right:.35rem;vertical-align:middle;background:${esc(i.hex_code)}"></span>${esc(i.color)}</td>
          <td>${Number(i.width_cm)} × ${Number(i.height_cm)}</td>
          <td>${i.quantity}</td><td>${money(i.unit_price)}</td><td>${money(i.total_amount)}</td>
        </tr>
      `).join('');

      statusSel.value = o.status;
      document.getElementById('omPayment').value = data.sale ? data.sale.payment_method : 'Cash';
      msgEl.textContent = '';
      syncPay();
      modal.hidden = false;
    });

    tbody.addEventListener('change', async (e) => {
      const select = e.target.closest('[data-inline-order-status]');
      if (!select) return;
      select.disabled = true;
      try {
        const result = await adminApiPost('orders.php', {
          id: Number(select.dataset.inlineOrderStatus),
          status: select.value,
          payment_method: select.dataset.paymentMethod || 'Cash',
        });
        if (result && result.success) await loadOrders(ordersPage, ordersPerPage);
        else {
          alert((result && result.message) || 'Could not update order status.');
          await loadOrders(ordersPage, ordersPerPage);
        }
      } catch (error) {
        alert(error.message || 'Could not update order status.');
        await loadOrders(ordersPage, ordersPerPage);
      }
    });

    document.getElementById('omSaveBtn').addEventListener('click', async () => {
      msgEl.textContent = 'Saving…';
      try {
        const res = await adminApiPost('orders.php', {
          id: currentId,
          status: statusSel.value,
          payment_method: document.getElementById('omPayment').value,
        });
        if (res && res.success) { modal.hidden = true; loadOrders(ordersPage, ordersPerPage); }
        else msgEl.textContent = (res && res.message) || 'Could not save.';
      } catch (err) { msgEl.textContent = err.message; }
    });

    const resetOrderPage = () => loadOrders(1, ordersPerPage);
    document.getElementById('orderSearch').addEventListener('input', debounce(resetOrderPage, 250));
    document.getElementById('orderStatusFilter').addEventListener('change', resetOrderPage);
    document.getElementById('orderArchiveFilter').addEventListener('change', resetOrderPage);
    document.getElementById('orderPaymentFilter').addEventListener('change', resetOrderPage);
    document.getElementById('orderFrom').addEventListener('change', resetOrderPage);
    document.getElementById('orderTo').addEventListener('change', resetOrderPage);
    loadOrders();
  }

  // ---------- admin-customizations.html (quotation items) ----------
  const customizationsTable = document.getElementById('customizationsTable');
  if (customizationsTable) {
    const tbody = customizationsTable.querySelector('tbody');
    const modal = document.getElementById('czModal');
    const typeFilter = document.getElementById('czTypeFilter');
    let customizationsPage = 1;
    let customizationsPerPage = 25;

    async function loadCustomizations(page = customizationsPage, perPage = customizationsPerPage) {
      customizationsPage = page;
      customizationsPerPage = perPage;
      try {
        const data = await adminApiGet('customizations.php', {
          search: document.getElementById('czSearch').value.trim(),
          status: document.getElementById('czStatusFilter').value,
          blind_type: typeFilter.value,
          from: document.getElementById('czFrom').value,
          to: document.getElementById('czTo').value,
          page,
          per_page: perPage,
        });
        if (!data) return;
        if (!data.success) {
          tbody.innerHTML = emptyRow(9, data.message || 'Could not load customizations.');
          return;
        }
        customizationsPage = data.pagination.page;
        const selectedType = typeFilter.value;
        typeFilter.innerHTML = '<option value="">All blind types</option>' + data.types.map(type =>
          `<option value="${esc(type)}"${type === selectedType ? ' selected' : ''}>${esc(type)}</option>`
        ).join('');
        tbody.innerHTML = data.customizations.length ? data.customizations.map(c => `
          <tr>
            <td>${esc(`QT-${String(c.quotation_id).padStart(5, '0')}`)}</td>
            <td>${esc(c.full_name)}</td><td>${esc(c.blind_type)}<br><small>${esc(c.product)}</small></td>
            <td>${Number(c.width_cm)} × ${Number(c.height_cm)}</td><td>${Number(c.quantity)}</td>
            <td>${money(c.total_amount)}</td>
            <td><select class="admin-inline-status" data-customization-status="${c.quotation_id}" aria-label="Change quotation ${c.quotation_id} customization status">
              ${['Active', 'Ordered', 'Expired'].map(status => `<option${status === c.status ? ' selected' : ''}>${status}</option>`).join('')}
            </select></td><td>${fmtDate(c.created_at)}</td>
            <td><button class="btn btn--ghost" data-customization="${c.quotation_item_id}">View</button></td>
          </tr>
        `).join('') : emptyRow(9, 'No customizations found.');
        renderAdminPagination(document.getElementById('customizationsPagination'), data.pagination, loadCustomizations);
      } catch (error) {
        tbody.innerHTML = emptyRow(9, error.message || 'Could not load customizations.');
      }
    }

    tbody.addEventListener('click', async (event) => {
      const button = event.target.closest('[data-customization]');
      if (!button) return;
      try {
        const data = await adminApiGet('customizations.php', { id: button.dataset.customization });
        if (!data) return;
        if (!data.success) { alert(data.message || 'Could not load customization.'); return; }
        const c = data.customization;
        document.getElementById('czId').textContent = `${c.quotation_id} / ${c.quotation_item_id}`;
        document.getElementById('czCustomer').textContent = c.full_name;
        document.getElementById('czContact').textContent = c.email || '—';
        document.getElementById('czType').textContent = `${c.blind_type} — ${c.product}`;
        document.getElementById('czSize').textContent = `${Number(c.width_cm)} × ${Number(c.height_cm)} cm`;
        document.getElementById('czQty').textContent = c.quantity;
        document.getElementById('czMaterial').textContent = c.material;
        document.getElementById('czColor').textContent = c.color;
        document.getElementById('czTotal').textContent = money(c.total_amount);
        document.getElementById('czStatus').textContent = c.status;
        document.getElementById('czDate').textContent = fmtDate(c.created_at);
        modal.hidden = false;
      } catch (error) {
        alert(error.message || 'Could not load customization.');
      }
    });

    tbody.addEventListener('change', (event) => {
      const select = event.target.closest('[data-customization-status]');
      if (select) saveQuotationStatus(select, select.dataset.customizationStatus, 'customizations.php', loadCustomizations);
    });

    const resetCustomizationPage = () => loadCustomizations(1, customizationsPerPage);
    document.getElementById('czSearch').addEventListener('input', debounce(resetCustomizationPage, 250));
    document.getElementById('czStatusFilter').addEventListener('change', resetCustomizationPage);
    typeFilter.addEventListener('change', resetCustomizationPage);
    document.getElementById('czFrom').addEventListener('change', resetCustomizationPage);
    document.getElementById('czTo').addEventListener('change', resetCustomizationPage);
    loadCustomizations();
  }

  // ---------- admin-inventory.html (product.stock_qty) ----------
  const inventoryTable = document.getElementById('inventoryTable');
  if (inventoryTable) {
    const tbody = inventoryTable.querySelector('tbody');
    const searchInput = document.getElementById('inventorySearch');
    let inventoryRows = [];

    async function loadInventory() {
      const data = await adminApiGet('inventory.php');
      if (!data) return;
      if (!data.success) { tbody.innerHTML = emptyRow(6, data.message); return; }
      const note = document.getElementById('lowStockNote');
      if (note) note.textContent = `Products with ${data.low_stock_level} or fewer in stock are flagged as low.`;
      inventoryRows = data.inventory;
      renderInventory();
    }

    function renderInventory() {
      const search = searchInput.value.trim().toLocaleLowerCase();
      const rows = inventoryRows.filter(p =>
        [p.name, p.category, p.blind_type, p.sku].some(value =>
          String(value || '').toLocaleLowerCase().includes(search)
        )
      );
      tbody.innerHTML = rows.length ? rows.map(p => {
        const stock = Number(p.stock_qty);
        const low = Number(p.low_stock) === 1;
        const stockLabel = stock === 0 ? 'Out of stock' : `${stock}${low ? ' (low)' : ''}`;
        return `
          <tr>
            <td>${esc(p.name)}</td><td>${esc(p.category)}</td><td>${esc(p.blind_type)}</td>
            <td>${money(p.base_price)}</td>
            <td><span class="admin-badge ${low ? 'admin-badge--low' : 'admin-badge--ok'}">${stockLabel}</span></td>
            <td>
              <input type="number" min="1" step="1" value="1" data-qty="${p.product_id}" aria-label="Quantity to add for ${esc(p.name)}" style="width:5.5rem" />
              <button class="btn btn--ghost" data-add-stock="${p.product_id}">Add Quantity</button>
            </td>
          </tr>`;
      }).join('') : emptyRow(6, search ? 'No matching products.' : 'No products.');
    }

    searchInput.addEventListener('input', renderInventory);
    tbody.addEventListener('click', async (e) => {
      const btn = e.target.closest('[data-add-stock]');
      if (!btn) return;
      const input = tbody.querySelector(`[data-qty="${btn.dataset.addStock}"]`);
      const quantity = Number(input.value);
      if (!Number.isSafeInteger(quantity) || quantity < 1) {
        alert('Enter a whole quantity greater than zero.');
        input.focus();
        return;
      }
      btn.disabled = true;
      try {
        const res = await adminApiPost('inventory.php', { id: Number(btn.dataset.addStock), quantity_to_add: quantity });
        if (res && res.success) await loadInventory();
        else alert((res && res.message) || 'Could not add stock.');
      } catch (error) {
        alert(error.message || 'Could not add stock.');
      } finally {
        btn.disabled = false;
      }
    });
    loadInventory();
  }

  // ---------- admin-products.html ----------
  const productForm = document.getElementById('productForm');
  if (productForm) {
    const productBody = document.querySelector('#productsTable tbody');
    const categorySelect = document.getElementById('productCategory');
    const message = document.getElementById('productFormMessage');
    const submitButton = document.getElementById('addProductBtn');

    async function loadProducts() {
      try {
        const data = await adminApiGet('products.php');
        if (!data) return;
        if (!data.success) {
          productBody.innerHTML = emptyRow(5, data.message || 'Could not load products.');
          categorySelect.innerHTML = '<option value="">Could not load categories</option>';
          return;
        }
        categorySelect.innerHTML = '<option value="">Choose a category</option>' + data.categories.map(c =>
          `<option value="${c.category_id}">${esc(c.name)}</option>`
        ).join('');
        productBody.innerHTML = data.products.length ? data.products.map(p => `
          <tr><td>${esc(p.name)}</td><td>${esc(p.category)}</td><td>${esc(p.blind_type)}</td>
          <td>${money(p.base_price)}</td><td>${Number(p.stock_qty) === 0 ? '<span class="admin-badge admin-badge--low">Out of stock</span>' : Number(p.stock_qty)}</td></tr>
        `).join('') : emptyRow(5, 'No products yet.');
      } catch (error) {
        productBody.innerHTML = emptyRow(5, error.message || 'Could not load products.');
        categorySelect.innerHTML = '<option value="">Could not load categories</option>';
      }
    }

    productForm.addEventListener('submit', async (event) => {
      event.preventDefault();
      message.textContent = '';
      submitButton.disabled = true;
      const form = new FormData(productForm);
      const body = Object.fromEntries(form.entries());
      body.category_id = Number(body.category_id);
      body.base_price = Number(body.base_price);
      body.stock_qty = Number(body.stock_qty);

      try {
        const result = await adminApiPost('products.php', body);
        if (result && result.success) {
          productForm.reset();
          document.getElementById('productStock').value = '0';
          message.textContent = 'Product added to the catalog.';
          await loadProducts();
        } else {
          message.textContent = (result && result.message) || 'Could not add the product.';
        }
      } catch (error) {
        message.textContent = error.message || 'Could not add the product.';
      } finally {
        submitButton.disabled = false;
      }
    });

    loadProducts();
  }

  // ---------- Admin themed dialogs (replace browser prompt/confirm) ----------
  function adminDialog({ title = 'Santi Blinds', message = '', input = false, value = '', confirm = false, confirmText = 'Continue' } = {}) {
    return new Promise(resolve => {
      const overlay = document.createElement('div');
      overlay.className = 'sb-dialog-overlay';
      overlay.innerHTML = `
        <section class="sb-dialog" role="dialog" aria-modal="true" aria-labelledby="sb-dialog-title">
          <div class="sb-dialog__eyebrow">SANTI BLINDS</div>
          <h2 id="sb-dialog-title">${title}</h2>
          <p class="sb-dialog__message"></p>
          ${input ? `<label class="sb-dialog__label" for="sb-dialog-input">${message}</label><input id="sb-dialog-input" class="sb-dialog__input" type="text" inputmode="decimal" />` : ''}
          <div class="sb-dialog__actions">
            <button type="button" class="btn btn--ghost sb-dialog__cancel">Cancel</button>
            <button type="button" class="btn btn--primary sb-dialog__accept">${confirm ? confirmText : 'Save & Continue'}</button>
          </div>
        </section>`;
      const messageEl = overlay.querySelector('.sb-dialog__message');
      if (input) messageEl.remove(); else messageEl.textContent = message;
      const field = overlay.querySelector('.sb-dialog__input');
      if (field) field.value = value;
      const close = result => { overlay.remove(); document.removeEventListener('keydown', onKey); resolve(result); };
      const onKey = event => {
        if (event.key === 'Escape') close(input ? null : false);
        if (event.key === 'Enter' && input) { event.preventDefault(); close(field.value); }
      };
      overlay.querySelector('.sb-dialog__cancel').addEventListener('click', () => close(input ? null : false));
      overlay.querySelector('.sb-dialog__accept').addEventListener('click', () => close(input ? field.value : true));
      overlay.addEventListener('click', event => { if (event.target === overlay) close(input ? null : false); });
      document.addEventListener('keydown', onKey);
      document.body.appendChild(overlay);
      if (field) { field.focus(); field.select(); } else overlay.querySelector('.sb-dialog__accept').focus();
    });
  }
  const adminPrompt = (message, value = '', title = 'Edit quotation') => adminDialog({ title, message, input: true, value });
  const adminConfirm = (message, title = 'Confirm action', confirmText = 'Confirm') => adminDialog({ title, message, confirm: true, confirmText });
  const adminAlert = (message, title = 'Notice') => adminDialog({ title, message, confirm: true, confirmText: 'Okay' });

  // ---------- admin-quotations.html ----------
  const quotationsTable = document.getElementById('quotationsTable');
  if (quotationsTable) {
    const tbody = quotationsTable.querySelector('tbody');
    let quotationsPage = 1;
    let quotationsPerPage = 25;

    async function loadQuotations(page = quotationsPage, perPage = quotationsPerPage) {
      quotationsPage = page;
      quotationsPerPage = perPage;
      try {
        const search = document.getElementById('quotationSearch').value.trim();
        const status = document.getElementById('quotationStatusFilter').value;
        const from = document.getElementById('quotationFrom').value;
        const to = document.getElementById('quotationTo').value;
        const data = await adminApiGet('quotations.php', { search, status, from, to, page, per_page: perPage });
        if (!data) return;
        if (!data.success) { tbody.innerHTML = emptyRow(8, data.message || 'Could not load quotations.'); return; }
        quotationsPage = data.pagination.page;
        tbody.innerHTML = data.quotations.length ? data.quotations.map(q => `
          <tr class="admin-quote-row" data-quote="${q.quotation_id}" tabindex="0" aria-expanded="false">
            <td>${esc(q.reference)}</td><td>${esc(q.customer)}<br><small>${esc(q.email)}</small></td>
            <td>${Number(q.item_count)}</td><td>${money(q.total_amount)}</td>
            <td>${fmtDate(q.created_at)}</td><td>${fmtDate(q.valid_until)}</td>
            <td><select class="admin-inline-status" data-quotation-status="${q.quotation_id}" aria-label="Change quotation ${esc(q.reference)} status">
              ${['Pending', 'Returned', 'Active', 'Ordered', 'Expired'].map(status => `<option${status === q.status ? ' selected' : ''}>${status}</option>`).join('')}
            </select></td>
            <td>${q.order_id ? `#${q.order_id} ${q.order_status ? badge(q.order_status) : ''}` : '—'}</td>
          </tr>
          <tr class="admin-quote-detail" data-detail="${q.quotation_id}" hidden>
            <td colspan="8">
              <div class="admin-table-wrap">
                <table class="admin-table">
                  <thead><tr><th>Product</th><th>Material</th><th>Colour</th><th>Size (cm)</th><th>Qty</th><th>Unit Price</th><th>Total</th></tr></thead>
                  <tbody>${q.items.map(item => `
                    <tr data-quotation-item-id="${item.quotation_item_id}"><td>${esc(item.product)}<br><small>${esc(item.blind_type)}</small></td>
                    <td>${esc(item.material)}</td><td>${esc(item.color)}</td>
                    <td>${Number(item.width_cm)} × ${Number(item.height_cm)}</td>
                    <td>${Number(item.quantity)}</td><td>${money(item.unit_price)}</td><td>${money(item.total_amount)}</td></tr>
                  `).join('') || emptyRow(7, 'No quotation items.')}</tbody>
                </table>
                <div style="display:flex;gap:.75rem;align-items:center;flex-wrap:wrap;padding:1rem 0;">
                  <button type="button" class="btn btn--primary" data-return-quote="${q.quotation_id}" ${q.status === 'Ordered' || q.status === 'Expired' ? 'disabled' : ''}>Edit prices &amp; return to customer</button>
                  <small>Customer cannot place an order until this quotation is returned.</small>
                </div>
              </div>
            </td>
          </tr>
        `).join('') : emptyRow(8, 'No quotations found.');
        renderAdminPagination(document.getElementById('quotationsPagination'), data.pagination, loadQuotations);
      } catch (error) {
        tbody.innerHTML = emptyRow(8, error.message || 'Could not load quotations.');
      }
    }

    tbody.addEventListener('click', async (event) => {
      const returnButton = event.target.closest('[data-return-quote]');
      if (returnButton) {
        const quoteId = returnButton.dataset.returnQuote;
        const detail = tbody.querySelector(`[data-detail="${quoteId}"]`);
        const rows = [...detail.querySelectorAll('tbody tr[data-quotation-item-id]')];
        const quoteItems = [];
        for (const row of rows) {
          const cells = row.querySelectorAll('td');
          const quantity = Number(cells[4].textContent.trim());
          const original = Number(String(cells[5].textContent).replace(/[^0-9.]/g, ''));
          const unitPrice = await adminPrompt(`Enter unit price for ${cells[0].innerText.trim()} (quantity ${quantity}):`, original.toFixed(2), 'Set unit price');
          if (unitPrice === null) return;
          if (!Number.isFinite(Number(unitPrice)) || Number(unitPrice) < 0) { await adminAlert('Enter a valid non-negative price.', 'Invalid price'); return; }
          quoteItems.push({ quotation_item_id: Number(row.dataset.quotationItemId), unit_price: Number(unitPrice) });
        }
        const validDays = await adminPrompt('Quotation validity in days (default 14):', '14', 'Quotation validity');
        if (validDays === null) return;
        if (!Number.isInteger(Number(validDays)) || Number(validDays) < 1 || Number(validDays) > 90) { await adminAlert('Validity must be 1–90 days.', 'Invalid validity period'); return; }
        if (!await adminConfirm('Return this edited quotation to the customer? The customer will be able to review the updated price.', 'Return quotation', 'Return to customer')) return;
        returnButton.disabled = true;
        try {
          const result = await adminApiPost('quotations.php', { action: 'return_quote', quotation_id: Number(quoteId), items: quoteItems, valid_days: Number(validDays) });
          if (!result || !result.success) { await adminAlert(result?.message || 'Could not return quotation.', 'Unable to return quotation'); return; }
          await adminAlert('Quotation returned to customer.', 'Quotation returned');
          loadQuotations();
        } catch (error) { await adminAlert(error.message || 'Could not return quotation.', 'Unable to return quotation'); }
        finally { returnButton.disabled = false; }
        return;
      }
      if (event.target.closest('[data-quotation-status]')) return;
      const row = event.target.closest('.admin-quote-row');
      if (!row) return;
      const detail = tbody.querySelector(`[data-detail="${row.dataset.quote}"]`);
      if (detail) {
        detail.hidden = !detail.hidden;
        row.setAttribute('aria-expanded', String(!detail.hidden));
      }
    });
    tbody.addEventListener('keydown', (event) => {
      if ((event.key === 'Enter' || event.key === ' ') && event.target.matches('.admin-quote-row')) {
        event.preventDefault();
        event.target.click();
      }
    });
    tbody.addEventListener('change', (event) => {
      const select = event.target.closest('[data-quotation-status]');
      if (select) saveQuotationStatus(select, select.dataset.quotationStatus, 'quotations.php', loadQuotations);
    });
    const resetQuotationPage = () => loadQuotations(1, quotationsPerPage);
    document.getElementById('quotationSearch').addEventListener('input', debounce(resetQuotationPage, 250));
    document.getElementById('quotationStatusFilter').addEventListener('change', resetQuotationPage);
    document.getElementById('quotationFrom').addEventListener('change', resetQuotationPage);
    document.getElementById('quotationTo').addEventListener('change', resetQuotationPage);
    loadQuotations();
  }

  // ---------- admin-reports.html ----------
  const reportTable = document.getElementById('reportTable');
  if (reportTable) {
    const tbody = reportTable.querySelector('tbody');
    const fromInput = document.getElementById('reportFrom');
    const toInput = document.getElementById('reportTo');
    const reportStatusFilter = document.getElementById('reportStatusFilter');
    const reportSearch = document.getElementById('reportSearch');
    const today = new Date();
    const toIso = date => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
    fromInput.value = toIso(new Date(today.getFullYear(), today.getMonth(), 1));
    toInput.value = toIso(today);
    let reportRows = [];
    let reportsPage = 1;
    let reportsPerPage = 25;

    async function loadBestSellers() {
      const data = await adminApiGet('best_sellers.php');
      if (!data) return;
      const bestSellersBody = document.querySelector('#bestSellersTable tbody');
      const stats = document.getElementById('reportStats');
      if (!data.success) {
        bestSellersBody.innerHTML = emptyRow(4, data.message || 'Could not load best-selling report.');
        if (stats) {
          const existing = stats.querySelector('[data-top-seller]');
          if (existing) existing.remove();
        }
        return;
      }
      const topProduct = data.products[0];
      if (stats) {
        let topSellerCard = stats.querySelector('[data-top-seller]');
        const cardHtml = topProduct
          ? `
            <div class="admin-stat-card" data-top-seller>
              <span class="admin-stat-card__label">Top Seller</span>
              <span class="admin-stat-card__value">${esc(topProduct.product)}</span>
            </div>
          `
          : '<div class="admin-stat-card" data-top-seller><span class="admin-stat-card__label">Top Seller</span><span class="admin-stat-card__value">No sales yet</span></div>';
        if (topSellerCard) {
          topSellerCard.outerHTML = cardHtml;
        } else {
          stats.insertAdjacentHTML('beforeend', cardHtml);
        }
      }
      bestSellersBody.innerHTML = data.products.length
        ? data.products.map((product, index) => `
          <tr><td>${index + 1}</td><td>${esc(product.product)}</td>
          <td>${Number(product.units)}</td><td>${money(product.revenue)}</td></tr>
        `).join('')
        : emptyRow(4, 'No completed sales yet.');
    }

    async function loadReport(page = reportsPage, perPage = reportsPerPage) {
      reportsPage = page;
      reportsPerPage = perPage;
      try {
        const data = await adminApiGet('reports.php', {
          from: fromInput.value,
          to: toInput.value,
          status: reportStatusFilter.value,
          search: reportSearch.value.trim(),
          page,
          per_page: perPage,
        });
        if (!data) return;
        if (!data.success) {
          document.getElementById('reportStats').textContent = data.message || 'Could not load report.';
          tbody.innerHTML = emptyRow(8, data.message || 'Could not load report.');
          return;
        }
        reportsPage = data.pagination.page;
        reportRows = data.orders;
        document.getElementById('reportPrintRange').textContent =
          `Order report: ${fromInput.value || 'All dates'} through ${toInput.value || 'All dates'}`;
        document.getElementById('reportStats').innerHTML = `
          ${statCard('Orders in Range', data.stats.orders)}
          ${statCard('Completed', data.stats.completed_orders)}
          ${statCard('Pending', data.stats.pending_orders)}
          ${statCard('Paid Revenue', money(data.stats.revenue))}
        `;
        tbody.innerHTML = reportRows.length ? reportRows.map(order => `
          <tr><td>#${order.order_id}</td><td>${esc(order.full_name)}</td><td>${esc(order.address || '—')}</td>
          <td>${esc(order.products)}</td><td>${Number(order.quantity)}</td><td>${money(order.amount)}</td>
          <td>${badge(order.status)}</td><td>${fmtDate(order.order_date)}</td></tr>
        `).join('') : emptyRow(8, 'No orders in this date range.');
        renderAdminPagination(document.getElementById('reportsPagination'), data.pagination, loadReport);
      } catch (error) {
        document.getElementById('reportStats').textContent = error.message || 'Could not load report.';
        tbody.innerHTML = emptyRow(8, error.message || 'Could not load report.');
      }
    }

    const resetReportPage = () => loadReport(1, reportsPerPage);
    reportStatusFilter.addEventListener('change', resetReportPage);
    reportSearch.addEventListener('input', debounce(resetReportPage, 250));
    fromInput.addEventListener('change', resetReportPage);
    toInput.addEventListener('change', resetReportPage);
    document.getElementById('printReportsBtn').addEventListener('click', async (event) => {
      const button = event.currentTarget;
      button.disabled = true;
      try {
        const firstPage = await adminApiGet('reports.php', {
          from: fromInput.value,
          to: toInput.value,
          status: reportStatusFilter.value,
          search: reportSearch.value.trim(),
          page: 1,
          per_page: 200,
        });
        if (!firstPage || !firstPage.success) throw new Error(firstPage && firstPage.message || 'Could not load report for printing.');
        let printRows = firstPage.orders;
        for (let page = 2; page <= firstPage.pagination.pages; page++) {
          const nextPage = await adminApiGet('reports.php', {
            from: fromInput.value,
            to: toInput.value,
            status: reportStatusFilter.value,
            search: reportSearch.value.trim(),
            page,
            per_page: 200,
          });
          if (!nextPage || !nextPage.success) throw new Error(nextPage && nextPage.message || 'Could not load report for printing.');
          printRows = printRows.concat(nextPage.orders);
        }
        tbody.innerHTML = printRows.length ? printRows.map(order => `
          <tr><td>#${order.order_id}</td><td>${esc(order.full_name)}</td><td>${esc(order.address || '—')}</td>
          <td>${esc(order.products)}</td><td>${Number(order.quantity)}</td><td>${money(order.amount)}</td>
          <td>${badge(order.status)}</td><td>${fmtDate(order.order_date)}</td></tr>
        `).join('') : emptyRow(8, 'No orders in this date range.');
        document.body.classList.add('printing-reports');
        window.addEventListener('afterprint', () => {
          document.body.classList.remove('printing-reports');
          button.disabled = false;
          loadReport(reportsPage, reportsPerPage);
        }, { once: true });
        window.print();
      } catch (error) {
        alert(error.message || 'Could not print reports.');
        button.disabled = false;
        loadReport(reportsPage, reportsPerPage);
      }
    });
    document.getElementById('exportReportBtn').addEventListener('click', async () => {
      try {
        const exportData = await adminApiGet('reports.php', {
          from: fromInput.value,
          to: toInput.value,
          status: reportStatusFilter.value,
          search: reportSearch.value.trim(),
          page: 1,
          per_page: 200,
        });
        if (!exportData || !exportData.success) {
          alert((exportData && exportData.message) || 'Could not export report.');
          return;
        }
        let exportRows = exportData.orders;
        for (let page = 2; page <= exportData.pagination.pages; page++) {
          const next = await adminApiGet('reports.php', {
            from: fromInput.value,
            to: toInput.value,
            status: reportStatusFilter.value,
            search: reportSearch.value.trim(),
            page,
            per_page: 200,
          });
          if (!next || !next.success) {
            alert((next && next.message) || 'Could not export report.');
            return;
          }
          exportRows = exportRows.concat(next.orders);
        }
      const columns = ['Order ID', 'Customer', 'Location', 'Products', 'Quantity', 'Amount', 'Status', 'Order Date'];
      const cell = value => `"${String(value ?? '').replace(/"/g, '""')}"`;
      const csv = [columns, ...exportRows.map(order => [
        order.order_id, order.full_name, order.address || '', order.products, order.quantity,
        Number(order.amount).toFixed(2), order.status, order.order_date,
      ])].map(row => row.map(cell).join(',')).join('\r\n');
      const url = URL.createObjectURL(new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8' }));
      const link = document.createElement('a');
      link.href = url;
      link.download = `santi-blinds-orders-${fromInput.value}-to-${toInput.value}.csv`;
      link.click();
      URL.revokeObjectURL(url);
      } catch (error) {
        alert(error.message || 'Could not export report.');
      }
    });
    loadReport();
    loadBestSellers();
  }

  // ---------- admin-audit.html ----------
  const auditTable = document.getElementById('auditTable');
  if (auditTable) {
    const tbody = auditTable.querySelector('tbody');
    const actionFilter = document.getElementById('auditActionFilter');
    let auditPage = 1;
    let auditPerPage = 25;

    async function loadAudit(page = auditPage, perPage = auditPerPage) {
      auditPage = page;
      auditPerPage = perPage;
      try {
        const data = await adminApiGet('audit.php', {
          search: document.getElementById('auditSearch').value.trim(),
          from: document.getElementById('auditFrom').value,
          to: document.getElementById('auditTo').value,
          action: actionFilter.value,
          page,
          per_page: perPage,
        });
        if (!data) return;
        auditPage = data.pagination.page;
        const selectedAction = actionFilter.value;
        actionFilter.innerHTML = '<option value="">All actions</option>' + data.actions.map(action =>
          `<option value="${esc(action)}"${action === selectedAction ? ' selected' : ''}>${esc(action)}</option>`
        ).join('');
        tbody.innerHTML = data.success && data.logs.length ? data.logs.map(log => `
          <tr><td>${fmtDate(log.created_at)}<br><small>${esc(log.created_at)}</small></td>
          <td>${esc(log.admin_name)}</td><td>${esc(log.action)}</td><td>${esc(log.details || '')}</td></tr>
        `).join('') : emptyRow(4, data.message || 'No admin actions have been recorded yet.');
        renderAdminPagination(document.getElementById('auditPagination'), data.pagination, loadAudit);
      } catch (error) {
        tbody.innerHTML = emptyRow(4, error.message || 'Could not load the audit log.');
      }
    }

    const resetAuditPage = () => loadAudit(1, auditPerPage);
    document.getElementById('auditSearch').addEventListener('input', debounce(resetAuditPage, 250));
    document.getElementById('auditFrom').addEventListener('change', resetAuditPage);
    document.getElementById('auditTo').addEventListener('change', resetAuditPage);
    actionFilter.addEventListener('change', resetAuditPage);
    loadAudit();
  }

  // ---------- admin-sales.html (sale table) ----------
  const salesStats = document.getElementById('salesStats');
  if (salesStats) {
    (async () => {
      const data = await adminApiGet('sales.php');
      if (!data || !data.success) return;

      salesStats.innerHTML = `
        ${statCard('Total Revenue', money(data.total_revenue))}
        ${statCard('Average Sale', money(data.avg_sale_value))}
        ${statCard('Sales Recorded', data.sales_count)}
      `;

      const byMonth = document.querySelector('#salesByMonthTable tbody');
      if (byMonth) {
        byMonth.innerHTML = data.by_month.length ? data.by_month.map(m => `
          <tr><td>${esc(m.month)}</td><td>${m.orders}</td><td>${money(m.revenue)}</td></tr>
        `).join('') : emptyRow(3, 'No sales yet.');
      }

      const byProduct = document.querySelector('#salesByProductTable tbody');
      if (byProduct) {
        byProduct.innerHTML = data.by_product.length ? data.by_product.map(p => `
          <tr><td>${esc(p.product)}</td><td>${p.units}</td><td>${money(p.revenue)}</td></tr>
        `).join('') : emptyRow(3, 'No completed orders yet.');
      }
    })();
  }

  // ---------- admin-customers.html (customer profile + contact info) ----------
  const customersTable = document.getElementById('customersTable');
  if (customersTable) {
    const tbody = customersTable.querySelector('tbody');
    const modal = document.getElementById('customerModal');
    let customersPage = 1;
    let customersPerPage = 25;

    async function loadCustomers(page = customersPage, perPage = customersPerPage) {
      customersPage = page;
      customersPerPage = perPage;
      const search = document.getElementById('customerSearch').value.trim();
      const from = document.getElementById('customerFrom').value;
      const to = document.getElementById('customerTo').value;
      const orders = document.getElementById('customerOrdersFilter').value;
      const data = await adminApiGet('customers.php', { search, from, to, orders, page, per_page: perPage });
      if (!data) return;
      if (!data.success) { tbody.innerHTML = emptyRow(6, data.message); return; }
      customersPage = data.pagination.page;
      tbody.innerHTML = data.customers.length ? data.customers.map(c => `
        <tr>
          <td>${esc(c.full_name)}</td><td>${esc(c.phone || '—')}</td><td>${esc(c.email || '—')}</td>
          <td>${esc(c.address || '—')}</td><td>${c.order_count}</td>
          <td><button class="btn btn--ghost" data-view="${c.customer_id}">View</button></td>
        </tr>
      `).join('') : emptyRow(6, 'No customers found.');
      renderAdminPagination(document.getElementById('customersPagination'), data.pagination, loadCustomers);
    }

    tbody.addEventListener('click', async (e) => {
      const btn = e.target.closest('[data-view]');
      if (!btn) return;
      const data = await adminApiGet('customers.php', { id: btn.dataset.view });
      if (!data) return;
      if (!data.success) { alert(data.message); return; }

      const c = data.customer;
      document.getElementById('cmName').textContent = c.full_name;
      document.getElementById('cmPhone').textContent = c.phone || '—';
      document.getElementById('cmEmail').textContent = c.email || '—';
      document.getElementById('cmAddress').textContent = c.address || '—';
      document.getElementById('cmSince').textContent = fmtDate(c.created_at);

      document.querySelector('#cmOrdersTable tbody').innerHTML = data.orders.length ? data.orders.map(o => `
        <tr><td>#${o.order_id}</td>
        <td>${esc(o.products)}${o.quotation_ref ? `<br><small>from ${esc(o.quotation_ref)}</small>` : ''}</td>
        <td>${badge(o.status)}</td>
        <td>${money(o.amount)}</td><td>${fmtDate(o.order_date)}</td></tr>
      `).join('') : emptyRow(5, 'No orders yet.');

      document.querySelector('#cmInquiriesTable tbody').innerHTML = data.inquiries.length ? data.inquiries.map(q => `
        <tr><td>${fmtDate(q.created_at)}</td><td>${esc(q.product)}</td><td>${esc(q.message || '')}</td><td>${esc(q.status)}</td></tr>
      `).join('') : emptyRow(4, 'No inquiries.');

      renderQuotations(data.quotations || []);
      modal.hidden = false;
    });

    // ----- Quotations inside the customer profile -----
    const quotesBody = document.querySelector('#cmQuotesTable tbody');
    const QUOTE_BADGE = { Active: 'admin-badge--processing', Ordered: 'admin-badge--completed', Expired: 'admin-badge--cancelled' };

    function renderQuotations(quotes) {
      if (!quotes.length) { quotesBody.innerHTML = emptyRow(6, 'No quotations yet.'); return; }

      quotesBody.innerHTML = quotes.map(q => {
        const status = `<span class="admin-badge ${QUOTE_BADGE[q.status] || ''}">${esc(q.status)}</span>`
          + (q.order_id
              ? ` <small>→ order #${q.order_id}</small> ${badge(q.order_status)}`
              : '');
        return `
          <tr class="cm-quote-row" data-quote="${q.quotation_id}" style="cursor:pointer;">
            <td>${esc(q.reference)}</td><td>${fmtDate(q.created_at)}</td>
            <td>${q.item_count}</td><td>${money(q.total_amount)}</td>
            <td>${fmtDate(q.valid_until)}</td><td>${status}</td>
          </tr>
          <tr class="cm-quote-detail" data-detail="${q.quotation_id}" hidden>
            <td colspan="6">
              <table class="admin-table">
                <thead><tr><th>Product</th><th>Material</th><th>Color</th><th>Size (cm)</th><th>Qty</th><th>Unit Price</th><th>Amount</th></tr></thead>
                <tbody>
                  ${q.items.map(i => `
                    <tr><td>${esc(i.product)}</td><td>${esc(i.material)}</td><td>${esc(i.color)}</td>
                    <td>${i.width_cm} × ${i.height_cm}</td><td>${i.quantity}</td>
                    <td>${money(i.unit_price)}</td><td>${money(i.total_amount)}</td></tr>
                  `).join('')}
                </tbody>
              </table>
            </td>
          </tr>`;
      }).join('');
    }

    quotesBody.addEventListener('click', (e) => {
      const row = e.target.closest('.cm-quote-row');
      if (!row) return;
      const detail = quotesBody.querySelector(`[data-detail="${row.dataset.quote}"]`);
      if (detail) detail.hidden = !detail.hidden;
    });

    const resetCustomerPage = () => loadCustomers(1, customersPerPage);
    document.getElementById('customerSearch').addEventListener('input', debounce(resetCustomerPage, 250));
    document.getElementById('customerFrom').addEventListener('change', resetCustomerPage);
    document.getElementById('customerTo').addEventListener('change', resetCustomerPage);
    document.getElementById('customerOrdersFilter').addEventListener('change', resetCustomerPage);
    loadCustomers();
  }
})();


/* ============================================================
   ORDER PAGE — order.html
   Register > Login > Customize > (optional Quotation) > Place order.
   A quotation is optional: an order can be placed straight from
   the cart (portal/order.php with items) or from a quotation
   (portal/order.php with quotation_id).
   Prices shown while designing are previews only; the server
   recomputes every price before saving, and an order keeps the
   prices it was placed at.
   ============================================================ */
(function initOrderPortal() {
  const app = document.getElementById('orderApp');
  if (!app) return;

  const API = 'portal';
  const $ = (id) => document.getElementById(id);
  const setHidden = (id, hidden) => { const el = $(id); if (el) el.hidden = hidden; };
  const esc = (v) => String(v ?? '').replace(/[&<>"']/g, ch => (
    { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch]));
  const money = (n) => '₱' + Number(n || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  // MySQL gives "2026-10-02 14:30:00"; Safari needs the "T".
  const fmtDate = (v) => {
    const d = new Date(String(v || '').replace(' ', 'T'));
    return isNaN(d) ? '—' : d.toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' });
  };

  const state = { catalog: null, productId: null, colorId: null, cart: [], customer: null, quote: null };
  const PANELS = { auth: 'panelAuth', customize: 'panelCustomize', quote: 'panelQuote', review: 'panelReview', done: 'panelDone' };
  const STEP_INDEX = { auth: 0, customize: 1, quote: 2, review: 3, done: 4 };

  /* ---------- API ---------- */
  async function api(endpoint, body) {
    let res;
    try {
      res = await fetch(`${API}/${endpoint}`, {
        credentials: 'include',
        ...(body !== undefined ? { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) } : {}),
      });
    } catch (_) {
      throw new Error('Could not reach the server. Please check your connection and try again.');
    }
    const raw = await res.text();
    let data;
    try { data = JSON.parse(raw); } catch (_) {
      console.error('Non-JSON response from', res.url, raw.slice(0, 500));
      throw new Error('The server returned an unexpected response. Please try again.');
    }
    // A 401 from anywhere except the login form means the session ended.
    if (res.status === 401 && !endpoint.startsWith('customer_login')) {
      const err = new Error('Your session has ended. Please log in again.');
      err.auth = true;
      throw err;
    }
    return data;
  }

  /* ---------- UI helpers ---------- */
  function showAlert(msg, ok) {
    const el = $('orderAlert');
    const list = Array.isArray(msg) ? msg : [msg];
    el.className = 'order-alert' + (ok ? ' order-alert--ok' : '');
    el.innerHTML = list.length > 1 ? '<ul>' + list.map(m => `<li>${esc(m)}</li>`).join('') + '</ul>' : esc(list[0]);
    el.hidden = false;
  }
  function clearAlert() { $('orderAlert').hidden = true; }
  function resultErrors(res, fallback) { return res.errors || res.message || fallback; }
  function fail(err) {
    if (err && err.auth) return sessionEnded();
    showAlert((err && err.message) || 'Something went wrong.');
  }

  function go(step) {
    Object.entries(PANELS).forEach(([k, id]) => { $(id).hidden = k !== step; });
    const cur = STEP_INDEX[step];
    document.querySelectorAll('#orderSteps li').forEach((li, i) => {
      li.classList.toggle('is-done', i < cur);
      li.classList.toggle('is-current', i === cur);
      li.toggleAttribute('aria-current', i === cur);
    });
    const h = $(PANELS[step]).querySelector('.order-panel__title');
    if (h) h.focus({ preventScroll: true });
    window.scrollTo({ top: Math.max(0, app.offsetTop - 100), behavior: 'smooth' });
    if (step === 'customize') updatePreview();
  }

  /* ---------- Account: log in / register ---------- */
  function showAuth(mode) {
    const reg = mode === 'register';
    $('custLoginForm').hidden = reg;
    $('custRegisterForm').hidden = !reg;
    $('authTitle').textContent = reg ? 'Create your account' : 'Log in';
    $('authLead').textContent = reg
      ? 'One account lets you design blinds, keep your quotations and place orders.'
      : 'Log in to design your blinds and place your order.';
  }

  function sessionEnded() {
    state.customer = null; state.quote = null; announceAuth(null);
    showAuth('login'); go('auth');
    showAlert('Your session has ended. Please log in again.');
  }

  // Keeps the nav account menu in sync with this page's login state.
  const announceAuth = (customer) => window.dispatchEvent(new CustomEvent('santi:auth', { detail: { customer } }));

  async function startSession(customer) {
    state.customer = customer;
    announceAuth(customer);
    if (!state.catalog) return;
    go('customize');
    loadSavedQuotes();
  }

  window.addEventListener('santi:login', event => {
    const customer = event.detail && event.detail.customer;
    if (customer) startSession(customer);
  });
  window.addEventListener('santi:register', () => {
    clearAlert();
    showAuth('register');
    go('auth');
  });

  $('showRegisterBtn').addEventListener('click', () => { clearAlert(); showAuth('register'); });
  $('showLoginBtn').addEventListener('click', () => { clearAlert(); showAuth('login'); });

  $('custRegisterForm').addEventListener('submit', async (e) => {
    e.preventDefault(); clearAlert();
    const f = {
      full_name: $('rName').value.trim(), email: $('rEmail').value.trim(), phone: window.SantiPhone.toInternational($('rPhone').value.trim()),
      address: $('rAddress').value.trim(), password: $('rPassword').value,
    };
    const errs = [];
    if (!f.full_name) errs.push('Please enter your full name.');
    if (!/^\S+@\S+\.\S+$/.test(f.email)) errs.push('Please enter a valid email address.');
    if (!window.SantiPhone.isValid(f.phone)) errs.push('Enter a valid Philippine mobile number, such as +63 917 123 4567.');
    if (!f.address) errs.push('Please enter your installation / delivery address.');
    if (f.password.length < 8) errs.push('Password must be at least 8 characters.');
    else if (f.password !== $('rConfirm').value) errs.push('The two passwords do not match.');
    if (!$('rConsent').checked) errs.push('Please agree to the terms to create an account.');
    if (errs.length) return showAlert(errs);

    const btn = $('registerBtn'); btn.disabled = true;
    try {
      const res = await api('customer_register.php', { ...f, consent: true });
      if (!res.success) return showAlert(resultErrors(res, 'Could not create your account.'));
      $('custRegisterForm').reset();
      showAuth('login');
      $('lEmail').value = f.email;
      $('lPassword').focus();
      showAlert('Account created. Log in to continue.', true);
    } catch (err) { fail(err); } finally { btn.disabled = false; }
  });

  $('custLoginForm').addEventListener('submit', async (e) => {
    e.preventDefault(); clearAlert();
    const email = $('lEmail').value.trim(), password = $('lPassword').value;
    if (!/^\S+@\S+\.\S+$/.test(email) || !password) return showAlert('Please enter your email and password.');

    const btn = $('loginBtn'); btn.disabled = true;
    try {
      const res = await api('customer_login.php', { email, password });
      if (!res.success) return showAlert(resultErrors(res, 'Could not log you in.'));
      $('custLoginForm').reset();
      await startSession(res.customer);
    } catch (err) { fail(err); } finally { btn.disabled = false; }
  });

  /* ---------- Catalog lookups ---------- */
  const productById  = (id) => state.catalog.products.find(p => +p.product_id === +id);
  const materialById = (id) => state.catalog.materials.find(m => +m.material_id === +id);
  const colorById    = (id) => state.catalog.colors.find(c => +c.color_id === +id);

  /* ---------- Design form ---------- */
  function buildForm() {
    const c = state.catalog;
    const box = $('typeTiles');
    box.setAttribute('role', 'radiogroup');
    box.innerHTML = '';
    c.products.forEach(p => {
      const b = document.createElement('button');
      b.type = 'button'; b.className = 'order-type'; b.dataset.product = p.product_id;
      const stock = Number(p.stock_qty);
      b.disabled = stock < 1;
      b.setAttribute('role', 'radio');
      b.innerHTML = `<strong>${esc(p.name)}</strong><small>${esc(p.description || p.blind_type)}</small>
        <small data-stock-label class="${stock < 1 ? 'order-stock--out' : 'order-stock'}">${stock < 1 ? 'Out of stock' : `${stock} in stock`}</small>`;
      b.addEventListener('click', () => selectProduct(p.product_id));
      box.appendChild(b);
    });

    $('cMaterial').innerHTML = c.materials.map(m =>
      `<option value="${m.material_id}">${esc(m.name)}${+m.price_modifier > 0 ? ` (+${money(m.price_modifier)})` : ''}</option>`).join('');

    const sw = $('swatches');
    sw.setAttribute('role', 'radiogroup');
    sw.innerHTML = '';
    c.colors.forEach(col => {
      const b = document.createElement('button');
      b.type = 'button'; b.className = 'order-swatch'; b.dataset.color = col.color_id;
      b.style.setProperty('--sw', col.hex_code);
      b.setAttribute('role', 'radio'); b.setAttribute('aria-label', col.name); b.title = col.name;
      b.addEventListener('click', () => { setColor(col.color_id); updatePreview(); });
      sw.appendChild(b);
    });

    ['cWidth', 'cHeight'].forEach(id => { $(id).min = c.limits.min_cm; $(id).max = c.limits.max_cm; });
    $('cQty').max = c.limits.max_qty;
    resetForm();

    $('customizeForm').addEventListener('input', updatePreview);
    $('customizeForm').addEventListener('change', updatePreview);
    window.addEventListener('resize', updatePreview);
  }

  function resetForm() {
    $('cWidth').value = 120; $('cHeight').value = 150; $('cQty').value = 1;
    $('cMaterial').selectedIndex = 0;
    setColor(state.catalog.colors[0].color_id);
    const available = state.catalog.products.find(p => Number(p.stock_qty) > 0);
    if (available) {
      selectProduct(available.product_id);
    } else {
      state.productId = 0;
      document.querySelectorAll('#typeTiles .order-type').forEach(b => b.setAttribute('aria-checked', 'false'));
      $('addItemBtn').disabled = true;
      $('previewUnit').textContent = 'All blind types are currently out of stock.';
    }
  }

  function selectProduct(id) {
    const product = productById(id);
    if (!product || Number(product.stock_qty) < 1) return;
    state.productId = +id;
    document.querySelectorAll('#typeTiles .order-type').forEach(b => b.setAttribute('aria-checked', String(+b.dataset.product === +id)));
    const inCart = state.cart.filter(item => +item.product_id === +id).reduce((total, item) => total + Number(item.quantity), 0);
    const available = Number(product.stock_qty) - inCart;
    const remaining = Math.max(1, Math.min(state.catalog.limits.max_qty, available));
    $('addItemBtn').disabled = available < 1;
    $('cQty').max = remaining;
    if (Number($('cQty').value) > remaining) $('cQty').value = remaining;
    updatePreview();
  }

  function setColor(id) {
    state.colorId = +id;
    document.querySelectorAll('#swatches .order-swatch').forEach(b => b.setAttribute('aria-checked', String(+b.dataset.color === +id)));
    const col = colorById(id);
    $('colorName').textContent = col ? '— ' + col.name : '';
  }

  function readSpec() {
    return {
      product_id: state.productId, material_id: +$('cMaterial').value, color_id: state.colorId,
      width_cm: parseFloat($('cWidth').value), height_cm: parseFloat($('cHeight').value),
      quantity: parseInt($('cQty').value, 10),
    };
  }

  // Mirrors unitPrice() in portal/catalog_lib.php (preview only; the server is the authority).
  function estimate(sp) {
    const L = state.catalog.limits, p = productById(sp.product_id), m = materialById(sp.material_id);
    if (!p || !m || !colorById(sp.color_id)) return null;
    if (!(sp.width_cm >= L.min_cm && sp.width_cm <= L.max_cm && sp.height_cm >= L.min_cm && sp.height_cm <= L.max_cm)
        || !(sp.quantity >= 1 && sp.quantity <= L.max_qty)) return null;
    const area = Math.max(L.min_area, (sp.width_cm * sp.height_cm) / 10000);
    const unit = Math.round((area * parseFloat(p.base_price) + parseFloat(m.price_modifier)) * 100) / 100;
    return { unit, total: Math.round(unit * sp.quantity * 100) / 100 };
  }

  function blindStyle(type) {
    const t = String(type || '').toLowerCase();
    if (/venetian|slat/.test(t)) return 'slats';
    if (/roman/.test(t)) return 'roman';
    if (/vertical/.test(t)) return 'vertical';
    if (/cellular|honeycomb/.test(t)) return 'cellular';
    if (/zebra/.test(t)) return 'zebra';
    return 'roller';
  }

  function blindBackground(style, hex) {
    switch (style) {
      case 'slats':    return `repeating-linear-gradient(180deg, ${hex} 0 9px, rgba(0,0,0,.35) 9px 11px)`;
      case 'roman':    return `repeating-linear-gradient(180deg, ${hex} 0 34px, rgba(0,0,0,.3) 34px 38px, rgba(255,255,255,.08) 38px 42px)`;
      case 'vertical': return `repeating-linear-gradient(90deg, ${hex} 0 16px, rgba(0,0,0,.35) 16px 18px)`;
      case 'cellular': return `repeating-linear-gradient(180deg, ${hex} 0 7px, rgba(0,0,0,.22) 7px 8px)`;
      case 'zebra':    return `repeating-linear-gradient(180deg, ${hex} 0 14px, rgba(255,255,255,.55) 14px 28px)`;
      default:         return hex;
    }
  }

  function updatePreview() {
    if (!state.catalog || !state.productId) return;
    const sp = readSpec(), p = productById(sp.product_id), m = materialById(sp.material_id), col = colorById(sp.color_id), L = state.catalog.limits;
    if (!p || !m || !col) return;

    const stage = document.querySelector('.order-preview__stage'), win = $('previewWindow');
    const ratio = Math.min(2.5, Math.max(0.4, (sp.width_cm / sp.height_cm) || 0.8));
    const sw = stage.clientWidth, sh = stage.clientHeight;
    if (sw && sh) {
      const w = Math.min(sw, sh * ratio);
      win.style.width = w + 'px'; win.style.height = (w / ratio) + 'px';
    }
    const blind = $('previewBlind');
    blind.style.background = blindBackground(blindStyle(p.blind_type), col.hex_code);
    blind.style.opacity = /Sheer/i.test(m.name) ? 0.65 : /Sunscreen|Light-filtering/i.test(m.name) ? 0.85 : 1;

    const rows = [['Type', p.name], ['Size', `${sp.width_cm || '—'} × ${sp.height_cm || '—'} cm`],
      ['Quantity', sp.quantity || '—'], ['Material', m.name], ['Colour', col.name]];
    $('previewList').innerHTML = rows.map(([k, v]) => `<dt>${esc(k)}</dt><dd>${esc(v)}</dd>`).join('');

    const est = estimate(sp);
    $('previewTotal').textContent = est ? money(est.total) : '₱0.00';
    const inCart = state.cart.filter(item => +item.product_id === +sp.product_id).reduce((total, item) => total + Number(item.quantity), 0);
    const remaining = Number(p.stock_qty) - inCart;
    $('previewUnit').textContent = Number(p.stock_qty) < 1
      ? 'Out of stock.'
      : sp.quantity > remaining
        ? `Only ${Math.max(0, remaining)} remaining in stock.`
        : est
          ? (sp.quantity > 1 ? `${money(est.unit)} each × ${sp.quantity}` : `${Number(p.stock_qty)} in stock`)
          : `Width and height must be ${L.min_cm}–${L.max_cm} cm; quantity 1–${L.max_qty}.`;
  }

  /* ---------- Cart (blinds going into the order) ---------- */
  const cartTotal = () => state.cart.reduce((s, i) => s + i.total, 0);

  function renderCart() {
    $('cartCard').hidden = !state.cart.length;
    $('cartBody').innerHTML = state.cart.map((it, i) => `<tr>
      <td>${esc(it.product)}<br><small>${esc(it.material)} · ${esc(it.color)}</small></td>
      <td>${esc(it.width_cm)} × ${esc(it.height_cm)}</td><td>${esc(it.quantity)}</td><td>${money(it.total)}</td>
      <td><button type="button" data-remove="${i}">Remove</button></td></tr>`).join('');
    $('cartTotal').textContent = money(cartTotal());
  }

  /* ---------- Saved quotations ---------- */
  function renderSavedQuotes(list) {
    const active = list.filter(q => ['Pending', 'Returned', 'Active'].includes(q.status));
    $('savedQuotesCard').hidden = !active.length;
    $('savedQuotesBody').innerHTML = active.map(q => `<tr>
      <td>${esc(q.reference)}</td><td>${esc(q.item_count)}</td><td>${money(q.total_amount)}</td>
      <td>${esc(fmtDate(q.valid_until))}</td>
      <td>${esc(q.status === 'Pending' ? 'Awaiting admin review' : q.status === 'Returned' ? 'Quotation returned' : q.status)}</td>
      <td><button type="button" data-quote="${esc(q.quotation_id)}">View</button></td></tr>`).join('');
  }

  async function loadSavedQuotes() {
    try {
      const res = await api('quotation.php');
      renderSavedQuotes(res.success ? res.quotations : []);
    } catch (err) { if (err.auth) sessionEnded(); /* otherwise the list simply stays hidden */ }
  }

  $('savedQuotesBody').addEventListener('click', async (e) => {
    const b = e.target.closest('[data-quote]'); if (!b) return;
    clearAlert(); b.disabled = true;
    try {
      const res = await api(`quotation.php?id=${encodeURIComponent(b.dataset.quote)}`);
      if (!res.success) return showAlert(resultErrors(res, 'Could not open that quotation.'));
      state.quote = res.quotation; renderQuote(); go('quote');
    } catch (err) { fail(err); } finally { b.disabled = false; }
  });

  /* ---------- Quotation (optional) ---------- */
  function quoteRow(it) {
    return `<tr>
      <td>${esc(it.product)}<br><small>${esc(it.material)} · ${esc(it.color)}</small></td>
      <td>${esc(it.width_cm)} × ${esc(it.height_cm)}</td><td>${esc(it.quantity)}
        ${Number(it.stock_qty) < Number(it.quantity) ? `<br><small class="order-stock--out">${Number(it.stock_qty) < 1 ? 'Out of stock' : `Only ${Number(it.stock_qty)} in stock`}</small>` : ''}
      </td>
      <td>${money(it.unit_price)}</td><td>${money(it.total_amount)}</td></tr>`;
  }

  function renderQuote() {
    const q = state.quote;
    $('quoteRef').textContent = `Quotation ${q.reference}`;
    $('quoteMeta').textContent = `Requested by ${state.customer.full_name} on ${fmtDate(q.created_at)} · valid until ${fmtDate(q.valid_until)}`
      + (q.status === 'Pending' ? ' · waiting for Santi Blinds to review and return your quotation' : q.status === 'Returned' ? ' · quotation returned by Santi Blinds; review the final price below' : q.status === 'Ordered' ? ' · already ordered' : q.status === 'Expired' ? ' · expired' : '');
    const unavailable = q.items.some(item => Number(item.stock_qty) < Number(item.quantity));
    if (unavailable) $('quoteMeta').textContent += ' · one or more blinds are out of stock or have insufficient stock';
    $('quoteBody').innerHTML = q.items.map(quoteRow).join('');
    $('quoteTotal').textContent = money(q.total_amount);
    $('quoteContinueBtn').disabled = q.status !== 'Returned' || unavailable;
  }

  // Review works from either a quotation (state.quote) or straight from the cart (no quotation).
  function renderReview() {
    const c = state.customer;
    const direct = !state.quote;
    const lines = direct
      ? state.cart.map(it => ({ ...it, total_amount: it.total }))
      : state.quote.items;
    const total = direct ? cartTotal() : state.quote.total_amount;

    $('reviewRef').textContent = direct ? '' : `(${state.quote.reference})`;
    $('reviewBody').innerHTML = lines.map(it => `<tr>
      <td>${esc(it.product)}<br><small>${esc(it.material)} · ${esc(it.color)}</small></td>
      <td>${esc(it.width_cm)} × ${esc(it.height_cm)}</td><td>${esc(it.quantity)}
        ${Number(it.stock_qty) < Number(it.quantity) ? `<br><small class="order-stock--out">${Number(it.stock_qty) < 1 ? 'Out of stock' : `Only ${Number(it.stock_qty)} in stock`}</small>` : ''}
      </td><td>${money(it.total_amount)}</td></tr>`).join('');
    $('reviewTotal').textContent = money(total);
    $('reviewAccount').innerHTML = [['Name', c.full_name], ['Email', c.email], ['Contact', c.phone]]
      .map(([k, v]) => `<dt>${esc(k)}</dt><dd>${esc(v)}</dd>`).join('');
    $('oAddress').value = c.address || '';
  }

  async function refreshStockAvailability() {
    const latest = await api('catalog.php');
    if (!latest.success || !Array.isArray(latest.products)) {
      throw new Error(latest.message || 'Could not refresh product availability. Please try again.');
    }
    const latestById = new Map(latest.products.map(product => [+product.product_id, Number(product.stock_qty)]));
    state.catalog.products.forEach(product => {
      product.stock_qty = latestById.get(+product.product_id) || 0;
      const tile = document.querySelector(`#typeTiles [data-product="${product.product_id}"]`);
      if (tile) {
        tile.disabled = product.stock_qty < 1;
        const label = tile.querySelector('[data-stock-label]');
        label.textContent = product.stock_qty < 1 ? 'Out of stock' : `${product.stock_qty} in stock`;
        label.className = product.stock_qty < 1 ? 'order-stock--out' : 'order-stock';
      }
    });
    state.cart.forEach(item => {
      const product = productById(item.product_id);
      item.stock_qty = product ? Number(product.stock_qty) : 0;
    });

    const selected = productById(state.productId);
    if (selected && Number(selected.stock_qty) > 0) {
      selectProduct(selected.product_id);
    } else {
      const available = state.catalog.products.find(product => {
        const inCart = state.cart.filter(item => +item.product_id === +product.product_id)
          .reduce((total, item) => total + Number(item.quantity), 0);
        return Number(product.stock_qty) > inCart;
      });
      if (available) selectProduct(available.product_id);
      else {
        state.productId = 0;
        document.querySelectorAll('#typeTiles .order-type').forEach(button => button.setAttribute('aria-checked', 'false'));
        $('addItemBtn').disabled = true;
        $('previewUnit').textContent = state.catalog.products.some(product => Number(product.stock_qty) > 0)
          ? 'No additional stock remains for the products in your order.'
          : 'All blind types are currently out of stock.';
      }
    }
  }

  /* ---------- Events ---------- */
  $('customizeForm').addEventListener('submit', async (e) => {
    e.preventDefault(); clearAlert();
    const sp = readSpec(), est = estimate(sp), L = state.catalog.limits;
    const button = $('addItemBtn');
    button.disabled = true;
    try {
      await refreshStockAvailability();
      const product = productById(sp.product_id);
      if (!product || Number(product.stock_qty) < 1) {
        showAlert(`${product ? product.name : 'This blind'} is out of stock.`);
        return;
      }
      const inCart = state.cart.filter(item => +item.product_id === +sp.product_id).reduce((total, item) => total + Number(item.quantity), 0);
      if (inCart + sp.quantity > Number(product.stock_qty)) {
        showAlert(`Only ${Math.max(0, Number(product.stock_qty) - inCart)} ${product.name} in stock.`);
        return;
      }
      if (!est) {
        showAlert(`Please enter a width and height of ${L.min_cm}–${L.max_cm} cm and a quantity of 1–${L.max_qty}.`);
        return;
      }
      if (state.cart.length >= L.max_items) {
        showAlert(`An order can have at most ${L.max_items} items.`);
        return;
      }
      state.cart.push({
        ...sp, unit: est.unit, total: est.total,
        stock_qty: Number(product.stock_qty),
        product: product.name, material: materialById(sp.material_id).name, color: colorById(sp.color_id).name,
      });
      renderCart();
      selectProduct(product.product_id);
      showAlert('Added. Add another blind, or place your order.', true);
      $('cartCard').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    } catch (error) {
      fail(error);
    } finally {
      const selected = productById(state.productId);
      button.disabled = !selected || Number(selected.stock_qty) - state.cart
        .filter(item => +item.product_id === +selected.product_id)
        .reduce((total, item) => total + Number(item.quantity), 0) < 1;
    }
  });

  $('cartBody').addEventListener('click', (e) => {
    const b = e.target.closest('[data-remove]'); if (!b) return;
    state.cart.splice(+b.dataset.remove, 1);
    renderCart();
    if (state.productId) selectProduct(state.productId);
  });

  // Optional: place the order straight from the cart, skipping the quotation.
  $('orderNowBtn').addEventListener('click', () => {
    clearAlert();
    if (!state.cart.length) return showAlert('Add at least one blind first.');
    state.quote = null;
    renderReview(); go('review');
  });

  // Optional: get a saved, price-locked quotation first.
  $('getQuoteBtn').addEventListener('click', async () => {
    clearAlert();
    if (!state.cart.length) return showAlert('Add at least one blind first.');
    const btn = $('getQuoteBtn'); btn.disabled = true;
    try {
      const items = state.cart.map(({ product_id, material_id, color_id, width_cm, height_cm, quantity }) =>
        ({ product_id, material_id, color_id, width_cm, height_cm, quantity }));
      const res = await api('quotation.php', { items });
      if (!res.success) return showAlert(resultErrors(res, 'Could not prepare your quotation.'));
      state.quote = res.quotation;
      state.cart = []; renderCart();           // the quotation now holds these blinds
      loadSavedQuotes();
      renderQuote(); go('quote');
    } catch (err) { fail(err); } finally { btn.disabled = false; }
  });

  $('quoteBackBtn').addEventListener('click', () => { clearAlert(); go('customize'); });

  $('quotePrintBtn').addEventListener('click', () => {
    document.body.classList.add('printing-quote');
    window.addEventListener('afterprint', () => document.body.classList.remove('printing-quote'), { once: true });
    window.print();
  });

  $('quoteContinueBtn').addEventListener('click', () => {
    clearAlert();
    if (!state.quote || state.quote.status !== 'Returned') return showAlert('This quotation is not ready to order yet. Please wait for Santi Blinds to return it.');
    renderReview(); go('review');
  });

  $('reviewBackBtn').addEventListener('click', () => { clearAlert(); go(state.quote ? 'quote' : 'customize'); });

  $('orderForm').addEventListener('submit', async (e) => {
    e.preventDefault(); clearAlert();
    const btn = $('placeOrderBtn');
    const address = $('oAddress').value.trim();
    if (!state.quote && !state.cart.length) return showAlert('Add at least one blind first.');
    if (!address) return showAlert('Please enter the installation / delivery address.');

    const fromQuote = !!state.quote;
    const payload = fromQuote
      ? { quotation_id: state.quote.quotation_id, address }
      : { address, items: state.cart.map(({ product_id, material_id, color_id, width_cm, height_cm, quantity }) =>
          ({ product_id, material_id, color_id, width_cm, height_cm, quantity })) };

    btn.disabled = true;
    try {
      const res = await api('order.php', payload);
      if (!res.success) return showAlert(resultErrors(res, 'Could not place your order.'));
      state.customer.address = address;
      const first = state.customer.full_name.split(' ')[0];
      const basis = res.order.quotation ? `from quotation ${res.order.quotation}` : 'directly from your design';
      const receiptDate = fmtDate(res.receipt.order_date);
      $('doneRef').textContent = `Reference ${res.order.reference}`;
      $('doneMsg').textContent = `Thank you, ${first}! We've received your order for ${money(res.order.amount)} ${basis}. Our team will contact you on ${state.customer.phone} within one business day to confirm and schedule your measurement.`;
      $('receiptMeta').textContent = `Placed on ${receiptDate}`;
      $('receiptMeta').dataset.orderDate = receiptDate;
      $('receiptMeta').dataset.source = res.order.quotation || 'Direct order';
      $('receiptDetails').innerHTML = `
        <dt>Order reference</dt><dd>${esc(res.order.reference)}</dd>
        <dt>Customer</dt><dd>${esc(state.customer.full_name)}</dd>
        <dt>Phone</dt><dd>${esc(state.customer.phone)}</dd>
        <dt>Email</dt><dd>${esc(state.customer.email)}</dd>
        <dt>Address</dt><dd>${esc(address)}</dd>
        <dt>Quotation</dt><dd>${esc(res.order.quotation || 'Direct order')}</dd>
      `;
      $('receiptItems').innerHTML = res.receipt.items.map(item => `
        <tr>
          <td>${esc(item.product)}<br><small>${esc(item.blind_type)}</small></td>
          <td>${esc(item.material)}</td>
          <td>${esc(item.color)}</td>
          <td>${Number(item.width_cm)} × ${Number(item.height_cm)}</td>
          <td>${Number(item.quantity)}</td>
          <td>${money(item.unit_price)}</td>
          <td>${money(item.total_amount)}</td>
        </tr>
      `).join('');
      $('receiptTotal').textContent = money(res.order.amount);
      if (!fromQuote) { state.cart = []; renderCart(); }
      state.quote = null;
      loadSavedQuotes();
      go('done');
    } catch (err) { fail(err); } finally { btn.disabled = false; }
  });

  $('newDesignBtn').addEventListener('click', () => { clearAlert(); resetForm(); go('customize'); });
  $('receiptPrintBtn').addEventListener('click', () => {
    document.body.classList.add('printing-receipt');
    window.addEventListener('afterprint', () => document.body.classList.remove('printing-receipt'), { once: true });
    window.print();
  });
  $('receiptPngBtn').addEventListener('click', async (event) => {
    const button = event.currentTarget;
    button.disabled = true;
    try {
      const receipt = {
        reference: $('doneRef').textContent.replace(/^Reference\s*/, ''),
        date: $('receiptMeta').dataset.orderDate,
        customer: state.customer,
        address: $('oAddress').value.trim(),
        source: $('receiptMeta').dataset.source,
        items: Array.from($('receiptItems').rows, row => Array.from(row.cells, cell => cell.innerText.trim().replace(/\s+/g, ' '))),
        total: $('receiptTotal').textContent,
      };
      const canvas = document.createElement('canvas');
      const ctx = canvas.getContext('2d');
      if (!ctx) throw new Error('Your browser could not create the receipt image.');

      const scale = 2;
      const width = 1400;
      const margin = 72;
      const contentWidth = width - margin * 2;
      const columns = [
        { label: 'BLIND', width: 220 },
        { label: 'MATERIAL', width: 210 },
        { label: 'COLOUR', width: 150 },
        { label: 'SIZE (CM)', width: 160 },
        { label: 'QTY', width: 80 },
        { label: 'UNIT PRICE', width: 200 },
        { label: 'TOTAL', width: 236 },
      ];
      const lineHeight = 26;
      const wrap = (text, maxWidth, font) => {
        ctx.font = font;
        const words = String(text || '—').split(/\s+/);
        const lines = [];
        let line = '';
        words.forEach(word => {
          const candidate = line ? `${line} ${word}` : word;
          if (line && ctx.measureText(candidate).width > maxWidth) {
            lines.push(line);
            line = word;
          } else line = candidate;
        });
        if (line) lines.push(line);
        return lines;
      };
      const rows = receipt.items.map(cells => {
        const cellLines = cells.map((text, index) =>
          wrap(text, columns[index].width - 24, '20px Arial')
        );
        return { cellLines, height: Math.max(58, ...cellLines.map(lines => lines.length * lineHeight + 26)) };
      });
      const tableHeight = 54 + rows.reduce((sum, row) => sum + row.height, 0);
      const height = 610 + tableHeight + 150;
      canvas.width = width * scale;
      canvas.height = height * scale;
      ctx.scale(scale, scale);
      ctx.fillStyle = '#ffffff';
      ctx.fillRect(0, 0, width, height);

      let y = 72;
      ctx.fillStyle = '#242923';
      ctx.font = 'bold 34px Arial';
      ctx.fillText('SANTI BLINDS', margin, y);
      ctx.textAlign = 'right';
      ctx.font = 'bold 24px Arial';
      ctx.fillText('ORDER RECEIPT', width - margin, y);
      ctx.textAlign = 'left';
      y += 42;
      ctx.fillStyle = '#77766d';
      ctx.font = '18px Arial';
      ctx.fillText('Window coverings designed for light, made for life.', margin, y);
      y += 42;
      ctx.strokeStyle = '#a2a092';
      ctx.lineWidth = 2;
      ctx.beginPath();
      ctx.moveTo(margin, y);
      ctx.lineTo(width - margin, y);
      ctx.stroke();
      y += 42;

      const details = [
        ['Receipt reference', receipt.reference],
        ['Order date', receipt.date || '—'],
        ['Customer', receipt.customer.full_name || '—'],
        ['Phone', receipt.customer.phone || '—'],
        ['Email', receipt.customer.email || '—'],
        ['Installation / delivery address', receipt.address || '—'],
        ['Quotation', receipt.source || 'Direct order'],
      ];
      const detailColumn = contentWidth / 2;
      details.forEach(([label, value], index) => {
        const x = margin + (index % 2) * detailColumn;
        if (index > 0 && index % 2 === 0) y += 80;
        ctx.fillStyle = '#77766d';
        ctx.font = '16px Arial';
        ctx.fillText(label.toUpperCase(), x, y);
        ctx.fillStyle = '#242923';
        ctx.font = '20px Arial';
        const valueLines = wrap(value, detailColumn - 24, '20px Arial');
        valueLines.forEach((line, lineIndex) => ctx.fillText(line, x, y + 26 + lineIndex * 24));
      });
      y += 60;

      const tableX = margin;
      const headerHeight = 54;
      ctx.fillStyle = '#242923';
      ctx.fillRect(tableX, y, contentWidth, headerHeight);
      ctx.font = 'bold 15px Arial';
      ctx.fillStyle = '#ffffff';
      let x = tableX;
      columns.forEach(column => {
        ctx.fillText(column.label, x + 12, y + 34);
        x += column.width;
      });
      y += headerHeight;

      rows.forEach(row => {
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(tableX, y, contentWidth, row.height);
        ctx.strokeStyle = '#d8d8d2';
        ctx.lineWidth = 1;
        ctx.strokeRect(tableX, y, contentWidth, row.height);
        x = tableX;
        row.cellLines.forEach((lines, index) => {
          ctx.fillStyle = '#242923';
          ctx.font = '20px Arial';
          lines.forEach((line, lineIndex) => ctx.fillText(line, x + 12, y + 30 + lineIndex * lineHeight));
          x += columns[index].width;
          if (index < columns.length - 1) {
            ctx.beginPath();
            ctx.moveTo(x, y);
            ctx.lineTo(x, y + row.height);
            ctx.stroke();
          }
        });
        y += row.height;
      });

      y += 50;
      ctx.textAlign = 'right';
      ctx.fillStyle = '#77766d';
      ctx.font = 'bold 18px Arial';
      ctx.fillText('ORDER TOTAL', width - margin - 230, y);
      ctx.fillStyle = '#242923';
      ctx.font = 'bold 30px Arial';
      ctx.fillText(receipt.total, width - margin, y + 4);
      y += 52;
      ctx.textAlign = 'left';
      ctx.fillStyle = '#77766d';
      ctx.font = '18px Arial';
      ctx.fillText('No payment was collected when this order was placed.', margin, y);
      ctx.fillText('Final price is confirmed after an on-site measurement.', margin, y + 30);

      if (typeof canvas.toBlob !== 'function') throw new Error('PNG download is not supported in this browser.');
      const blob = await new Promise((resolve, reject) => {
        canvas.toBlob(result => result ? resolve(result) : reject(new Error('Could not create the receipt PNG.')), 'image/png');
      });
      const url = URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.href = url;
      link.download = `santi-blinds-receipt-${receipt.reference}.png`;
      link.click();
      window.setTimeout(() => URL.revokeObjectURL(url), 1000);
    } catch (error) {
      alert(error.message || 'Could not download the receipt image.');
    } finally {
      button.disabled = false;
    }
  });

  /* ---------- Boot ---------- */
  (async function boot() {
    try {
      const cat = await api('catalog.php');
      if (!cat.success || !cat.products.length || !cat.materials.length || !cat.colors.length) {
        throw new Error('The blind catalog is not available right now. Please try again later.');
      }
      state.catalog = cat;
      buildForm();
      renderCart();

      if (state.customer) {
        await startSession(state.customer);
        return;
      }

      const me = await api('customer_session.php');
      if (state.customer) { await startSession(state.customer); }
      else if (me.success && me.logged_in) { await startSession(me.customer); }
      else {
        showAuth(window.location.hash === '#register' ? 'register' : 'login');
        go('auth');
      }
    } catch (err) {
      showAlert((err && err.message) || 'Could not reach the server. Is Apache running? Open this page through http://localhost/…');
    }
  })();
})();


/* ============================================================
   MY ORDERS (my-orders.html)
   Lists the signed-in customer's orders and tracks each one
   (Pending > Processing > Completed) using portal/my_orders.php.
   ============================================================ */
(function initMyOrders() {
  const app = document.getElementById('ordersApp');
  if (!app) return;

  const $ = (id) => document.getElementById(id);
  // Local helper: the helper in the earlier account IIFE is not in this scope.
  const setHidden = (id, hidden) => { const el = $(id); if (el) el.hidden = Boolean(hidden); };
  const esc = (v) => String(v ?? '').replace(/[&<>"']/g, ch => (
    { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch]));
  const money = (n) => '₱' + Number(n || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  const fmtDate = (v) => {
    const d = new Date(String(v || '').replace(' ', 'T'));
    return isNaN(d) ? '—' : d.toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' });
  };
  const cm = (n) => (Math.round(Number(n) * 10) / 10) + ' cm';

  const STATUS_NOTE = {
    Pending:    'We have received your order and will confirm it shortly.',
    Processing: 'Your blinds are being prepared.',
    Completed:  'Your order is complete. Thank you for choosing Santi Blinds!',
    Cancelled:  'This order was cancelled.',
  };
  const FILTERS = {
    all:        () => true,
    active:     (o) => o.status === 'Pending' || o.status === 'Processing',
    Completed:  (o) => o.status === 'Completed',
    Cancelled:  (o) => o.status === 'Cancelled',
  };

  const state = { orders: [], quotations: [], filter: 'all', open: new Set(), confirmingCancel: new Set() };

  function showAlert(msg) { const el = $('ordersAlert'); if (!el) return; el.textContent = msg; el.hidden = false; }

  function timeline(o) {
    if (o.tracking.cancelled) {
      return '<p class="acct-cancelled">This order was cancelled.</p>';
    }
    return '<ol class="acct-track" aria-label="Order progress">' + o.tracking.steps.map(s =>
      `<li class="is-${esc(s.state)}"${s.state === 'current' ? ' aria-current="step"' : ''}><span>${esc(s.label)}</span></li>`
    ).join('') + '</ol>';
  }

  function card(o) {
    const open = state.open.has(o.order_id);
    const items = o.items.map(i => `<tr>
        <td><strong>${esc(i.product)}</strong><small>${esc(i.material)} · ${esc(i.color)}</small></td>
        <td>${esc(cm(i.width_cm))} × ${esc(cm(i.height_cm))}</td>
        <td>${esc(i.quantity)}</td>
        <td>${money(i.total_amount)}</td></tr>`).join('');
    return `<article class="acct-order" data-order="${esc(o.order_id)}">
      <header class="acct-order__head">
        <div>
          <h2 class="acct-order__ref">${esc(o.reference)}</h2>
          <p class="acct-order__meta">Placed ${esc(fmtDate(o.order_date))} · ${esc(o.item_count)} ${o.item_count === 1 ? 'item' : 'items'}</p>
        </div>
        <span class="acct-badge acct-badge--${esc(o.status.toLowerCase())}">${esc(o.status)}</span>
        <strong class="acct-order__total">${money(o.total_amount)}</strong>
        <button type="button" class="btn btn--ghost acct-order__toggle" data-toggle="${esc(o.order_id)}"
                aria-expanded="${open}" aria-controls="order-detail-${esc(o.order_id)}">${open ? 'Hide details' : 'Track order'}</button>
      </header>
      <div class="acct-order__detail" id="order-detail-${esc(o.order_id)}"${open ? '' : ' hidden'}>
        ${timeline(o)}
        <p class="acct-note">${esc(STATUS_NOTE[o.status] || '')}</p>
        <div class="acct-table-wrap">
          <table class="acct-table">
            <thead><tr><th>Item</th><th>Size</th><th>Qty</th><th>Amount</th></tr></thead>
            <tbody>${items}</tbody>
            <tfoot><tr><td colspan="3">Total</td><td>${money(o.total_amount)}</td></tr></tfoot>
          </table>
        </div>
        ${o.quotation ? `<p class="acct-note">From quotation ${esc(o.quotation)}</p>` : ''}
        ${['Pending', 'Processing'].includes(o.status) ? `
          ${state.confirmingCancel.has(o.order_id) ? `
            <div class="acct-cancel-confirm" role="group" aria-label="Confirm cancellation of order ${esc(o.reference)}">
              <p>Are you sure you want to cancel this order?</p>
              <button type="button" class="btn btn--ghost acct-cancel-order" data-confirm-cancel="${esc(o.order_id)}">Yes, cancel order</button>
              <button type="button" class="btn btn--ghost" data-keep-order="${esc(o.order_id)}">Keep order</button>
            </div>
          ` : `<button type="button" class="btn btn--ghost acct-cancel-order" data-cancel-order="${esc(o.order_id)}">Cancel order</button>`}
        ` : ''}
      </div>
    </article>`;
  }

  function quotationCard(q) {
    const statusText = q.status === 'Pending' ? 'Awaiting admin review' :
      q.status === 'Returned' ? 'Quotation returned — ready for review' : q.status;
    const statusClass = String(q.status || 'pending').toLowerCase();
    return `<article class="acct-order acct-quotation">
      <header class="acct-order__head">
        <div><h2 class="acct-order__ref">${esc(q.reference)}</h2>
          <p class="acct-order__meta">Requested ${esc(fmtDate(q.created_at))} · ${esc(q.item_count)} ${Number(q.item_count) === 1 ? 'item' : 'items'}</p>
          <p class="acct-note">${esc(statusText)}</p>
        </div>
        <span class="acct-badge acct-badge--${esc(statusClass)}">${esc(q.status)}</span>
        <strong class="acct-order__total">${money(q.total_amount)}</strong>
        <a class="btn btn--ghost acct-order__toggle" href="order.html#savedQuotesCard">View quotation</a>
      </header>
      <p class="acct-note">${q.valid_until ? `Valid until ${esc(fmtDate(q.valid_until))}` : 'Waiting for quotation details from Santi Blinds.'}</p>
    </article>`;
  }

  function render() {
    if (state.filter === 'quotes') {
      const quotes = state.quotations.filter(q => ['Pending', 'Returned'].includes(q.status));
      setHidden('ordersList', true);
      setHidden('pendingQuotesList', false);
      const quotesList = $('pendingQuotesList');
      if (quotesList) quotesList.innerHTML = quotes.map(quotationCard).join('');
      setHidden('ordersEmpty', quotes.length > 0);
      const empty = $('ordersEmpty');
      if (empty) empty.textContent = quotes.length ? '' : 'You have no pending quotations.';
    } else {
      setHidden('ordersList', false);
      setHidden('pendingQuotesList', true);
      const list = state.orders.filter(FILTERS[state.filter] || FILTERS.all);
      const ordersList = $('ordersList');
      if (ordersList) ordersList.innerHTML = list.map(card).join('');
      setHidden('ordersEmpty', list.length > 0);
      const empty = $('ordersEmpty');
      if (empty) empty.textContent = state.orders.length
        ? 'No orders match this filter.'
        : "You haven't placed any orders yet.";
    }
    document.querySelectorAll('[data-filter]').forEach(b => {
      const on = b.dataset.filter === state.filter;
      b.classList.toggle('is-active', on);
      b.setAttribute('aria-pressed', String(on));
    });
  }

  const filterBar = document.querySelector('.acct-filters');
  if (filterBar) filterBar.addEventListener('click', (e) => {
    const b = e.target.closest('[data-filter]'); if (!b) return;
    state.filter = b.dataset.filter; render();
  });
  const ordersListElement = $('ordersList');
  if (ordersListElement) ordersListElement.addEventListener('click', (e) => {
    const confirmButton = e.target.closest('[data-confirm-cancel]');
    if (confirmButton) {
      cancelOrder(Number(confirmButton.dataset.confirmCancel), confirmButton);
      return;
    }
    const keepButton = e.target.closest('[data-keep-order]');
    if (keepButton) {
      const id = Number(keepButton.dataset.keepOrder);
      state.confirmingCancel.delete(id);
      render();
      const cancelButton = document.querySelector(`[data-cancel-order="${id}"]`);
      if (cancelButton) cancelButton.focus();
      return;
    }
    const cancelButton = e.target.closest('[data-cancel-order]');
    if (cancelButton) {
      const id = Number(cancelButton.dataset.cancelOrder);
      state.confirmingCancel.add(id);
      render();
      const confirmAction = document.querySelector(`[data-confirm-cancel="${id}"]`);
      if (confirmAction) confirmAction.focus();
      return;
    }
    const b = e.target.closest('[data-toggle]'); if (!b) return;
    const id = +b.dataset.toggle;
    state.confirmingCancel.clear();
    if (state.open.has(id)) state.open.delete(id);
    else { state.open.clear(); state.open.add(id); }
    render();
    const again = document.querySelector(`[data-toggle="${id}"]`); if (again) again.focus();
  });

  async function loadOrders(initial = false) {
    try {
      const res = await fetch('portal/my_orders.php', { credentials: 'include' });
      if (res.status === 401) { setHidden('ordersLoading', true); setHidden('ordersGate', false); return false; }
      const data = await res.json();
      if (!data.success) throw new Error(data.message || 'Could not load your orders.');
      state.orders = data.orders;
      try {
        const quoteRes = await fetch('portal/quotation.php', { credentials: 'include' });
        const quoteData = await quoteRes.json();
        state.quotations = quoteData.success ? (quoteData.quotations || []) : [];
      } catch (_) { state.quotations = []; }

      if (initial) {
        // Open the order named in the link (?order=12), else the newest one.
        const wanted = +new URLSearchParams(location.search).get('order');
        const pick = state.orders.find(o => o.order_id === wanted) || state.orders[0];
        if (pick) state.open.add(pick.order_id);
      }

      setHidden('ordersLoading', true);
      setHidden('ordersBody', false);
      render();
      return true;
    } catch (err) {
      setHidden('ordersLoading', true);
      showAlert((err && err.message) || 'Could not reach the server. Please try again.');
      return false;
    }
  }

  async function cancelOrder(orderId, button) {
    const order = state.orders.find(o => o.order_id === orderId);
    if (!order || !['Pending', 'Processing'].includes(order.status)) return;

    button.disabled = true;
    try {
      const res = await fetch('portal/cancel_order.php', {
        method: 'POST',
        credentials: 'include',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ order_id: orderId }),
      });
      if (res.status === 401) { window.location.href = 'order.html'; return; }
      const data = await res.json();
      if (!data.success) throw new Error(data.message || 'Could not cancel this order.');

      state.open.delete(orderId);
      state.confirmingCancel.delete(orderId);
      const refreshed = await loadOrders();
      if (!refreshed) {
        const cancelledOrder = state.orders.find(o => o.order_id === orderId);
        if (cancelledOrder) {
          cancelledOrder.status = 'Cancelled';
          cancelledOrder.tracking = { cancelled: true, steps: [] };
        }
        render();
      }
      showAlert(refreshed
        ? `Order ${order.reference} was cancelled.`
        : `Order ${order.reference} was cancelled, but the list could not refresh. Reload the page to see the latest status.`);
    } catch (err) {
      showAlert((err && err.message) || 'Could not cancel this order. Please try again.');
      button.disabled = false;
    }
  }

  loadOrders(true);
})();
