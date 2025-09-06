<?php
/**
 * Custom Header for Prompt Finder Child
 */
if ( ! defined( 'ABSPATH' ) ) exit;
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
  <meta charset="<?php bloginfo( 'charset' ); ?>">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php wp_head(); ?>
</head>

<body <?php body_class(); ?> <?php generate_do_microdata( 'body' ); ?>>

  <?php
  // Pflicht-Hook (Plugins/Tracking etc.)
  do_action( 'wp_body_open' );
  ?>

  <!-- ===== Custom Header ===== -->
<header class="pf-header-site">
  <div class="pf-wrap">
    <div class="pf-logo">
      <a href="<?php echo esc_url( home_url( '/' ) ); ?>">
        <?php 
          if ( function_exists( 'the_custom_logo' ) && has_custom_logo() ) {
              the_custom_logo(); 
          } else {
              // Fallback: Seitentitel
              bloginfo( 'name' );
          }
        ?>
      </a>
    </div>
    <nav class="pf-nav">
      <a href="/workflows">Workflows</a>
      <a href="/pricing">Pricing</a>
      <a href="/about">About</a>
      <a href="/contact" class="pf-btn pf-btn--primary">Get Started</a>
    </nav>
  </div>
</header>

  <div <?php generate_do_attr( 'page' ); ?>>
    <?php
    // Optional: eigener Hookpunkt falls du später etwas injizieren willst
    do_action( 'generate_inside_site_container' );
    ?>
    <div <?php generate_do_attr( 'site-content' ); ?>>
      <?php
      // Beibehalten – sorgt für kompatibles Innen-Layout
      do_action( 'generate_inside_container' );
      ?>

