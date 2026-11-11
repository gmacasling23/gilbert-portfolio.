/* ============================================
   MAIN - AbiYarn
   Handles: mobile nav, shared page behaviors
   ============================================ */

document.addEventListener('DOMContentLoaded', () => {

  /* ── Mobile Navigation ─────────────────── */
  const hamburgerBtn = document.getElementById('hamburgerBtn');
  const mobileNav    = document.getElementById('mobileNav');
  const mobileClose  = document.getElementById('mobileClose');

  function openMobileNav() {
    mobileNav.classList.add('open');
    document.body.style.overflow = 'hidden';
  }

  function closeMobileNav() {
    mobileNav.classList.remove('open');
    document.body.style.overflow = '';
  }

  if (hamburgerBtn) hamburgerBtn.addEventListener('click', openMobileNav);
  if (mobileClose)  mobileClose.addEventListener('click', closeMobileNav);

  if (mobileNav) {
    mobileNav.addEventListener('click', (e) => {
      if (e.target === mobileNav) closeMobileNav();
    });
  }

  document.querySelectorAll('.mobile-nav-links a').forEach(link => {
    link.addEventListener('click', closeMobileNav);
  });

  /* ── Active Nav Link Highlight ─────────── */
  const currentPath = window.location.pathname.split('/').pop() || 'index.html';
  document.querySelectorAll('.nav a, .mobile-nav-links a').forEach(link => {
    const href = link.getAttribute('href');
    if (href === currentPath) link.classList.add('active');
  });

  /* ── Scroll: Header Shadow ─────────────── */
  const header = document.querySelector('.header');
  if (header) {
    window.addEventListener('scroll', () => {
      header.style.boxShadow = window.scrollY > 10
        ? '0 4px 20px rgba(0,0,0,0.08)'
        : 'none';
    }, { passive: true });
  }

  /* ── Scroll Fade-in ─────────────────────── */
  const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        entry.target.style.opacity = '1';
        entry.target.style.transform = 'translateY(0)';
        observer.unobserve(entry.target);
      }
    });
  }, { threshold: 0.1 });

  document.querySelectorAll(
    '.highlight-card, .feature-card, .featured-card, .product-card, .sidebar-card, .maker-card'
  ).forEach(el => {
    el.style.opacity = '0';
    el.style.transform = 'translateY(24px)';
    el.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
    observer.observe(el);
  });

});