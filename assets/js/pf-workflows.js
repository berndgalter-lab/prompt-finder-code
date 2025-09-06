/* ============================================================
   Prompt Finder – Workflows JS (full, consolidated)
   - Visible placeholders until value is provided
   - Cross-step variable store (carry-over + override)
   - Renders all [data-prompt-template] from data-base
   - Copy prompt (feedback inside button, green state)
   - Insert example values, Share link copy
   - Rating stars (frontend only; localStorage)
   - Smooth scroll to #step-N + focus + active-step highlight
   - Interactive checklists
   ============================================================ */

(function () {
  // ------------------------------
  // Helpers
  // ------------------------------
  const $ = (sel, root = document) => root.querySelector(sel);
  const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));
  const on = (el, evt, fn) => el.addEventListener(evt, fn);
  const normKey = (s) => (s || '').trim().replace(/^{|}$/g, '').toLowerCase();

  // Optional Config aus PHP/Localize
  const CFG = (typeof PF_CONFIG === 'object' && PF_CONFIG) ? PF_CONFIG : {};
  const FLAGS = CFG.feature_flags || {};
  const COPYTXT = (CFG.copy || {});
  const BEHAV = CFG.behavior || {};

  // Cross-step store (lives until reload)
  const VARS = (window.PF_VARS = window.PF_VARS || {});

  // ------------------------------
  // Store & Render
  // ------------------------------

  // Pull current inputs into store (last input wins)
  function syncFromInputs(root = document) {
    $$('input[data-var-name]', root).forEach((inp) => {
      const k = normKey(inp.getAttribute('data-var-name'));
      if (!k) return;
      VARS[k] = inp.value;
    });
  }

  // Push store values into empty inputs (don’t overwrite user typing)
  function fillInputsFromStore(root = document) {
    $$('input[data-var-name]', root).forEach((inp) => {
      const k = normKey(inp.getAttribute('data-var-name'));
      if (!k) return;
      if (!inp.value && VARS[k] != null) {
        inp.value = VARS[k];
        inp.dispatchEvent(new Event('input', { bubbles: true }));
      }
    });
  }

  // Render textareas from pristine template (data-base)
  // Replaces ONLY vars that have a value; leaves {placeholder} visible otherwise.
  function renderPrompts(container = document) {
    const re = /\{([^}]+)\}/g;
    $$('[data-prompt-template]', container).forEach((ta) => {
      const base = ta.getAttribute('data-base') || '';
      const out = base.replace(re, (m, key) => {
        const k = normKey(key);
        const val = Object.prototype.hasOwnProperty.call(VARS, k) ? VARS[k] : '';
        return val ? val : m;
      });
      ta.value = out;
    });
  }

  // Full refresh for a container (inputs -> store, then render prompts)
  function refresh(container = document) {
    syncFromInputs(container);
    renderPrompts(container);
  }

  // ------------------------------
  // Copy helpers
  // ------------------------------
  async function copyText(str) {
    try {
      await navigator.clipboard.writeText(str);
      return true;
    } catch {
      // Fallback
      const ta = document.createElement('textarea');
      ta.value = str;
      ta.style.position = 'fixed';
      ta.style.opacity = '0';
      document.body.appendChild(ta);
      ta.focus();
      ta.select();
      let ok = false;
      try { ok = document.execCommand('copy'); } catch {}
      document.body.removeChild(ta);
      return ok;
    }
  }

  // ------------------------------
  // Rating (frontend only)
  // ------------------------------
