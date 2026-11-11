/* ============================================
   AUTH SYSTEM - AbiYarn
   Handles: login / register modal, session check,
   account dropdown, logout.
   ============================================ */

const AUTH_API_BASE = 'api'; // relative path — matches checkout.js / inquiry.js convention

document.addEventListener('DOMContentLoaded', () => {

  const accountBtn      = document.getElementById('accountBtn');
  const accountLabel    = document.getElementById('accountLabel');
  const accountDropdown = document.getElementById('accountDropdown');
  const accountName     = document.getElementById('accountName');
  const accountAdminLink= document.getElementById('accountAdminLink');
  const logoutBtn       = document.getElementById('logoutBtn');
  let accountOrdersLink = document.getElementById('accountOrdersLink');

  if (!accountOrdersLink && accountDropdown) {
    accountOrdersLink = document.createElement('a');
    accountOrdersLink.id = 'accountOrdersLink';
    accountOrdersLink.href = 'my-orders.php';
    accountOrdersLink.className = 'account-dropdown-link';
    accountOrdersLink.textContent = '📦 My orders';
    accountOrdersLink.style.display = 'none';
    accountDropdown.insertBefore(accountOrdersLink, logoutBtn);
  }

  const authModal   = document.getElementById('authModal');
  const authOverlay = document.getElementById('authOverlay');
  const closeAuthBtn = document.getElementById('closeAuthBtn');
  const authTabLogin = document.getElementById('authTabLogin');
  const authTabRegister = document.getElementById('authTabRegister');
  const loginForm    = document.getElementById('loginForm');
  const registerForm = document.getElementById('registerForm');
  const authError    = document.getElementById('authError');

  if (!accountBtn || !authModal) return; // page doesn't include the auth UI

  let currentUser = null;

  /* ── Modal open / close ──────────────────── */
  function openAuthModal(tab = 'login') {
    authError.style.display = 'none';
    switchAuthTab(tab);
    authModal.classList.add('open');
    document.body.style.overflow = 'hidden';
  }
  function closeAuthModal() {
    authModal.classList.remove('open');
    document.body.style.overflow = '';
  }
  if (authOverlay)  authOverlay.addEventListener('click', closeAuthModal);
  if (closeAuthBtn) closeAuthBtn.addEventListener('click', closeAuthModal);

  function switchAuthTab(tab) {
    const isLogin = tab === 'login';
    authTabLogin.classList.toggle('active', isLogin);
    authTabRegister.classList.toggle('active', !isLogin);
    loginForm.style.display    = isLogin ? 'block' : 'none';
    registerForm.style.display = isLogin ? 'none'  : 'block';
    authError.style.display = 'none';
  }
  authTabLogin.addEventListener('click', () => switchAuthTab('login'));
  authTabRegister.addEventListener('click', () => switchAuthTab('register'));

  function showAuthError(msg) {
    authError.textContent = msg;
    authError.style.display = 'block';
  }

  /* ── Account dropdown ────────────────────── */
  function toggleDropdown(force) {
    const open = force !== undefined ? force : !accountDropdown.classList.contains('open');
    accountDropdown.classList.toggle('open', open);
  }
  document.addEventListener('click', (e) => {
    if (!accountDropdown.classList.contains('open')) return;
    if (!accountDropdown.contains(e.target) && e.target !== accountBtn && !accountBtn.contains(e.target)) {
      toggleDropdown(false);
    }
  });

  accountBtn.addEventListener('click', () => {
    if (currentUser) {
      toggleDropdown();
    } else {
      openAuthModal('login');
    }
  });

  /* ── Render logged-in / logged-out state ─── */
  function renderUser(user) {
    currentUser = user;
    window.ABI_CURRENT_USER = user;
    document.body.classList.toggle('auth-logged-out', !user);
    if (user) {
      const fullName = user.full_name || user.username || 'Account';
      accountLabel.textContent = fullName.trim().split(/\s+/)[0];
      accountName.textContent = fullName;
      const role = document.createElement('span');
      role.className = 'account-dropdown-role';
      role.textContent = user.role || 'user';
      accountName.appendChild(role);
      if (accountAdminLink && (user.role === 'admin' || user.role === 'owner')) {
        accountAdminLink.style.display = 'flex';
      } else if (accountAdminLink) {
        accountAdminLink.style.display = 'none';
      }
      if (accountOrdersLink) accountOrdersLink.style.display = 'flex';
    } else {
      accountLabel.textContent = 'Login';
      if (accountOrdersLink) accountOrdersLink.style.display = 'none';
      toggleDropdown(false);
    }
  }

  /* ── Session check on load ───────────────── */
  fetch(`${AUTH_API_BASE}/auth.php?action=me`)
    .then(res => res.json())
    .then(data => renderUser(data && data.loggedIn ? data.user : null))
    .catch(() => renderUser(null));

  /* ── Login submit ────────────────────────── */
  loginForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData(loginForm);
    authError.style.display = 'none';

    try {
      const res = await fetch(`${AUTH_API_BASE}/auth.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          action: 'login',
          username: fd.get('username'),
          password: fd.get('password'),
        }),
      });
      const data = await res.json();
      if (!res.ok || data.error) {
        showAuthError(data.error || 'Could not log in.');
        return;
      }
      renderUser(data.user);
      closeAuthModal();
      loginForm.reset();
      if (data.user && ['admin', 'owner'].includes(data.user.role)) {
        window.location.assign('admin/index.php');
        return;
      }
    } catch (err) {
      showAuthError('Network error — please try again.');
    }
  });

  /* ── Register submit ─────────────────────── */
  registerForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData(registerForm);
    authError.style.display = 'none';

    try {
      const res = await fetch(`${AUTH_API_BASE}/auth.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          action: 'register',
          full_name: fd.get('full_name'),
          email: fd.get('email'),
          username: fd.get('username'),
          password: fd.get('password'),
        }),
      });
      const data = await res.json();
      if (!res.ok || data.error) {
        showAuthError(data.error || 'Could not create your account.');
        return;
      }
      renderUser(data.user);
      closeAuthModal();
      registerForm.reset();
    } catch (err) {
      showAuthError('Network error — please try again.');
    }
  });

  /* ── Logout ───────────────────────────────── */
  if (logoutBtn) {
    logoutBtn.addEventListener('click', async () => {
      try {
        await fetch(`${AUTH_API_BASE}/auth.php`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'logout' }),
        });
      } catch (err) { /* ignore — clear UI regardless */ }
      renderUser(null);
    });
  }

});
