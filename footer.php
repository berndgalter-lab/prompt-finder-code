<?php
/**
 * Custom Footer for Prompt Finder Child
 */

if ( ! defined( 'ABSPATH' ) ) {
  exit; // Exit if accessed directly.
}
?>

  </div><!-- /site-content -->
</div><!-- /page -->

<!-- ===== Custom Footer ===== -->
<footer class="pf-footer">
  <div class="pf-wrap pf-footer-grid">

    <!-- Column 1: Brand -->
    <div class="pf-footer-col">
      <?php if ( function_exists('the_custom_logo') && has_custom_logo() ) : ?>
        <?php the_custom_logo(); ?>
      <?php else : ?>
        <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="pf-logo"><?php bloginfo('name'); ?></a>
      <?php endif; ?>
      <p class="pf-sub">AI Workflows that save you time.<br>Built for professionals.</p>
    </div>

    <!-- Column 2: Links -->
    <div class="pf-footer-col">
      <h4>Product</h4>
      <ul>
        <li><a href="/workflows">Workflows</a></li>
        <li><a href="/pricing">Pricing</a></li>
        <li><a href="/faq">FAQ</a></li>
      </ul>
    </div>

    <!-- Column 3: Company -->
    <div class="pf-footer-col">
      <h4>Company</h4>
      <ul>
        <li><a href="/about">About</a></li>
        <li><a href="/contact">Contact</a></li>
        <li><a href="/privacy">Privacy</a></li>
      </ul>
    </div>

    <!-- Column 4: Social -->
    <div class="pf-footer-col">
      <h4>Connect</h4>
      <div class="pf-footer-social">
        <!-- X (ehem. Twitter) -->
        <a href="#" aria-label="X">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 1227" width="18" height="18" fill="currentColor" role="img" focusable="false">
            <path d="M714.163 519.284L1160.89 0H1050.43L667.137 442.899L356.169 0H0L468.793 681.821L0 1226.96H110.464L515.861 757.435L843.831 1226.96H1200L714.137 519.284H714.163ZM571.03 693.978L521.365 624.523L150.556 80.1173H303.924L600.801 506.345L650.466 575.801L1050.47 1146.84H897.101L571.03 693.978Z"/>
          </svg>
        </a>
        <!-- LinkedIn -->
        <a href="#" aria-label="LinkedIn">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" fill="currentColor" role="img" focusable="false">
            <path d="M20.45 20.45h-3.55v-5.4c0-1.29-.02-2.95-1.8-2.95-1.8 0-2.08 1.4-2.08 2.85v5.5H9.47v-11h3.4v1.5h.05c.47-.9 1.62-1.85 3.34-1.85 3.58 0 4.24 2.36 4.24 5.43v5.92zM5.34 8.95a2.06 2.06 0 1 1 0-4.12 2.06 2.06 0 0 1 0 4.12zM7.11 20.45H3.56v-11h3.55v11z"/>
          </svg>
        </a>
        <!-- GitHub -->
        <a href="#" aria-label="GitHub">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" fill="currentColor" role="img" focusable="false">
            <path d="M12 .3a12 12 0 0 0-3.8 23.4c.6.1.8-.3.8-.6v-2c-3.3.7-4-1.6-4-1.6-.5-1.2-1.2-1.6-1.2-1.6-1-.7.1-.7.1-.7 1.1.1 1.7 1.2 1.7 1.2 1 .1.7 1.8 2.6 1.3.1-.8.4-1.3.7-1.6-2.6-.3-5.4-1.3-5.4-5.9 0-1.3.5-2.4 1.2-3.2-.1-.3-.5-1.5.1-3.1 0 0 1-.3 3.3 1.2a11.5 11.5 0 0 1 6 0c2.3-1.5 3.3-1.2 3.3-1.2.6 1.6.2 2.8.1 3.1.8.9 1.2 2 1.2 3.2 0 4.6-2.8 5.6-5.4 5.9.4.3.8 1 .8 2v3c0 .3.2.7.8.6A12 12 0 0 0 12 .3"/>
          </svg>
        </a>
      </div>
    </div>

  </div>

  <div class="pf-footer-bottom">
    <p>© <?php echo date('Y'); ?> <?php bloginfo('name'); ?>. All rights reserved.</p>
  </div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