function initRatings() {
  if (!FLAGS || !FLAGS.rating) return;

  $$('.pf-rating[data-post-id]').forEach(function (wrap) {
    var postId = wrap.getAttribute('data-post-id');
    if (!postId) return;

    var keyVote  = 'pf_rated_' + postId;     // Sperr-Flag (localStorage / Cookie)
    var keyScore = 'pf_rating_' + postId;    // zuletzt gewählter Wert (nur UI)

    var stars = $$('.pf-star', wrap);
    var avgEl = $('.pf-rating-avg', wrap);
    var cntEl = $('.pf-rating-count', wrap);
    var msgEl = $('.pf-rating-msg', wrap);

    // Vorbelegung aus PHP (Meta)
    var metaAvg = Number(wrap.getAttribute('data-avg') || 0);
    var metaCnt = Number(wrap.getAttribute('data-count') || 0);
    if (avgEl) avgEl.textContent = metaAvg ? metaAvg.toFixed(1) : '–';
    if (cntEl) cntEl.textContent = '(' + metaCnt + ')';

    function paint(val) {
      stars.forEach(function (s) {
        var sv = Number(s.getAttribute('data-value') || '0');
        s.classList.toggle('is-on', sv <= val);
        s.setAttribute('aria-checked', String(sv === val));
      });
    }

    function lockUI() {
      stars.forEach(function (s) {
        s.disabled = true;
        s.classList.add('is-locked');
      });
    }

    // Bereits gevotet? (Cookie oder localStorage)
    var alreadyVoted = (document.cookie.indexOf(keyVote + '=') !== -1) || (localStorage.getItem(keyVote) === '1');
    if (alreadyVoted) {
      lockUI();
      if (msgEl) msgEl.textContent = 'You already rated.';
    }

    // Evtl. früher gewählte Anzeige wiederherstellen
    var saved = Number(localStorage.getItem(keyScore) || 0);
    if (saved) paint(saved);

    on(wrap, 'click', function (e) {
      var btn = e.target.closest('.pf-star');
      if (!btn) return;

      if ((document.cookie.indexOf(keyVote + '=') !== -1) || (localStorage.getItem(keyVote) === '1')) {
        if (msgEl) msgEl.textContent = 'You already rated.';
        return;
      }

      var val = Number(btn.getAttribute('data-value') || '0') || 0;

      // Sofortiges UI-Feedback
      paint(val);
      localStorage.setItem(keyScore, String(val));

      // AJAX an WP
      try {
        var form = new FormData();
        form.append('action', 'pf_rate_workflow');
        form.append('nonce', (window.PF_WORKFLOWS && PF_WORKFLOWS.nonce) ? PF_WORKFLOWS.nonce : '');
        form.append('post_id', postId);
        form.append('rating', String(val));

        var ajaxUrl = (window.PF_WORKFLOWS && PF_WORKFLOWS.ajax_url) ? PF_WORKFLOWS.ajax_url : '/wp-admin/admin-ajax.php';

        fetch(ajaxUrl, {
          method: 'POST',
          credentials: 'same-origin',
          body: form
        })
        .then(function (res) { return res.json(); })
        .then(function (json) {
          if (json && json.success && json.data) {
            var avg = Number(json.data.avg || 0);
            var count = Number(json.data.count || 0);
            if (avgEl) avgEl.textContent = avg ? avg.toFixed(1) : '–';
            if (cntEl) cntEl.textContent = '(' + count + ')';
            if (msgEl) {
              var thanks = (COPYTXT && COPYTXT.rating_thanks) ? COPYTXT.rating_thanks : 'Thanks! ({val}/5)';
              msgEl.textContent = thanks.replace('{val}', String(val));
            }
            // Sperren
            localStorage.setItem(keyVote, '1');
            // komfortables Cookie (24h) – serverseitig wird ohnehin gesperrt
            document.cookie = keyVote + '=1; path=/; max-age=' + (24 * 60 * 60) + '; SameSite=Lax';
            lockUI();
          } else {
            // Bereits bewertet?
            if (json && json.data && (json.message === 'already_rated' || json.data.message === 'already_rated')) {
              var avg2 = Number((json.data.avg || 0));
              var cnt2 = Number((json.data.count || 0));
              if (avgEl) avgEl.textContent = avg2 ? avg2.toFixed(1) : '–';
              if (cntEl) cntEl.textContent = '(' + cnt2 + ')';
              localStorage.setItem(keyVote, '1');
              document.cookie = keyVote + '=1; path=/; max-age=' + (24 * 60 * 60) + '; SameSite=Lax';
              lockUI();
              if (msgEl) msgEl.textContent = 'You already rated.';
            } else {
              if (msgEl) msgEl.textContent = (COPYTXT && COPYTXT.copy_failed) ? COPYTXT.copy_failed : 'Error';
            }
          }
        })
        .catch(function () {
          if (msgEl) msgEl.textContent = (COPYTXT && COPYTXT.copy_failed) ? COPYTXT.copy_failed : 'Error';
        });

      } catch (err) {
        if (msgEl) msgEl.textContent = (COPYTXT && COPYTXT.copy_failed) ? COPYTXT.copy_failed : 'Error';
      }
    });
  });
}



  // ------------------------------
  // Smooth scroll to #step-N
  // ------------------------------
  function initSmoothScroll() {
    on(document, 'click', (e) => {
      const link = e.target.closest('a[href^="#"]');
      if (!link) return;

      const hash = link.getAttribute('href') || '';
      if (!hash.startsWith('#step-')) return;

      const target = document.querySelector(hash);
      if (!target) return;

      e.preventDefault();

      const prefersReduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
      target.scrollIntoView({
        behavior: prefersReduce ? 'auto' : 'smooth',
        block: 'start',
      });

      // After scroll: ensure target step is filled & rendered; then focus
      setTimeout(() => {
        fillInputsFromStore(target);
        refresh(target);
        (target.querySelector('input[data-var-name]') ||
          target.querySelector('[data-prompt-template]'))?.focus({ preventScroll: true });
      }, 200);
    });
  }

  // ------------------------------
  // Insert example values (uses input placeholder)
  // ------------------------------
 /* MVP: Insert Example Values vorerst deaktiviert
function initFillExamples() {
  on(document, 'click', (e) => {
    const btn = e.target.closest('[data-action="fill-examples"]');
    if (!btn) return;

    const step = btn.closest('.pf-step, .pf-step-card') || document;

    $$('input[data-var-name]', step).forEach((inp) => {
      if (!inp.value && inp.placeholder) {
        inp.value = inp.placeholder;
        inp.dispatchEvent(new Event('input', { bubbles: true }));
      }
    });

    refresh(step);
  });
}
*/

  // ------------------------------
  // Copy prompt (feedback inside button, green state)
  // ------------------------------
  function initCopyPrompt() {
    on(document, 'click', async (e) => {
      const btn = e.target.closest('[data-action="copy-prompt"], .pf-copy');
      if (!btn) return;

      const step = btn.closest('.pf-step, .pf-step-card') || document;

      // Ensure fresh render from current inputs (step-local changes included)
      refresh(step);

      const ta = step.querySelector('[data-prompt-template]');
      if (!ta) return;

      const ok = await copyText(ta.value);

      // Default-/Copied-Label bestimmen (aus PF_CONFIG oder Fallback)
      const defaultLabel = btn.dataset.labelDefault
        || btn.textContent.trim()
        || (COPYTXT.copy_prompt || 'Copy prompt');

      const copiedLabel  = COPYTXT.copied_label || 'Copied';
      const failedLabel  = COPYTXT.copy_failed  || 'Copy failed';

      // Default-Label einmalig merken
      btn.dataset.labelDefault = defaultLabel;

      // State setzen
      btn.classList.remove('is-copied', 'is-failed');
      btn.textContent = ok ? copiedLabel : failedLabel;
      btn.classList.add(ok ? 'is-copied' : 'is-failed');
      btn.setAttribute('aria-live', 'polite');

      // Nach kurzer Zeit zurücksetzen
      setTimeout(() => {
        btn.classList.remove('is-copied', 'is-failed');
        btn.textContent = btn.dataset.labelDefault;
      }, 1200);
    });
  }

  // ------------------------------
  // Share link copy
  // ------------------------------
  function initShare() {
    if (!FLAGS.share) return;
    on(document, 'click', async (e) => {
      const btn = e.target.closest('[data-action="copy-link"]');
      if (!btn) return;

      const url = window.location.href;
      await copyText(url);
      // Optional: analog zum Copy-Button Feedback im Button anzeigen
    });
  }

  // ------------------------------
  // Live update store on input (instant cross-step propagation)
  // ------------------------------
  function initLiveVars() {
    on(document, 'input', (e) => {
      const inp = e.target.closest('input[data-var-name]');
      if (!inp) return;
      const key = normKey(inp.getAttribute('data-var-name'));
      if (!key) return;

      // Update store immediately
      VARS[key] = inp.value;

      // Re-render ALL prompts so later steps update in real time
      renderPrompts(document);
    });
  }

  // ------------------------------
  // UX/UI Enhancements: active step, checklist
  // ------------------------------
  function initActiveStep() {
    const steps = Array.from(document.querySelectorAll('.pf-step, .pf-step-card'));
    if (!steps.length || !('IntersectionObserver' in window)) return;

    const io = new IntersectionObserver((entries)=>{
      entries.forEach(entry=>{
        if (entry.isIntersecting) {
          steps.forEach(s => s.classList.remove('is-active'));
          entry.target.classList.add('is-active');
        }
      });
    }, { rootMargin: '-35% 0px -50% 0px', threshold: 0.1 });

    steps.forEach(s => io.observe(s));
  }

  function initChecklist() {
    document.querySelectorAll('.pf-checklist-list').forEach((ul) => {
      ul.querySelectorAll('li').forEach((li) => {
        if (li.dataset.enhanced) return;
        const text = li.textContent.trim();
        li.innerHTML = `<label class="pf-check"><input type="checkbox"><span>${text}</span></label>`;
        li.dataset.enhanced = '1';
      });

      ul.addEventListener('change', (ev) => {
        const box = ev.target.closest('input[type="checkbox"]');
        if (!box) return;
        const li = box.closest('li');
        li.classList.toggle('is-checked', box.checked);
      });
    });
  }

  function activateHashOnLoad() {
    const hash = location.hash || '';
    if (!hash.startsWith('#step-')) return;
    const el = document.querySelector(hash);
    if (el) el.classList.add('is-active');
  }

  // ------------------------------
  // Init
  // ------------------------------
  on(document, 'DOMContentLoaded', () => {
    // On load: fill any empty inputs from store (usually none yet), then full refresh
    fillInputsFromStore(document);
    refresh(document);

    initCopyPrompt();
// initFillExamples(); // MVP: disabled
    initShare();
    initRatings();
    initSmoothScroll();
    initLiveVars();

    // UI niceties
    initActiveStep();
    initChecklist();
    activateHashOnLoad();

    // Optional: Smooth-Scroll offset per CSS var (falls du sie nutzt)
    if (typeof BEHAV.smooth_scroll_offset === 'number') {
      document.documentElement.style.setProperty('--pf-scroll-offset', BEHAV.smooth_scroll_offset + 'px');
    }
  });
})();

