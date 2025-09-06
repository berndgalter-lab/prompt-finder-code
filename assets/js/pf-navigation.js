/* ============================================================
   Prompt Finder – Mobile Navigation
   Handles hamburger menu functionality for mobile devices
   ============================================================ */

(function () {
  'use strict';

  // ------------------------------
  // Configuration & Constants
  // ------------------------------
  const CONFIG = {
    BREAKPOINT_MOBILE: 768,
    TRANSITION_DURATION: 250,
    FOCUS_DELAY: 100
  };

  // ------------------------------
  // Helper Functions
  // ------------------------------
  const $ = (sel, root = document) => {
    try {
      return root.querySelector(sel);
    } catch (e) {
      console.warn('[PF Navigation] Invalid selector:', sel, e);
      return null;
    }
  };

  const on = (el, evt, fn) => {
    if (el && typeof fn === 'function') {
      el.addEventListener(evt, fn);
    }
  };

  const logError = (context, error) => {
    console.error(`[PF Navigation ${context}]`, error);
  };

  // ------------------------------
  // Mobile Navigation Functions
  // ------------------------------

  /**
   * Initialize mobile navigation functionality
   */
  function initMobileNavigation() {
    try {
      const toggle = $('.pf-nav-toggle--mobile');
      const mobileNav = $('.pf-nav--mobile');
      
      if (!toggle || !mobileNav) {
        console.log('[PF Navigation] Mobile navigation elements not found');
        return;
      }

      // Create overlay element
      const overlay = document.createElement('div');
      overlay.className = 'pf-nav-overlay';
      document.body.appendChild(overlay);

      /**
       * Toggle mobile navigation
       */
      function toggleMobileNav() {
        const isOpen = mobileNav.classList.contains('is-open');
        
        if (isOpen) {
          closeMobileNav();
        } else {
          openMobileNav();
        }
      }

      /**
       * Open mobile navigation
       */
      function openMobileNav() {
        toggle.classList.add('is-active');
        toggle.setAttribute('aria-expanded', 'true');
        mobileNav.classList.add('is-open');
        mobileNav.setAttribute('aria-hidden', 'false');
        overlay.classList.add('is-active');
        document.body.style.overflow = 'hidden';
        
        // Focus first link for accessibility
        const firstLink = mobileNav.querySelector('.pf-nav-link');
        if (firstLink) {
          setTimeout(() => firstLink.focus(), CONFIG.FOCUS_DELAY);
        }
      }

      /**
       * Close mobile navigation
       */
      function closeMobileNav() {
        toggle.classList.remove('is-active');
        toggle.setAttribute('aria-expanded', 'false');
        mobileNav.classList.remove('is-open');
        mobileNav.setAttribute('aria-hidden', 'true');
        overlay.classList.remove('is-active');
        document.body.style.overflow = '';
      }

      /**
       * Check if mobile navigation should be closed
       */
      function shouldCloseOnResize() {
        return window.innerWidth > CONFIG.BREAKPOINT_MOBILE && 
               mobileNav.classList.contains('is-open');
      }

      // Event Listeners
      on(toggle, 'click', toggleMobileNav);
      on(overlay, 'click', closeMobileNav);

      // Close on escape key
      on(document, 'keydown', function(e) {
        if (e.key === 'Escape' && mobileNav.classList.contains('is-open')) {
          closeMobileNav();
        }
      });

      // Close on window resize (if desktop)
      on(window, 'resize', function() {
        if (shouldCloseOnResize()) {
          closeMobileNav();
        }
      });

      // Close on navigation link click
      on(mobileNav, 'click', function(e) {
        const link = e.target.closest('.pf-nav-link');
        if (link) {
          closeMobileNav();
        }
      });

      console.log('[PF Navigation] Mobile navigation initialized successfully');

    } catch (e) {
      logError('initMobileNavigation', e);
    }
  }

  // ------------------------------
  // Desktop Navigation Enhancements
  // ------------------------------

  /**
   * Initialize desktop navigation enhancements
   */
  function initDesktopNavigation() {
    try {
      const desktopNav = $('.pf-nav--desktop');
      if (!desktopNav) return;

      // Add hover effects and keyboard navigation
      const navLinks = desktopNav.querySelectorAll('.pf-nav-link');
      
      navLinks.forEach(link => {
        // Enhanced keyboard navigation
        on(link, 'keydown', function(e) {
          if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            link.click();
          }
        });
      });

      console.log('[PF Navigation] Desktop navigation enhanced');

    } catch (e) {
      logError('initDesktopNavigation', e);
    }
  }

  // ------------------------------
  // Responsive Navigation Management
  // ------------------------------

  /**
   * Handle responsive navigation behavior
   */
  function initResponsiveNavigation() {
    try {
      function handleResize() {
        const isMobile = window.innerWidth <= CONFIG.BREAKPOINT_MOBILE;
        const mobileNav = $('.pf-nav--mobile');
        const desktopNav = $('.pf-nav--desktop');
        
        if (isMobile) {
          // Mobile view
          if (desktopNav) desktopNav.style.display = 'none';
          if (mobileNav) mobileNav.style.display = 'block';
        } else {
          // Desktop view
          if (desktopNav) desktopNav.style.display = 'block';
          if (mobileNav) {
            mobileNav.style.display = 'none';
            // Close mobile nav if open
            if (mobileNav.classList.contains('is-open')) {
              mobileNav.classList.remove('is-open');
              mobileNav.setAttribute('aria-hidden', 'true');
              const toggle = $('.pf-nav-toggle--mobile');
              if (toggle) {
                toggle.classList.remove('is-active');
                toggle.setAttribute('aria-expanded', 'false');
              }
            }
          }
        }
      }

      // Initial call
      handleResize();

      // Listen for resize events
      on(window, 'resize', handleResize);

      console.log('[PF Navigation] Responsive navigation initialized');

    } catch (e) {
      logError('initResponsiveNavigation', e);
    }
  }

  // ------------------------------
  // Main Initialization
  // ------------------------------

  /**
   * Initialize all navigation functionality
   */
  function init() {
    try {
      initMobileNavigation();
      initDesktopNavigation();
      initResponsiveNavigation();
      
      console.log('[PF Navigation] All navigation features initialized successfully');
    } catch (e) {
      logError('main init', e);
    }
  }

  // ------------------------------
  // Event Listeners
  // ------------------------------
  on(document, 'DOMContentLoaded', init);

})();
