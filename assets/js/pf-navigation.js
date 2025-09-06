/* ============================================================
   Prompt Finder – Navigation JS
   Handles header navigation functionality
   ============================================================ */

(function () {
  'use strict';

  // ------------------------------
  // Helper Functions
  // ------------------------------
  const $ = (sel, root = document) => {
    try {
      return root.querySelector(sel);
    } catch (e) {
      console.warn('[PF Nav] Invalid selector:', sel, e);
      return null;
    }
  };

  const on = (el, evt, fn) => {
    if (el && typeof fn === 'function') {
      el.addEventListener(evt, fn);
    }
  };

  const logError = (context, error) => {
    console.error(`[PF Nav ${context}]`, error);
  };

  // ------------------------------
  // Mobile Navigation
  // ------------------------------

  /**
   * Initialize mobile navigation toggle
   */
  function initMobileNav() {
    try {
      const toggle = $('.pf-nav-toggle--mobile');
      const mobileNav = $('.pf-nav--mobile');
      
      if (!toggle || !mobileNav) return;

      on(toggle, 'click', () => {
        const isExpanded = toggle.getAttribute('aria-expanded') === 'true';
        const newExpanded = !isExpanded;
        
        toggle.setAttribute('aria-expanded', String(newExpanded));
        mobileNav.setAttribute('aria-hidden', String(!newExpanded));
        
        // Add/remove active class for styling
        toggle.classList.toggle('is-active', newExpanded);
        mobileNav.classList.toggle('is-open', newExpanded);
      });

      // Close mobile nav when clicking outside
      on(document, 'click', (e) => {
        if (!mobileNav.contains(e.target) && !toggle.contains(e.target)) {
          toggle.setAttribute('aria-expanded', 'false');
          mobileNav.setAttribute('aria-hidden', 'true');
          toggle.classList.remove('is-active');
          mobileNav.classList.remove('is-open');
        }
      });

      // Close mobile nav on escape key
      on(document, 'keydown', (e) => {
        if (e.key === 'Escape') {
          toggle.setAttribute('aria-expanded', 'false');
          mobileNav.setAttribute('aria-hidden', 'true');
          toggle.classList.remove('is-active');
          mobileNav.classList.remove('is-open');
        }
      });
    } catch (e) {
      logError('initMobileNav', e);
    }
  }

  // ------------------------------
  // Main Initialization
  // ------------------------------

  /**
   * Initialize navigation functionality
   */
  function init() {
    try {
      initMobileNav();
      console.log('[PF Nav] Navigation JS initialized successfully');
    } catch (e) {
      logError('main init', e);
    }
  }

  // ------------------------------
  // Event Listeners
  // ------------------------------
  on(document, 'DOMContentLoaded', init);

})();