/* ================== How-to: Remember preference ================== */
(function(){
  function applyHowtoPrefs() {
    document.querySelectorAll('.pf-howto[data-pref-key]').forEach(el => {
      const key = el.getAttribute('data-pref-key');
      const hidden = localStorage.getItem(key) === '1';
      if (hidden) el.open = false; // eingeklappt lassen
    });
  }

  document.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-action="hide-howto"]');
    if (!btn) return;
    const box = btn.closest('.pf-howto[data-pref-key]');
    if (!box) return;
    const key = box.getAttribute('data-pref-key');
    localStorage.setItem(key, '1'); // nie wieder automatisch öffnen
    box.open = false;
  });

  document.addEventListener('DOMContentLoaded', applyHowtoPrefs);
})();

// ===== Variables UX: Hint merken & Required-Empty markieren =====
(function(){
  function markEmptyRequired(root=document){
    root.querySelectorAll('.pf-var.is-required input[data-var-name]').forEach(inp=>{
      inp.classList.toggle('is-empty', !inp.value.trim());
    });
  }

  document.addEventListener('input', (e)=>{
    const inp = e.target.closest('.pf-var.is-required input[data-var-name]');
    if (inp){ inp.classList.toggle('is-empty', !inp.value.trim()); }
  });

  document.addEventListener('DOMContentLoaded', ()=>{
    // Dismissable hint
    const box = document.querySelector('[data-vars-hint]');
    const key = 'pf_hide_vars_hint';
    if (box){
      if (localStorage.getItem(key) === '1') box.remove();
      document.addEventListener('click', (e)=>{
        const btn = e.target.closest('[data-action="hide-vars-hint"]');
        if (!btn) return;
        localStorage.setItem(key,'1');
        box.remove();
      });
    }
    // Initial required check
    markEmptyRequired();
  });
})();

