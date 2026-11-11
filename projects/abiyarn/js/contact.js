/* ============================================
   CONTACT PAGE - AbiYarn
   Powered by EmailJS (https://emailjs.com)
   ============================================

   SETUP STEPS (one-time):
   1. Go to https://emailjs.com and create a free account
   2. Add a new Email Service → connect your Gmail (abiyarn.shop@gmail.com)
      → copy the Service ID (e.g. "service_abc123")
   3. Create an Email Template with these variables:
        {{from_name}}   {{from_email}}   {{phone}}
        {{subject}}     {{message}}      {{to_email}}
      → copy the Template ID (e.g. "template_abc123")
   4. Go to Account → API Keys → copy your Public Key
   5. Paste all three below and save.

   ============================================ */

const EMAILJS_SERVICE_ID  = 'service_poyx94u';   // ← replace
const EMAILJS_CONTACT_TID = 'template_x9t5r4h';  // ← replace
const EMAILJS_PUBLIC_KEY  = 'kzqo6SxEnRUvfa0TG';   // ← replace

document.addEventListener('DOMContentLoaded', () => {

  /* ── Init EmailJS ────────────────────────── */
  if (typeof emailjs !== 'undefined') {
    emailjs.init(EMAILJS_PUBLIC_KEY);
  }

  const form      = document.getElementById('contactForm');
  const success   = document.getElementById('contactSuccess');
  const submitBtn = form ? form.querySelector('button[type="submit"]') : null;

  if (!form) return;

  /* Inject icon into submit button */
  if (submitBtn && typeof ICONS !== 'undefined') {
    submitBtn.innerHTML = `<span class="btn-icon">${ICONS.send}</span> Send Message`;
  }

  form.addEventListener('submit', async (e) => {
    e.preventDefault();

    const nameEl    = document.getElementById('name');
    const emailEl   = document.getElementById('email');
    const phoneEl   = document.getElementById('phone');
    const subjectEl = document.getElementById('subject');
    const messageEl = document.getElementById('message');

    [nameEl, emailEl, messageEl].forEach(el => resetError(el));

    let valid = true;
    if (!nameEl.value.trim())    { highlightError(nameEl);    valid = false; }
    if (!emailEl.value.trim() || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailEl.value)) {
                                   highlightError(emailEl);   valid = false; }
    if (!messageEl.value.trim()) { highlightError(messageEl); valid = false; }

    if (!valid) {
      showNotification('Please fill in all required fields correctly.');
      return;
    }

    /* ── Sending state ───────────────────────── */
    submitBtn.innerHTML = `<span class="btn-icon">⏳</span> Sending…`;
    submitBtn.disabled  = true;

    const templateParams = {
      from_name : nameEl.value.trim(),
      from_email: emailEl.value.trim(),
      phone     : phoneEl ? (phoneEl.value.trim() || 'Not provided') : 'Not provided',
      subject   : subjectEl ? subjectEl.options[subjectEl.selectedIndex].text : 'General Inquiry',
      message   : messageEl.value.trim(),
      to_email  : 'abiyarn.shop@gmail.com',
    };

    try {
      await emailjs.send(EMAILJS_SERVICE_ID, EMAILJS_CONTACT_TID, templateParams);

      /* ── Success ─────────────────────────── */
      form.style.display = 'none';
      if (success) {
        const iconEl = success.querySelector('.success-icon');
        if (iconEl && typeof ICONS !== 'undefined') iconEl.innerHTML = ICONS.check;
        success.classList.add('show');
      }

    } catch (err) {
      console.error('EmailJS error:', err);
      submitBtn.innerHTML = `<span class="btn-icon">✉️</span> Send Message`;
      submitBtn.disabled  = false;
      showNotification('Failed to send message. Please try again or contact us directly.');
    }
  });

  function highlightError(el) {
    el.style.borderColor = 'var(--red-err)';
    el.style.boxShadow   = '0 0 0 3px rgba(239,68,68,0.15)';
    el.addEventListener('input', () => resetError(el), { once: true });
  }

  function resetError(el) {
    el.style.borderColor = '';
    el.style.boxShadow   = '';
  }

});