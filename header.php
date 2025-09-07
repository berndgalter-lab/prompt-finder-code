<?php
/**
 * Custom Header for Prompt Finder Child Theme
 * Optimized for performance, accessibility, and SEO
 * 
 * @package PromptFinder
 * @version 1.0.0
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Get site information
$site_name = get_bloginfo( 'name' );
$site_description = get_bloginfo( 'description' );
$home_url = esc_url( home_url( '/' ) );
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    
    <!-- SEO Meta Tags -->
    <meta name="description" content="<?php echo esc_attr( $site_description ); ?>">
    <meta name="author" content="<?php echo esc_attr( $site_name ); ?>">
    <meta name="robots" content="index, follow">
    
    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?php echo esc_url( home_url( $_SERVER['REQUEST_URI'] ?? '/' ) ); ?>">
    <meta property="og:title" content="<?php echo esc_attr( $site_name ); ?>">
    <meta property="og:description" content="<?php echo esc_attr( $site_description ); ?>">
    <meta property="og:site_name" content="<?php echo esc_attr( $site_name ); ?>">
    
    <!-- Twitter -->
    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:url" content="<?php echo esc_url( home_url( $_SERVER['REQUEST_URI'] ?? '/' ) ); ?>">
    <meta property="twitter:title" content="<?php echo esc_attr( $site_name ); ?>">
    <meta property="twitter:description" content="<?php echo esc_attr( $site_description ); ?>">
    
    <!-- NO PRELOADS - Direct loading only -->
    
    <!-- DNS Prefetch for external resources -->
    <link rel="dns-prefetch" href="//fonts.googleapis.com">
    <link rel="dns-prefetch" href="//cdnjs.cloudflare.com">
    
    <?php wp_head(); ?>
</head>

<body <?php body_class( 'pf-body' ); ?> <?php generate_do_microdata( 'body' ); ?>>

    <?php
    // Required hook for plugins/tracking etc.
    do_action( 'wp_body_open' );
    ?>

    <!-- Skip to main content for accessibility -->
    <a class="pf-skip-link pf-sr-only" href="#main-content">
        <?php esc_html_e( 'Skip to main content', 'prompt-finder' ); ?>
    </a>

    <!-- ============================================================
         Site Header
         ============================================================ -->
    <header class="pf-header-site" role="banner">
        <div class="pf-wrap">
            <!-- Logo Section -->
            <div class="pf-logo">
                <a href="<?php echo $home_url; ?>" aria-label="<?php echo esc_attr( $site_name ); ?> - <?php esc_attr_e( 'Home', 'prompt-finder' ); ?>">
                    <?php 
                    try {
                        if ( function_exists( 'the_custom_logo' ) && has_custom_logo() ) {
                            the_custom_logo(); 
                        } else {
                            // Fallback: Site title with proper styling
                            echo '<span class="pf-logo-text">' . esc_html( $site_name ) . '</span>';
                        }
                    } catch ( Exception $e ) {
                        error_log( '[PF Header] Logo error: ' . $e->getMessage() );
                        echo '<span class="pf-logo-text">' . esc_html( $site_name ) . '</span>';
                    }
                    ?>
                </a>
            </div>

            <!-- Desktop Navigation -->
            <nav class="pf-nav pf-nav--desktop" role="navigation" aria-label="<?php esc_attr_e( 'Main navigation', 'prompt-finder' ); ?>">
                <ul class="pf-nav-list">
                    <li class="pf-nav-item">
                        <a href="<?php echo esc_url( home_url( '/workflows' ) ); ?>" class="pf-nav-link">
                            <?php esc_html_e( 'Workflows', 'prompt-finder' ); ?>
                        </a>
                    </li>
                    <li class="pf-nav-item">
                        <a href="<?php echo esc_url( home_url( '/pricing' ) ); ?>" class="pf-nav-link">
                            <?php esc_html_e( 'Pricing', 'prompt-finder' ); ?>
                        </a>
                    </li>
                    <li class="pf-nav-item">
                        <a href="<?php echo esc_url( home_url( '/about' ) ); ?>" class="pf-nav-link">
                            <?php esc_html_e( 'About', 'prompt-finder' ); ?>
                        </a>
                    </li>
                    <li class="pf-nav-item pf-nav-item--cta">
                        <a href="<?php echo esc_url( home_url( '/contact' ) ); ?>" class="pf-btn pf-btn--primary">
                            <?php esc_html_e( 'Get Started', 'prompt-finder' ); ?>
                        </a>
                    </li>
                </ul>
            </nav>

            <!-- Mobile Navigation Toggle -->
            <button class="pf-nav-toggle pf-nav-toggle--mobile" 
                    aria-label="<?php esc_attr_e( 'Toggle mobile menu', 'prompt-finder' ); ?>"
                    aria-expanded="false"
                    aria-controls="mobile-navigation">
                <span class="pf-nav-toggle-icon">
                    <span class="pf-nav-toggle-line"></span>
                    <span class="pf-nav-toggle-line"></span>
                    <span class="pf-nav-toggle-line"></span>
                </span>
            </button>

            <!-- Mobile Navigation -->
            <nav class="pf-nav pf-nav--mobile" id="mobile-navigation" role="navigation" aria-label="<?php esc_attr_e( 'Mobile navigation', 'prompt-finder' ); ?>" aria-hidden="true">
                <ul class="pf-nav-list pf-nav-list--mobile">
                    <li class="pf-nav-item">
                        <a href="<?php echo esc_url( home_url( '/workflows' ) ); ?>" class="pf-nav-link">
                            <?php esc_html_e( 'Workflows', 'prompt-finder' ); ?>
                        </a>
                    </li>
                    <li class="pf-nav-item">
                        <a href="<?php echo esc_url( home_url( '/pricing' ) ); ?>" class="pf-nav-link">
                            <?php esc_html_e( 'Pricing', 'prompt-finder' ); ?>
                        </a>
                    </li>
                    <li class="pf-nav-item">
                        <a href="<?php echo esc_url( home_url( '/about' ) ); ?>" class="pf-nav-link">
                            <?php esc_html_e( 'About', 'prompt-finder' ); ?>
                        </a>
                    </li>
                    <li class="pf-nav-item pf-nav-item--cta">
                        <a href="<?php echo esc_url( home_url( '/contact' ) ); ?>" class="pf-btn pf-btn--primary pf-btn--full">
                            <?php esc_html_e( 'Get Started', 'prompt-finder' ); ?>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
    </header>

    <!-- ============================================================
         Main Content Area
         ============================================================ -->
    <div <?php generate_do_attr( 'page' ); ?>>
        <?php
        // Optional: Custom hook point for future injections
        do_action( 'generate_inside_site_container' );
        ?>
        
        <main id="main-content" <?php generate_do_attr( 'site-content' ); ?> role="main">
            <?php
            // Maintain compatibility with GeneratePress layout
            do_action( 'generate_inside_container' );
            ?>