// Focus beim Start-CTA
document.addEventListener('click', function(e){
  var link = e.target.closest('[data-action="focus-first"]');
  if (!link) return;
  var t = document.querySelector('#step-1');
  if (!t) return;
  setTimeout(function(){
    var first = t.querySelector('input[data-var-name]') || t.querySelector('textarea');
    if (first) first.focus();
  }, 250);
});

(function(){
  document.addEventListener('click', function(e){
    var btn = e.target.closest('.pf-fav-btn');
    if (!btn) return;

    var pid = btn.getAttribute('data-post-id');
    if (!pid) return;

    if (!window.PF_FAVS || !PF_FAVS.logged_in) {
      // nicht eingeloggt → Hinweis
      var t = (PF_FAVS && PF_FAVS.txt_login) ? PF_FAVS.txt_login : 'Please log in';
      btn.classList.add('is-denied');
      btn.querySelector('.pf-fav-label').textContent = t;
      setTimeout(function(){ btn.classList.remove('is-denied'); btn.querySelector('.pf-fav-label').textContent = 'Save to favorites'; }, 1200);
      return;
    }

    var form = new FormData();
    form.append('action', 'pf_toggle_favorite');
    form.append('nonce', PF_FAVS.nonce || '');
    form.append('post_id', pid);

    btn.classList.add('is-busy');

    fetch(PF_FAVS.ajax_url, { method:'POST', credentials:'same-origin', body: form })
      .then(function(r){ return r.json(); })
      .then(function(json){
        btn.classList.remove('is-busy');
        if (json && json.success) {
          var on = !!json.data.added;
          btn.classList.toggle('is-on', on);
			if (on) { btn.classList.add('pf-fav-pop'); setTimeout(function(){ btn.classList.remove('pf-fav-pop'); }, 320); }

          btn.setAttribute('aria-pressed', on ? 'true' : 'false');
          var label = btn.querySelector('.pf-fav-label');
          if (label) label.textContent = on
            ? (PF_FAVS.txt_added || 'Saved to favorites')
            : (PF_FAVS.txt_removed || 'Removed from favorites');
          // kurze Rückmeldung, dann neutrales Label
          setTimeout(function(){
            if (label) label.textContent = on ? 'Saved' : 'Save to favorites';
          }, 1000);
        } else {
          // ggf. zahlender-Zwang / forbidden
          var label = btn.querySelector('.pf-fav-label');
          if (label) label.textContent = (PF_FAVS.txt_denied || 'Not allowed');
          btn.classList.add('is-denied');
          setTimeout(function(){
            btn.classList.remove('is-denied');
            if (label) label.textContent = 'Save to favorites';
          }, 1200);
        }
      })
      .catch(function(){
        btn.classList.remove('is-busy');
      });
  });
})();

