/* ============================================================
   Prompt Finder – Workflows JS (optimized, consolidated)
   - Visible placeholders until value is provided
   - Cross-step variable store (carry-over + override)
   - Renders all [data-prompt-template] from data-base
   - Copy prompt (feedback inside button, green state)
   - Insert example values, Share link copy
   - Rating stars (frontend only; localStorage)
   - Smooth scroll to #step-N + focus + active-step highlight
   - Interactive checklists
   - Error handling and fallbacks
   ============================================================ */

(function () {
  'use strict';

  // ------------------------------
  // Configuration & Constants
  // ------------------------------
  const CONFIG = {
    // Default timeouts and delays
    SCROLL_DELAY: 200,
    FEEDBACK_DURATION: 1200,
    POP_ANIMATION_DURATION: 320,
    
    // Storage keys
    STORAGE_PREFIX: 'pf_',
    RATING_KEY: 'rated_',
    SCORE_KEY: 'rating_',
    HINT_KEY: 'hide_vars_hint',
    
    // CSS classes
    CLASSES: {
      ACTIVE: 'is-active',
      LOCKED: 'is-locked',
      COPIED: 'is-copied',
      FAILED: 'is-failed',
      BUSY: 'is-busy',
      DENIED: 'is-denied',
      ON: 'is-on',
      CHECKED: 'is-checked',
      EMPTY: 'is-empty',
      PRESSED: 'is-pressed',
      ENHANCED: 'enhanced'
    }
  };

  // ------------------------------
  // Helper Functions
  // ------------------------------
  const $ = (sel, root = document) => {
    try {
      return root.querySelector(sel);
    } catch (e) {
      console.warn('[PF] Invalid selector:', sel, e);
      return null;
    }
  };

  const $$ = (sel, root = document) => {
    try {
      return Array.from(root.querySelectorAll(sel));
    } catch (e) {
      console.warn('[PF] Invalid selector:', sel, e);
      return [];
    }
  };

  const on = (el, evt, fn) => {
    if (el && typeof fn === 'function') {
      el.addEventListener(evt, fn);
    }
  };

  const normKey = (s) => {
    if (typeof s !== 'string') return '';
    return s.trim().replace(/^{|}$/g, '').toLowerCase();
  };

  const logError = (context, error) => {
    console.error(`[PF ${context}]`, error);
  };

  // ------------------------------
  // Configuration Loading
  // ------------------------------
  const CFG = (typeof PF_CONFIG === 'object' && PF_CONFIG) ? PF_CONFIG : {};
  const FLAGS = CFG.feature_flags || {};
  const COPYTXT = CFG.copy || {};
  const BEHAV = CFG.behavior || {};

  // Cross-step store (lives until reload)
  const VARS = (window.PF_VARS = window.PF_VARS || {});

  // ------------------------------
  // Store & Render Functions
  // ------------------------------

  /**
   * Pull current inputs into store (last input wins)
   * @param {Element} root - Root element to search within
   */
  function syncFromInputs(root = document) {
    try {
      $$('input[data-var-name]', root).forEach((inp) => {
        const k = normKey(inp.getAttribute('data-var-name'));
        if (!k) return;
        VARS[k] = inp.value || '';
      });
    } catch (e) {
      logError('syncFromInputs', e);
    }
  }

  /**
   * Push store values into empty inputs (don't overwrite user typing)
   * @param {Element} root - Root element to search within
   */
  function fillInputsFromStore(root = document) {
    try {
      $$('input[data-var-name]', root).forEach((inp) => {
        const k = normKey(inp.getAttribute('data-var-name'));
        if (!k) return;
        if (!inp.value && VARS[k] != null) {
          inp.value = VARS[k];
          inp.dispatchEvent(new Event('input', { bubbles: true }));
        }
      });
    } catch (e) {
      logError('fillInputsFromStore', e);
    }
  }

  /**
   * Render textareas from pristine template (data-base)
   * Replaces ONLY vars that have a value; leaves {placeholder} visible otherwise.
   * @param {Element} container - Container element to search within
   */
  function renderPrompts(container = document) {
    try {
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
    } catch (e) {
      logError('renderPrompts', e);
    }
  }

  /**
   * Full refresh for a container (inputs -> store, then render prompts)
   * @param {Element} container - Container element to refresh
   */
  function refresh(container = document) {
    try {
      syncFromInputs(container);
      renderPrompts(container);
    } catch (e) {
      logError('refresh', e);
    }
  }

  // ------------------------------
  // Copy & Clipboard Functions
  // ------------------------------

  /**
   * Copy text to clipboard with fallback
   * @param {string} str - Text to copy
   * @returns {Promise<boolean>} Success status
   */
  async function copyText(str) {
    try {
      if (navigator.clipboard && window.isSecureContext) {
        await navigator.clipboard.writeText(str);
        return true;
      }
      
      // Fallback for older browsers or non-secure contexts
      const ta = document.createElement('textarea');
      ta.value = str;
      ta.style.position = 'fixed';
      ta.style.opacity = '0';
      ta.style.left = '-9999px';
      document.body.appendChild(ta);
      ta.focus();
      ta.select();
      
      let ok = false;
      try {
        ok = document.execCommand('copy');
      } catch (e) {
        logError('copyText fallback', e);
      }
      
      document.body.removeChild(ta);
      return ok;
    } catch (e) {
      logError('copyText', e);
      return false;
    }
  }

  // ------------------------------
  // Rating System
  // ------------------------------

  /**
   * Initialize rating system
   */
  function initRatings() {
    if (!FLAGS || !FLAGS.rating) return;

    try {
      $$('.pf-rating[data-post-id]').forEach(function (wrap) {
        const postId = wrap.getAttribute('data-post-id');
        if (!postId) return;

        const keyVote = CONFIG.STORAGE_PREFIX + CONFIG.RATING_KEY + postId;
        const keyScore = CONFIG.STORAGE_PREFIX + CONFIG.SCORE_KEY + postId;

        const stars = $$('.pf-star', wrap);
        const avgEl = $('.pf-rating-avg', wrap);
        const cntEl = $('.pf-rating-count', wrap);
        const msgEl = $('.pf-rating-msg', wrap);

        // Load initial values from PHP meta
        const metaAvg = Number(wrap.getAttribute('data-avg') || 0);
        const metaCnt = Number(wrap.getAttribute('data-count') || 0);
        if (avgEl) avgEl.textContent = metaAvg ? metaAvg.toFixed(1) : '–';
        if (cntEl) cntEl.textContent = '(' + metaCnt + ')';

        /**
         * Update star display based on rating value
         * @param {number} val - Rating value
         */
        function paint(val) {
          stars.forEach(function (s) {
            const sv = Number(s.getAttribute('data-value') || '0');
            s.classList.toggle(CONFIG.CLASSES.ON, sv <= val);
            s.setAttribute('aria-checked', String(sv === val));
          });
        }

        /**
         * Lock UI after rating
         */
        function lockUI() {
          stars.forEach(function (s) {
            s.disabled = true;
            s.classList.add(CONFIG.CLASSES.LOCKED);
          });
        }

        // Check if already voted
        const alreadyVoted = (document.cookie.indexOf(keyVote + '=') !== -1) || 
                           (localStorage.getItem(keyVote) === '1');
        if (alreadyVoted) {
          lockUI();
          if (msgEl) msgEl.textContent = 'You already rated.';
        }

        // Restore previous rating display
        const saved = Number(localStorage.getItem(keyScore) || 0);
        if (saved) paint(saved);

        on(wrap, 'click', function (e) {
          const btn = e.target.closest('.pf-star');
          if (!btn) return;

          if ((document.cookie.indexOf(keyVote + '=') !== -1) || 
              (localStorage.getItem(keyVote) === '1')) {
            if (msgEl) msgEl.textContent = 'You already rated.';
            return;
          }

          const val = Number(btn.getAttribute('data-value') || '0') || 0;

          // Immediate UI feedback
          paint(val);
          localStorage.setItem(keyScore, String(val));

          // AJAX to WordPress
          try {
            const form = new FormData();
            form.append('action', 'pf_rate_workflow');
            form.append('nonce', (window.PF_WORKFLOWS && PF_WORKFLOWS.nonce) ? PF_WORKFLOWS.nonce : '');
            form.append('post_id', postId);
            form.append('rating', String(val));

            const ajaxUrl = (window.PF_WORKFLOWS && PF_WORKFLOWS.ajax_url) ? 
                           PF_WORKFLOWS.ajax_url : '/wp-admin/admin-ajax.php';

            fetch(ajaxUrl, {
              method: 'POST',
              credentials: 'same-origin',
              body: form
            })
            .then(function (res) { return res.json(); })
            .then(function (json) {
              if (json && json.success && json.data) {
                const avg = Number(json.data.avg || 0);
                const count = Number(json.data.count || 0);
                if (avgEl) avgEl.textContent = avg ? avg.toFixed(1) : '–';
                if (cntEl) cntEl.textContent = '(' + count + ')';
                if (msgEl) {
                  const thanks = (COPYTXT && COPYTXT.rating_thanks) ? 
                               COPYTXT.rating_thanks : 'Thanks! ({val}/5)';
                  msgEl.textContent = thanks.replace('{val}', String(val));
                }
                // Lock after successful rating
                localStorage.setItem(keyVote, '1');
                document.cookie = keyVote + '=1; path=/; max-age=' + (24 * 60 * 60) + '; SameSite=Lax';
                lockUI();
              } else {
                // Handle already rated or other errors
                if (json && json.data && (json.message === 'already_rated' || json.data.message === 'already_rated')) {
                  const avg2 = Number((json.data.avg || 0));
                  const cnt2 = Number((json.data.count || 0));
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
            .catch(function (e) {
              logError('rating AJAX', e);
              if (msgEl) msgEl.textContent = (COPYTXT && COPYTXT.copy_failed) ? COPYTXT.copy_failed : 'Error';
            });

          } catch (err) {
            logError('rating click', err);
            if (msgEl) msgEl.textContent = (COPYTXT && COPYTXT.copy_failed) ? COPYTXT.copy_failed : 'Error';
          }
        });
      });
    } catch (e) {
      logError('initRatings', e);
    }
  }



  // ------------------------------
  // Smooth Scroll & Navigation
  // ------------------------------

  /**
   * Initialize smooth scroll to step anchors
   */
  function initSmoothScroll() {
    try {
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
          const focusTarget = target.querySelector('input[data-var-name]') ||
                             target.querySelector('[data-prompt-template]');
          if (focusTarget) {
            focusTarget.focus({ preventScroll: true });
          }
        }, CONFIG.SCROLL_DELAY);
      });
    } catch (e) {
      logError('initSmoothScroll', e);
    }
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
  // Copy Prompt Functionality
  // ------------------------------

  /**
   * Initialize copy prompt functionality
   */
  function initCopyPrompt() {
    try {
      on(document, 'click', async (e) => {
        const btn = e.target.closest('[data-action="copy-prompt"], .pf-copy');
        if (!btn) return;

        const step = btn.closest('.pf-step, .pf-step-card') || document;

        // Ensure fresh render from current inputs (step-local changes included)
        refresh(step);

        const ta = step.querySelector('[data-prompt-template]');
        if (!ta) return;

        const ok = await copyText(ta.value);

        // Determine labels from config or fallbacks
        const defaultLabel = btn.dataset.labelDefault ||
                           btn.textContent.trim() ||
                           (COPYTXT.copy_prompt || 'Copy prompt');

        const copiedLabel = COPYTXT.copied_label || 'Copied';
        const failedLabel = COPYTXT.copy_failed || 'Copy failed';

        // Store default label for restoration
        btn.dataset.labelDefault = defaultLabel;

        // Update button state
        btn.classList.remove(CONFIG.CLASSES.COPIED, CONFIG.CLASSES.FAILED);
        btn.textContent = ok ? copiedLabel : failedLabel;
        btn.classList.add(ok ? CONFIG.CLASSES.COPIED : CONFIG.CLASSES.FAILED);
        btn.setAttribute('aria-live', 'polite');

        // Reset after delay
        setTimeout(() => {
          btn.classList.remove(CONFIG.CLASSES.COPIED, CONFIG.CLASSES.FAILED);
          btn.textContent = btn.dataset.labelDefault;
        }, CONFIG.FEEDBACK_DURATION);
      });
    } catch (e) {
      logError('initCopyPrompt', e);
    }
  }

  // ------------------------------
  // Share Functionality
  // ------------------------------

  /**
   * Initialize share link functionality
   */
  function initShare() {
    if (!FLAGS.share) return;
    
    try {
      on(document, 'click', async (e) => {
        const btn = e.target.closest('[data-action="copy-link"]');
        if (!btn) return;

        const url = window.location.href;
        await copyText(url);
        // Optional: Add visual feedback similar to copy prompt
      });
    } catch (e) {
      logError('initShare', e);
    }
  }

  // ------------------------------
  // Live Variable Updates
  // ------------------------------

  /**
   * Initialize live variable updates
   */
  function initLiveVars() {
    try {
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
    } catch (e) {
      logError('initLiveVars', e);
    }
  }

  // ------------------------------
  // UX/UI Enhancements
  // ------------------------------

  /**
   * Initialize active step highlighting
   */
  function initActiveStep() {
    try {
      const steps = Array.from(document.querySelectorAll('.pf-step, .pf-step-card'));
      if (!steps.length || !('IntersectionObserver' in window)) return;

      const io = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
          if (entry.isIntersecting) {
            steps.forEach(s => s.classList.remove(CONFIG.CLASSES.ACTIVE));
            entry.target.classList.add(CONFIG.CLASSES.ACTIVE);
          }
        });
      }, { rootMargin: '-35% 0px -50% 0px', threshold: 0.1 });

      steps.forEach(s => io.observe(s));
    } catch (e) {
      logError('initActiveStep', e);
    }
  }

  /**
   * Initialize interactive checklists
   */
  function initChecklist() {
    try {
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
          li.classList.toggle(CONFIG.CLASSES.CHECKED, box.checked);
        });
      });
    } catch (e) {
      logError('initChecklist', e);
    }
  }

  /**
   * Activate hash on page load
   */
  function activateHashOnLoad() {
    try {
      const hash = location.hash || '';
      if (!hash.startsWith('#step-')) return;
      const el = document.querySelector(hash);
      if (el) el.classList.add(CONFIG.CLASSES.ACTIVE);
    } catch (e) {
      logError('activateHashOnLoad', e);
    }
  }

  // ------------------------------
  // Variable Hints & Required Fields
  // ------------------------------

  /**
   * Initialize variable hints and required field marking
   */
  function initVariableHints() {
    try {
      function markEmptyRequired(root = document) {
        $$('.pf-var.is-required input[data-var-name]', root).forEach(inp => {
          inp.classList.toggle(CONFIG.CLASSES.EMPTY, !inp.value.trim());
        });
      }

      on(document, 'input', (e) => {
        const inp = e.target.closest('.pf-var.is-required input[data-var-name]');
        if (inp) {
          inp.classList.toggle(CONFIG.CLASSES.EMPTY, !inp.value.trim());
        }
      });

      on(document, 'DOMContentLoaded', () => {
        // Dismissable hint
        const box = document.querySelector('[data-vars-hint]');
        const key = CONFIG.STORAGE_PREFIX + CONFIG.HINT_KEY;
        if (box) {
          if (localStorage.getItem(key) === '1') box.remove();
          on(document, 'click', (e) => {
            const btn = e.target.closest('[data-action="hide-vars-hint"]');
            if (!btn) return;
            localStorage.setItem(key, '1');
            box.remove();
          });
        }
        // Initial required check
        markEmptyRequired();
      });
    } catch (e) {
      logError('initVariableHints', e);
    }
  }

  // ------------------------------
  // How-to Preferences
  // ------------------------------

  /**
   * Initialize how-to preferences
   */
  function initHowtoPrefs() {
    try {
      function applyHowtoPrefs() {
        $$('.pf-howto[data-pref-key]').forEach(el => {
          const key = el.getAttribute('data-pref-key');
          const hidden = localStorage.getItem(key) === '1';
          if (hidden) el.open = false; // Keep collapsed
        });
      }

      on(document, 'click', (e) => {
        const btn = e.target.closest('[data-action="hide-howto"]');
        if (!btn) return;
        const box = btn.closest('.pf-howto[data-pref-key]');
        if (!box) return;
        const key = box.getAttribute('data-pref-key');
        localStorage.setItem(key, '1'); // Never auto-open again
        box.open = false;
      });

      on(document, 'DOMContentLoaded', applyHowtoPrefs);
    } catch (e) {
      logError('initHowtoPrefs', e);
    }
  }

  // ------------------------------
  // Focus Management
  // ------------------------------

  /**
   * Initialize focus management for start CTA
   */
  function initFocusManagement() {
    try {
      on(document, 'click', function(e) {
        const link = e.target.closest('[data-action="focus-first"]');
        if (!link) return;
        const t = document.querySelector('#step-1');
        if (!t) return;
        setTimeout(function() {
          const first = t.querySelector('input[data-var-name]') || t.querySelector('textarea');
          if (first) first.focus();
        }, 250);
      });
    } catch (e) {
      logError('initFocusManagement', e);
    }
  }

  // ------------------------------
  // Checkpoint Management
  // ------------------------------

  /**
   * Initialize checkpoint functionality
   */
  function initCheckpoints() {
    try {
      on(document, 'click', (e) => {
        const btn = e.target.closest('[data-action="continue-checkpoint"]');
        if (!btn) return;

        const checkpoint = btn.closest('[data-checkpoint="true"]');
        if (!checkpoint) return;

        const step = checkpoint.closest('.pf-step, .pf-step-card');
        if (!step) return;

        // Hide checkpoint and enable next step
        checkpoint.style.display = 'none';
        step.classList.remove('pf-step--checkpoint');
        
        // Enable next step if it exists
        const nextStep = step.nextElementSibling;
        if (nextStep && nextStep.classList.contains('pf-step')) {
          nextStep.classList.remove('pf-step--locked');
          const nextBlur = nextStep.querySelector('.pf-blur');
          if (nextBlur) {
            nextBlur.classList.remove('pf-blur');
          }
        }

        // Store checkpoint completion
        const stepId = step.id || 'unknown';
        localStorage.setItem(CONFIG.STORAGE_PREFIX + 'checkpoint_' + stepId, '1');
      });
    } catch (e) {
      logError('initCheckpoints', e);
    }
  }

  /**
   * Check and restore checkpoint states on page load
   */
  function restoreCheckpointStates() {
    try {
      $$('.pf-step--checkpoint').forEach(step => {
        const stepId = step.id || 'unknown';
        const completed = localStorage.getItem(CONFIG.STORAGE_PREFIX + 'checkpoint_' + stepId) === '1';
        
        if (completed) {
          const checkpoint = step.querySelector('[data-checkpoint="true"]');
          if (checkpoint) {
            checkpoint.style.display = 'none';
          }
          step.classList.remove('pf-step--checkpoint');
          
          // Enable next step
          const nextStep = step.nextElementSibling;
          if (nextStep && nextStep.classList.contains('pf-step')) {
            nextStep.classList.remove('pf-step--locked');
            const nextBlur = nextStep.querySelector('.pf-blur');
            if (nextBlur) {
              nextBlur.classList.remove('pf-blur');
            }
          }
        }
      });
    } catch (e) {
      logError('restoreCheckpointStates', e);
    }
  }


  // ------------------------------
  // Main Initialization
  // ------------------------------

  /**
   * Initialize all functionality
   */
  function init() {
    try {
      // On load: fill any empty inputs from store, then full refresh
      fillInputsFromStore(document);
      refresh(document);

      // Initialize all modules
      initCopyPrompt();
      initShare();
      initRatings();
      initSmoothScroll();
      initLiveVars();
      initFavorites();
      initGating();
      initVariableHints();
      initHowtoPrefs();
      initFocusManagement();
      initCheckpoints();

      // UI enhancements
      initActiveStep();
      initChecklist();
      activateHashOnLoad();
      restoreCheckpointStates();

      // Optional: Smooth-scroll offset per CSS var
      if (typeof BEHAV.smooth_scroll_offset === 'number') {
        document.documentElement.style.setProperty('--pf-scroll-offset', BEHAV.smooth_scroll_offset + 'px');
      }

      console.log('[PF] Workflows JS initialized successfully');
    } catch (e) {
      logError('main init', e);
    }
  }

  // ------------------------------
  // Event Listeners
  // ------------------------------
  on(document, 'DOMContentLoaded', init);

})();
