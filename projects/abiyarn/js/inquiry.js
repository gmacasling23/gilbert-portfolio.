/* ============================================
   INQUIRY PAGE - AbiYarn
   Powered by EmailJS (https://emailjs.com)
   ============================================

   Uses the same Service ID and Public Key as
   contact.js, but a SEPARATE template for
   inquiry-specific fields.

   SETUP:
   1. Complete EmailJS setup in contact.js first.
   2. Create a second Email Template for inquiries
      with these variables:
        {{from_name}}   {{from_email}}   {{phone}}
        {{order_type}}  {{quantity}}     {{colors}}
        {{needed_by}}   {{details}}      {{to_email}}
   3. Paste the new Template ID below.

   ============================================ */
const API_BASE = 'api'; // relative path — works whether the site is at the domain root or in a subfolder
const EMAILJS_INQUIRY_TID = 'template_gabv9hh'; // ← replace

document.addEventListener('DOMContentLoaded', () => {

  /* ── Init EmailJS ────────────────────────── */
  if (typeof emailjs !== 'undefined') {
    emailjs.init(EMAILJS_PUBLIC_KEY);
  }

  const form      = document.getElementById('inquiryForm');
  const success   = document.getElementById('inquirySuccess');
  const submitBtn = form ? form.querySelector('button[type="submit"]') : null;

  if (!form) return;

  form.addEventListener('submit', async (e) => {
    e.preventDefault();

    const nameEl     = document.getElementById('inqName');
    const emailEl    = document.getElementById('inqEmail');
    const phoneEl    = document.getElementById('inqPhone');
    const typeEl     = document.getElementById('inqType');
    const qtyEl      = document.getElementById('inqQuantity');
    const colorsEl   = document.getElementById('inqColors');
    const dateEl     = document.getElementById('inqDate');
    const detailsEl  = document.getElementById('inqDetails');

    /* Reset errors */
    [nameEl, emailEl, phoneEl, typeEl, detailsEl].forEach(el => {
      el.style.borderColor = '';
      el.style.boxShadow   = '';
    });

    let valid = true;
    if (!nameEl.value.trim())    { highlightError(nameEl);    valid = false; }
    if (!emailEl.value.trim() || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailEl.value)) {
                                   highlightError(emailEl);   valid = false; }
    if (!phoneEl.value.trim())   { highlightError(phoneEl);   valid = false; }
    if (!typeEl.value)           { highlightError(typeEl);    valid = false; }
    if (!detailsEl.value.trim()) { highlightError(detailsEl); valid = false; }

    if (!valid) {
      showNotification('Please fill in all required fields correctly.');
      return;
    }

    /* ── Sending state ───────────────────────── */
    submitBtn.innerHTML = `⏳ Sending…`;
    submitBtn.disabled  = true;

    const orderTypeLabel = typeEl.options[typeEl.selectedIndex].text;

    const templateParams = {
      from_name : nameEl.value.trim(),
      from_email: emailEl.value.trim(),
      phone     : phoneEl.value.trim(),
      order_type: orderTypeLabel,
      quantity  : qtyEl  ? (qtyEl.value  || 'Not specified') : 'Not specified',
      colors    : colorsEl ? (colorsEl.value.trim() || 'Not specified') : 'Not specified',
      needed_by : dateEl  ? (dateEl.value  || 'Not specified') : 'Not specified',
      details   : detailsEl.value.trim(),
      to_email  : 'abiyarn.shop@gmail.com',
    };

    try {
      await emailjs.send(EMAILJS_SERVICE_ID, EMAILJS_INQUIRY_TID, templateParams);

      /* ── Save to PHP + MySQL ─────────────────── */
      try {
        await fetch(`${API_BASE}/save-inquiry.php`, {
          method : 'POST',
          headers: { 'Content-Type': 'application/json' },
          body   : JSON.stringify(templateParams),
        });
      } catch (err) {
        console.warn('Could not reach inquiry API:', err);
        try {
          const adminInquiries = JSON.parse(localStorage.getItem('abiyarn_admin_inquiries') || '[]');
          adminInquiries.push({ ...templateParams, status: 'new', date: new Date().toISOString() });
          localStorage.setItem('abiyarn_admin_inquiries', JSON.stringify(adminInquiries));
        } catch (_) {}
      }

      /* ── Success ─────────────────────────── */
      form.style.display = 'none';
      if (success) {
        const iconEl = success.querySelector('.success-icon');
        if (iconEl && typeof ICONS !== 'undefined') iconEl.innerHTML = ICONS.check;
        success.classList.add('show');
      }

    } catch (err) {
      console.error('EmailJS error:', err);
      submitBtn.innerHTML = `Submit Inquiry 🧶`;
      submitBtn.disabled  = false;
      showNotification('Failed to send inquiry. Please try again or contact us directly.');
    }
  });

  function highlightError(el) {
    el.style.borderColor = 'var(--red-err, #ef4444)';
    el.style.boxShadow   = '0 0 0 3px rgba(239,68,68,0.15)';
    el.addEventListener('input', () => {
      el.style.borderColor = '';
      el.style.boxShadow   = '';
    }, { once: true });
  }

});