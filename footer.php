<?php
/**
 * Custom Footer for Prompt Finder Child Theme
 * Optimized for performance, accessibility, and SEO
 * 
 * @package PromptFinder
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// Get site information
$site_name = get_bloginfo( 'name' );
$site_description = get_bloginfo( 'description' );
$home_url = esc_url( home_url( '/' ) );
$current_year = date( 'Y' );
?>

        </main><!-- /main-content -->
    </div><!-- /page -->

    <!-- ============================================================
         Site Footer
         ============================================================ -->
    <footer class="pf-footer" role="contentinfo">
        <div class="pf-wrap pf-footer-grid">

            <!-- ============================================================
                 Brand Column
                 ============================================================ -->
            <div class="pf-footer-col pf-footer-col--brand">
                <div class="pf-footer-brand">
                    <?php 
                    try {
                        if ( function_exists( 'the_custom_logo' ) && has_custom_logo() ) {
                            the_custom_logo(); 
                        } else {
                            // Fallback: Site title with proper styling
                            echo '<a href="' . $home_url . '" class="pf-logo pf-logo--footer" aria-label="' . esc_attr( $site_name ) . ' - ' . esc_attr__( 'Home', 'prompt-finder' ) . '">';
                            echo '<span class="pf-logo-text">' . esc_html( $site_name ) . '</span>';
                            echo '</a>';
                        }
                    } catch ( Exception $e ) {
                        error_log( '[PF Footer] Logo error: ' . $e->getMessage() );
                        echo '<a href="' . $home_url . '" class="pf-logo pf-logo--footer">';
                        echo '<span class="pf-logo-text">' . esc_html( $site_name ) . '</span>';
                        echo '</a>';
                    }
                    ?>
                    
                    <p class="pf-footer-description">
                        <?php 
                        if ( $site_description ) {
                            echo esc_html( $site_description );
                        } else {
                            esc_html_e( 'AI Workflows that save you time. Built for professionals.', 'prompt-finder' );
                        }
                        ?>
                    </p>
                </div>
            </div>

            <!-- ============================================================
                 Product Links Column
                 ============================================================ -->
            <div class="pf-footer-col">
                <h3 class="pf-footer-heading"><?php esc_html_e( 'Product', 'prompt-finder' ); ?></h3>
                <nav class="pf-footer-nav" aria-label="<?php esc_attr_e( 'Product navigation', 'prompt-finder' ); ?>">
                    <ul class="pf-footer-list">
                        <li class="pf-footer-item">
                            <a href="<?php echo esc_url( home_url( '/workflows' ) ); ?>" class="pf-footer-link">
                                <?php esc_html_e( 'Workflows', 'prompt-finder' ); ?>
                            </a>
                        </li>
                        <li class="pf-footer-item">
                            <a href="<?php echo esc_url( home_url( '/pricing' ) ); ?>" class="pf-footer-link">
                                <?php esc_html_e( 'Pricing', 'prompt-finder' ); ?>
                            </a>
                        </li>
                        <li class="pf-footer-item">
                            <a href="<?php echo esc_url( home_url( '/faq' ) ); ?>" class="pf-footer-link">
                                <?php esc_html_e( 'FAQ', 'prompt-finder' ); ?>
                            </a>
                        </li>
                        <li class="pf-footer-item">
                            <a href="<?php echo esc_url( home_url( '/features' ) ); ?>" class="pf-footer-link">
                                <?php esc_html_e( 'Features', 'prompt-finder' ); ?>
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>

            <!-- ============================================================
                 Company Links Column
                 ============================================================ -->
            <div class="pf-footer-col">
                <h3 class="pf-footer-heading"><?php esc_html_e( 'Company', 'prompt-finder' ); ?></h3>
                <nav class="pf-footer-nav" aria-label="<?php esc_attr_e( 'Company navigation', 'prompt-finder' ); ?>">
                    <ul class="pf-footer-list">
                        <li class="pf-footer-item">
                            <a href="<?php echo esc_url( home_url( '/about' ) ); ?>" class="pf-footer-link">
                                <?php esc_html_e( 'About', 'prompt-finder' ); ?>
                            </a>
                        </li>
                        <li class="pf-footer-item">
                            <a href="<?php echo esc_url( home_url( '/contact' ) ); ?>" class="pf-footer-link">
                                <?php esc_html_e( 'Contact', 'prompt-finder' ); ?>
                            </a>
                        </li>
                        <li class="pf-footer-item">
                            <a href="<?php echo esc_url( home_url( '/privacy' ) ); ?>" class="pf-footer-link">
                                <?php esc_html_e( 'Privacy Policy', 'prompt-finder' ); ?>
                            </a>
                        </li>
                        <li class="pf-footer-item">
                            <a href="<?php echo esc_url( home_url( '/terms' ) ); ?>" class="pf-footer-link">
                                <?php esc_html_e( 'Terms of Service', 'prompt-finder' ); ?>
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>

            <!-- ============================================================
                 Social Media Column
                 ============================================================ -->
            <div class="pf-footer-col">
                <h3 class="pf-footer-heading"><?php esc_html_e( 'Connect', 'prompt-finder' ); ?></h3>
                <nav class="pf-footer-social" aria-label="<?php esc_attr_e( 'Social media links', 'prompt-finder' ); ?>">
                    <ul class="pf-footer-social-list">
                        <li class="pf-footer-social-item">
                            <a href="https://x.com/promptfinder" 
                               class="pf-footer-social-link" 
                               aria-label="<?php esc_attr_e( 'Follow us on X (formerly Twitter)', 'prompt-finder' ); ?>"
                               target="_blank" 
                               rel="noopener noreferrer">
                                <svg xmlns="http://www.w3.org/2000/svg" 
                                     viewBox="0 0 1200 1227" 
                                     width="20" 
                                     height="20" 
                                     fill="currentColor" 
                                     role="img" 
                                     aria-hidden="true">
                                    <path d="M714.163 519.284L1160.89 0H1050.43L667.137 442.899L356.169 0H0L468.793 681.821L0 1226.96H110.464L515.861 757.435L843.831 1226.96H1200L714.137 519.284H714.163ZM571.03 693.978L521.365 624.523L150.556 80.1173H303.924L600.801 506.345L650.466 575.801L1050.47 1146.84H897.101L571.03 693.978Z"/>
                                </svg>
                                <span class="pf-sr-only"><?php esc_html_e( 'X (formerly Twitter)', 'prompt-finder' ); ?></span>
                            </a>
                        </li>
                        <li class="pf-footer-social-item">
                            <a href="https://linkedin.com/company/promptfinder" 
                               class="pf-footer-social-link" 
                               aria-label="<?php esc_attr_e( 'Follow us on LinkedIn', 'prompt-finder' ); ?>"
                               target="_blank" 
                               rel="noopener noreferrer">
                                <svg xmlns="http://www.w3.org/2000/svg" 
                                     viewBox="0 0 24 24" 
                                     width="20" 
                                     height="20" 
                                     fill="currentColor" 
                                     role="img" 
                                     aria-hidden="true">
                                    <path d="M20.45 20.45h-3.55v-5.4c0-1.29-.02-2.95-1.8-2.95-1.8 0-2.08 1.4-2.08 2.85v5.5H9.47v-11h3.4v1.5h.05c.47-.9 1.62-1.85 3.34-1.85 3.58 0 4.24 2.36 4.24 5.43v5.92zM5.34 8.95a2.06 2.06 0 1 1 0-4.12 2.06 2.06 0 0 1 0 4.12zM7.11 20.45H3.56v-11h3.55v11z"/>
                                </svg>
                                <span class="pf-sr-only"><?php esc_html_e( 'LinkedIn', 'prompt-finder' ); ?></span>
                            </a>
                        </li>
                        <li class="pf-footer-social-item">
                            <a href="https://github.com/promptfinder" 
                               class="pf-footer-social-link" 
                               aria-label="<?php esc_attr_e( 'Follow us on GitHub', 'prompt-finder' ); ?>"
                               target="_blank" 
                               rel="noopener noreferrer">
                                <svg xmlns="http://www.w3.org/2000/svg" 
                                     viewBox="0 0 24 24" 
                                     width="20" 
                                     height="20" 
                                     fill="currentColor" 
                                     role="img" 
                                     aria-hidden="true">
                                    <path d="M12 .3a12 12 0 0 0-3.8 23.4c.6.1.8-.3.8-.6v-2c-3.3.7-4-1.6-4-1.6-.5-1.2-1.2-1.6-1.2-1.6-1-.7.1-.7.1-.7 1.1.1 1.7 1.2 1.7 1.2 1 .1.7 1.8 2.6 1.3.1-.8.4-1.3.7-1.6-2.6-.3-5.4-1.3-5.4-5.9 0-1.3.5-2.4 1.2-3.2-.1-.3-.5-1.5.1-3.1 0 0 1-.3 3.3 1.2a11.5 11.5 0 0 1 6 0c2.3-1.5 3.3-1.2 3.3-1.2.6 1.6.2 2.8.1 3.1.8.9 1.2 2 1.2 3.2 0 4.6-2.8 5.6-5.4 5.9.4.3.8 1 .8 2v3c0 .3.2.7.8.6A12 12 0 0 0 12 .3"/>
                                </svg>
                                <span class="pf-sr-only"><?php esc_html_e( 'GitHub', 'prompt-finder' ); ?></span>
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>

        </div>

        <!-- ============================================================
             Footer Bottom
             ============================================================ -->
        <div class="pf-footer-bottom">
            <div class="pf-wrap">
                <div class="pf-footer-bottom-content">
                    <p class="pf-footer-copyright">
                        © <?php echo esc_html( $current_year ); ?> 
                        <a href="<?php echo $home_url; ?>" class="pf-footer-copyright-link">
                            <?php echo esc_html( $site_name ); ?>
                        </a>. 
                        <?php esc_html_e( 'All rights reserved.', 'prompt-finder' ); ?>
                    </p>
                    
                    <nav class="pf-footer-bottom-nav" aria-label="<?php esc_attr_e( 'Legal navigation', 'prompt-finder' ); ?>">
                        <ul class="pf-footer-bottom-list">
                            <li class="pf-footer-bottom-item">
                                <a href="<?php echo esc_url( home_url( '/privacy' ) ); ?>" class="pf-footer-bottom-link">
                                    <?php esc_html_e( 'Privacy', 'prompt-finder' ); ?>
                                </a>
                            </li>
                            <li class="pf-footer-bottom-item">
                                <a href="<?php echo esc_url( home_url( '/terms' ) ); ?>" class="pf-footer-bottom-link">
                                    <?php esc_html_e( 'Terms', 'prompt-finder' ); ?>
                                </a>
                            </li>
                            <li class="pf-footer-bottom-item">
                                <a href="<?php echo esc_url( home_url( '/cookies' ) ); ?>" class="pf-footer-bottom-link">
                                    <?php esc_html_e( 'Cookies', 'prompt-finder' ); ?>
                                </a>
                            </li>
                        </ul>
                    </nav>
                </div>
            </div>
        </div>
    </footer>

<!-- NUCLEAR OPTION: FORCE LOAD JAVASCRIPT DIRECTLY (BYPASS WORDPRESS ENQUEUE) -->
<?php if (is_singular('workflows') || is_post_type_archive('workflows') || is_tax(['workflow_category','workflow_tag'])): ?>
    <?php 
    $child_theme_uri = get_stylesheet_directory_uri();
    $child_theme_dir = get_stylesheet_directory();
    ?>
    
    <!-- Force load pf-workflows.js directly -->
    <?php if (file_exists($child_theme_dir . '/assets/js/pf-workflows.js')): ?>
    <script id="pf-workflows-js-direct" src="<?php echo $child_theme_uri; ?>/assets/js/pf-workflows.js?v=<?php echo filemtime($child_theme_dir . '/assets/js/pf-workflows.js'); ?>"></script>
    
    <!-- Add required WordPress AJAX data -->
    <script id="pf-workflows-data">
    window.PF_WORKFLOWS = {
        ajax_url: "<?php echo admin_url('admin-ajax.php'); ?>",
        nonce: "<?php echo wp_create_nonce('pf-rate-nonce'); ?>"
    };
    
    <?php 
    $PF_CONFIG = function_exists('pf_load_config') ? pf_load_config() : [];
    ?>
    window.PF_CONFIG = <?php echo wp_json_encode($PF_CONFIG); ?>;
    window.PF_FLAGS = <?php echo wp_json_encode($PF_CONFIG['feature_flags'] ?? []); ?>;
    
    window.PF_FAVS = {
        ajax_url: "<?php echo admin_url('admin-ajax.php'); ?>",
        nonce: "<?php echo wp_create_nonce('pf-fav-nonce'); ?>",
        logged_in: <?php echo is_user_logged_in() ? 'true' : 'false'; ?>,
        txt_added: "Saved to favorites",
        txt_removed: "Removed from favorites",
        txt_login: "Please log in to save favorites",
        txt_denied: "Favorites are for paying users"
    };
    </script>
    <?php endif; ?>
<?php endif; ?>

<?php wp_footer(); ?>
</body>
</html>
