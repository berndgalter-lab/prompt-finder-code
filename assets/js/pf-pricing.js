
/* ===== PF Pricing Toggle (Gutenberg only) ===== */
document.addEventListener('DOMContentLoaded', () => {
  const root = document.querySelector('.pf-pricing');
  if (!root) return;

  const btns = root.querySelectorAll('.pf-toggle-btn');
  const setBilling = (mode) => {
    // data-billing am Root – für CSS/States
    root.setAttribute('data-billing', mode);

    // Buttons ARIA + active
    btns.forEach(b => {
      const isActive = b.dataset.billing === mode;
      b.classList.toggle('is-active', isActive);
      b.setAttribute('aria-selected', String(isActive));
      b.setAttribute('tabindex', isActive ? '0' : '-1');
    });

    // Sichtbarkeit der Preiswerte/Per-Text
    root.querySelectorAll('[data-monthly],[data-annual],[data-per]').forEach(el => {
      // Falls du [data-per] nicht nutzt, ignoriert dieser Teil das Element einfach
      if (el.hasAttribute('data-monthly')) {
        el.style.display = (mode === 'monthly') ? '' : 'none';
      }
      if (el.hasAttribute('data-annual')) {
        el.style.display = (mode === 'annual') ? '' : 'none';
      }
      if (el.hasAttribute('data-per')) {
        el.textContent = (mode === 'annual') ? '/ year' : '/ month';
      }
    });

    try { localStorage.setItem('pf_billing', mode); } catch(e){}
  };

  // Initial aus localStorage oder HTML data-billing
  const saved = (() => { try { return localStorage.getItem('pf_billing'); } catch(e){ return null; } })();
  setBilling(saved || root.getAttribute('data-billing') || 'monthly');

  // Click handler
  btns.forEach(b => b.addEventListener('click', () => setBilling(b.dataset.billing)));

  // Tastatur (links/rechts) – ARIA Tabs behavior lite
  root.querySelector('.pf-billing-toggle')?.addEventListener('keydown', (e) => {
    if (!['ArrowLeft','ArrowRight'].includes(e.key)) return;
    const order = Array.from(btns);
    const idx = order.findIndex(b => b.classList.contains('is-active'));
    const next = e.key === 'ArrowRight' ? (idx + 1) % order.length : (idx - 1 + order.length) % order.length;
    order[next].focus();
    order[next].click();
  });
});