/* ============================================================
   PF Workflows – Gating (minimal, additiv)
   - Erkennt .pf-step.is-locked
   - Blockiert Interaktionen im gesperrten Step
   - Leitet je nach Grund auf Login oder Upgrade
   ============================================================ */
(function () {
  function initGating() {
    const root = document.querySelector('.pf-workflow');
    if (!root) return;

    const loginUrl   = root.getAttribute('data-login-url')   || '/wp-login.php';
    const upgradeUrl = root.getAttribute('data-upgrade-url') || '/pricing/';

    // Alle Klicks innerhalb gelockter Steps abfangen
    root.addEventListener('click', function (e) {
      const lockedStep = e.target.closest('.pf-step.is-locked, .pf-step-card.is-locked');
      if (!lockedStep) return;

      // Nur interaktive Ziele abfangen – Links, Buttons, Copy, Toggle etc.
      const interactive = e.target.closest('a, button, [role="button"], .pf-copy, .pf-toggle, .pf-run, textarea, input, summary');
      if (!interactive) return;

      e.preventDefault();
      e.stopPropagation();

      const isProLock = lockedStep.classList.contains('pf-lock--pro');
      const targetUrl = isProLock ? upgradeUrl : loginUrl;

      // Wenn Overlay-CTA vorhanden ist, bevorzugt dieses anklicken lassen (optional)
      const overlayCta = lockedStep.querySelector('.pf-gate-overlay .pf-gate-btn--primary');
      if (overlayCta) {
        // optisches Feedback
        overlayCta.classList.add('is-pressed');
        setTimeout(() => { overlayCta.classList.remove('is-pressed'); }, 180);
      }

      window.location.href = targetUrl;
    }, { capture: true });

    // Zusätzlicher Schutz: Copy-Keyboard-Shortcuts im Locked-Step unterbinden
    root.addEventListener('keydown', function (e) {
      const lockedStep = e.target.closest('.pf-step.is-locked, .pf-step-card.is-locked');
      if (!lockedStep) return;

      const isCopyShortcut =
        ((e.ctrlKey || e.metaKey) && (e.key === 'c' || e.key === 'C')) ||
        (e.key === 'Enter' && e.target && e.target.matches('.pf-copy,[data-action="copy-prompt"]'));

      if (isCopyShortcut) {
        e.preventDefault();
        e.stopPropagation();
        const isProLock = lockedStep.classList.contains('pf-lock--pro');
        window.location.href = isProLock ? upgradeUrl : loginUrl;
      }
    }, true);
  }

  document.addEventListener('DOMContentLoaded', initGating);
})();


