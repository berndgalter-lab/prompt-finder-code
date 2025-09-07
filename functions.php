<?php
// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

/* =====================================================
   Constants
===================================================== */

// Rating constants
define('PF_MIN_RATING', 1);
define('PF_MAX_RATING', 5);
define('PF_DEFAULT_FREE_STEPS', 1);

// Rate limiting constants
define('PF_RATE_LIMIT_DURATION', 60); // seconds
define('PF_FAV_LIMIT_DURATION', 60); // seconds

// Cache constants
define('PF_CACHE_DURATION', 3600); // 1 hour

/* =====================================================
   Helper Functions
===================================================== */

/**
 * Load PF configuration from JSON file
 * 
 * @since 1.0.0
 * @return array Configuration array
 */
function pf_load_config(): array {
    static $config = null;
    
    if ($config === null) {
        $cfg_file = get_stylesheet_directory() . '/assets/pf-config.json';
        $config = [];
        
        if (file_exists($cfg_file)) {
            $json = file_get_contents($cfg_file);
            // Remove BOM if present
            $json = preg_replace('/^\xEF\xBB\xBF/', '', $json);
            $tmp = json_decode($json, true);
            if (is_array($tmp)) {
                $config = $tmp;
            }
        }
    }
    
    return $config;
}

/**
 * Get user's current plan
 * 
 * @since 1.0.0
 * @return string User plan ('guest', 'free', 'pro')
 */
function pf_get_user_plan(): string {
    if (current_user_can('pf_pro')) return 'pro';
    if (is_user_logged_in()) {
        $plan = get_user_meta(get_current_user_id(), 'pf_plan', true);
        return is_string($plan) && $plan ? strtolower($plan) : 'free';
    }
    return 'guest';
}

/**
 * Check if user has access based on gating rules
 * 
 * @since 1.0.0
 * @param array $gating Gating configuration
 * @return bool True if user has access
 */
function pf_user_has_access(array $gating): bool {
    // Login-Pflicht?
    if (!empty($gating['login_required']) && !is_user_logged_in()) return false;
    
    // Capability/Tier check
    if (!empty($gating['required_cap']) && !current_user_can($gating['required_cap'])) return false;
    
    return true;
}

/**
 * Enqueue asset with optimized versioning
 * 
 * @since 1.0.0
 * @param string $handle Asset handle
 * @param string $src Asset URL
 * @param array $deps Dependencies
 * @param string $type Asset type ('style' or 'script')
 * @param bool $in_footer For scripts only
 * @return void
 */
function pf_enqueue_asset(string $handle, string $src, array $deps = [], string $type = 'style', bool $in_footer = false): void {
    $file_path = get_stylesheet_directory() . str_replace(get_stylesheet_directory_uri(), '', $src);
    
    if (file_exists($file_path)) {
        $version = (function_exists('wp_get_environment_type') && (function_exists('wp_get_environment_type') && wp_get_environment_type() === 'production')) 
            ? wp_get_theme()->get('Version') 
            : filemtime($file_path);
            
        if ($type === 'style') {
            wp_enqueue_style($handle, $src, $deps, $version);
        } else {
            wp_enqueue_script($handle, $src, $deps, $version, $in_footer);
        }
    }
}

/**
 * Get client IP address with proxy support
 * 
 * @since 1.0.0
 * @return string Client IP address
 */
function pf_get_client_ip(): string {
    $ip_keys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
    
    foreach ($ip_keys as $key) {
        if (!empty($_SERVER[$key])) {
            $ip = $_SERVER[$key];
            
            // Handle comma-separated IPs (from proxies)
            if (strpos($ip, ',') !== false) {
                $ip = trim(explode(',', $ip)[0]);
            }
            
            // Validate IP
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }
    }
    
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/* =====================================================
   Child Theme Basics
===================================================== */

// Locale Styles (Parent RTL Support)
if ( !function_exists( 'chld_thm_cfg_locale_css' ) ):
    function chld_thm_cfg_locale_css( $uri ){
        if ( empty( $uri ) && is_rtl() && file_exists( get_template_directory() . '/rtl.css' ) ) {
            $uri = get_template_directory_uri() . '/rtl.css';
        }
        return $uri;
    }
endif;
add_filter( 'locale_stylesheet_uri', 'chld_thm_cfg_locale_css' );

/* =====================================================
   ACF JSON Sync & Global Context
===================================================== */

/**
 * Set ACF JSON save point to theme directory
 */
add_filter('acf/settings/save_json', function($path) {
    return get_stylesheet_directory() . '/acf-json';
});

/**
 * Set ACF JSON load point to theme directory
 */
add_filter('acf/settings/load_json', function($paths) {
    $paths[] = get_stylesheet_directory() . '/acf-json';
    return $paths;
});

/**
 * Add Global Context options page
 */
if (function_exists('acf_add_options_page')) {
    acf_add_options_page([
        'page_title' => 'Global Context',
        'menu_title' => 'Global Context',
        'menu_slug'  => 'acf-options-global-context',
        'capability' => 'manage_options',
        'icon_url'   => 'dashicons-admin-settings',
        'position'   => 30,
    ]);
}

/**
 * Get global context data for workflow injection
 * 
 * @since 1.0.0
 * @return array Global context data
 */
function pf_get_global_context(): array {
    static $context = null;
    
    if ($context === null) {
        $context = [
            'company_name' => get_field('company_name', 'option') ?: '',
            'industry' => get_field('industry', 'option') ?: '',
            'tone_of_voice' => get_field('tone_of_voice', 'option') ?: '',
            'target_audience' => get_field('target_audience', 'option') ?: '',
            'mission_values' => get_field('mission_values', 'option') ?: '',
            'reference_examples' => get_field('reference_examples', 'option') ?: [],
        ];
    }
    
    return $context;
}

/**
 * Inject global context into workflow prompts
 * 
 * @since 1.0.0
 * @param string $prompt The prompt template
 * @param array $context_requirements Required context types
 * @return string Modified prompt with injected context
 */
function pf_inject_global_context(string $prompt, array $context_requirements = []): string {
    if (empty($context_requirements)) {
        return $prompt;
    }
    
    $global_context = pf_get_global_context();
    $injected_context = [];
    
    foreach ($context_requirements as $req) {
        $type = $req['context_type'] ?? '';
        $required = $req['required'] ?? false;
        $source = $req['source'] ?? 'user_profile';
        $default = $req['default_value'] ?? '';
        
        switch ($type) {
            case 'business':
                if ($source === 'user_profile' && !empty($global_context['company_name'])) {
                    $injected_context[] = "Company: " . $global_context['company_name'];
                    if (!empty($global_context['industry'])) {
                        $injected_context[] = "Industry: " . $global_context['industry'];
                    }
                    if (!empty($global_context['mission_values'])) {
                        $injected_context[] = "Mission & Values: " . $global_context['mission_values'];
                    }
                }
                break;
                
            case 'icp':
                if ($source === 'user_profile' && !empty($global_context['target_audience'])) {
                    $injected_context[] = "Target Audience: " . $global_context['target_audience'];
                }
                break;
                
            case 'tone':
                if ($source === 'user_profile' && !empty($global_context['tone_of_voice'])) {
                    $injected_context[] = "Tone of Voice: " . $global_context['tone_of_voice'];
                }
                break;
                
            case 'examples':
                if ($source === 'user_profile' && !empty($global_context['reference_examples'])) {
                    $examples = [];
                    foreach ($global_context['reference_examples'] as $example) {
                        if (!empty($example['title']) && !empty($example['ref_text_or_link'])) {
                            $examples[] = $example['title'] . ": " . $example['ref_text_or_link'];
                        }
                    }
                    if (!empty($examples)) {
                        $injected_context[] = "Reference Examples:\n" . implode("\n", $examples);
                    }
                }
                break;
        }
        
        // Use default if no context found and not required
        if (empty($injected_context) && !$required && !empty($default)) {
            $injected_context[] = $default;
        }
    }
    
    if (!empty($injected_context)) {
        $context_text = "\n\n--- Context ---\n" . implode("\n", $injected_context) . "\n--- End Context ---\n";
        $prompt = $prompt . $context_text;
    }
    
    return $prompt;
}


/* =====================================================
   Frontend CSS / JS Enqueue
===================================================== */
add_action('wp_enqueue_scripts', function () {
    // Basisvariablen
    $base = get_stylesheet_directory();
    $uri  = get_stylesheet_directory_uri();

    // 0) Child Style
    wp_enqueue_style(
        'pf-child',
        get_stylesheet_uri(),
        [],
        wp_get_theme()->get('Version')
    );

    // Core (immer) - mit Caching für bessere Performance
    $core = $base . '/assets/css/pf-core.css';
    if (file_exists($core)) {
        $version = (function_exists('wp_get_environment_type') && wp_get_environment_type() === 'production') 
            ? wp_get_theme()->get('Version') 
            : filemtime($core);
        wp_enqueue_style('pf-core', $uri . '/assets/css/pf-core.css', ['pf-child'], $version);
    }

    // Landing (nur Front Page)
    if (is_front_page()) {
        $f = $base . '/assets/css/pf-landing.css';
        if (file_exists($f)) {
            $version = (function_exists('wp_get_environment_type') && wp_get_environment_type() === 'production') 
                ? wp_get_theme()->get('Version') 
                : filemtime($f);
            wp_enqueue_style('pf-landing', $uri . '/assets/css/pf-landing.css', ['pf-core'], $version);
        }
    }

    // Workflows (Single, Archive, Taxonomy)
    if (is_singular('workflows') || is_post_type_archive('workflows') || is_tax(['workflow_category','workflow_tag'])) {
        $f = $base . '/assets/css/pf-workflows.css';
        if (file_exists($f)) {
            $version = (function_exists('wp_get_environment_type') && wp_get_environment_type() === 'production') 
                ? wp_get_theme()->get('Version') 
                : filemtime($f);
            wp_enqueue_style('pf-workflows', $uri . '/assets/css/pf-workflows.css', ['pf-core'], $version);
        }

        $js = $base . '/assets/js/pf-workflows.js';
        if (file_exists($js)) {
            $js_version = (function_exists('wp_get_environment_type') && wp_get_environment_type() === 'production') 
                ? wp_get_theme()->get('Version') 
                : filemtime($js);
            wp_enqueue_script('pf-workflows-js', $uri . '/assets/js/pf-workflows.js', [], $js_version, true);
            
            // DEBUG: Log the actual URL being used
            error_log('PF DEBUG: Enqueuing JS from: ' . $uri . '/assets/js/pf-workflows.js');
            error_log('PF DEBUG: get_stylesheet_directory_uri() = ' . get_stylesheet_directory_uri());
            error_log('PF DEBUG: get_template_directory_uri() = ' . get_template_directory_uri());

            // Bereits vorhanden: AJAX-Infos für Ratings
            wp_localize_script('pf-workflows-js', 'PF_WORKFLOWS', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce'    => wp_create_nonce('pf-rate-nonce'),
            ]);
			
			// PF Config laden und ins JS geben (einmalig)
			$PF_CONFIG = pf_load_config();
			wp_localize_script('pf-workflows-js', 'PF_CONFIG', $PF_CONFIG);
			wp_localize_script('pf-workflows-js', 'PF_FLAGS', $PF_CONFIG['feature_flags'] ?? []);
        }

    }

    // Navigation JavaScript (global für alle Seiten)
    $nav_js = $base . '/assets/js/pf-navigation.js';
    if (file_exists($nav_js)) {
        $nav_version = (function_exists('wp_get_environment_type') && wp_get_environment_type() === 'production') 
            ? wp_get_theme()->get('Version') 
            : filemtime($nav_js);
        wp_enqueue_script('pf-navigation-js', $uri . '/assets/js/pf-navigation.js', [], $nav_version, true);
    }

    // Blog
    if (is_home() || is_singular('post') || is_category() || is_tag() || is_date() || is_author()) {
        $f = $base . '/assets/css/pf-blog.css';
        if (file_exists($f)) {
            $version = (function_exists('wp_get_environment_type') && wp_get_environment_type() === 'production') 
                ? wp_get_theme()->get('Version') 
                : filemtime($f);
            wp_enqueue_style('pf-blog', $uri . '/assets/css/pf-blog.css', ['pf-core'], $version);
        }
    }

}, 99);


/* =====================================================
   Block Editor (Backend) Styles
===================================================== */
add_action('enqueue_block_editor_assets', function(){

    if ( !function_exists('get_current_screen') ) return;
    $screen = get_current_screen(); if ( !$screen ) return;

    $base = get_stylesheet_directory();
    $uri  = get_stylesheet_directory_uri();

    // Core
    $core = $base . '/assets/css/pf-core.css';
    if (file_exists($core)) {
        $version = (function_exists('wp_get_environment_type') && wp_get_environment_type() === 'production') 
            ? wp_get_theme()->get('Version') 
            : filemtime($core);
        wp_enqueue_style('pf-core-editor', $uri . '/assets/css/pf-core.css', [], $version);
    }

    // Landing
    if ($screen->post_type === 'page') {
        $f = $base . '/assets/css/pf-landing.css';
        if (file_exists($f)) {
            $version = (function_exists('wp_get_environment_type') && wp_get_environment_type() === 'production') 
                ? wp_get_theme()->get('Version') 
                : filemtime($f);
            wp_enqueue_style('pf-landing-editor', $uri . '/assets/css/pf-landing.css', ['pf-core-editor'], $version);
        }
    }

    // Workflows
    if ($screen->post_type === 'workflows') {
        $f = $base . '/assets/css/pf-workflows.css';
        if (file_exists($f)) {
            $version = (function_exists('wp_get_environment_type') && wp_get_environment_type() === 'production') 
                ? wp_get_theme()->get('Version') 
                : filemtime($f);
            wp_enqueue_style('pf-workflows-editor', $uri . '/assets/css/pf-workflows.css', ['pf-core-editor'], $version);
        }
    }

    // Blog
    if ($screen->post_type === 'post') {
        $f = $base . '/assets/css/pf-blog.css';
        if (file_exists($f)) {
            $version = (function_exists('wp_get_environment_type') && wp_get_environment_type() === 'production') 
                ? wp_get_theme()->get('Version') 
                : filemtime($f);
            wp_enqueue_style('pf-blog-editor', $uri . '/assets/css/pf-blog.css', ['pf-core-editor'], $version);
        }
    }

});


/* =====================================================
   AJAX Workflow Rating
===================================================== */
add_action('wp_ajax_pf_rate_workflow', 'pf_rate_workflow_cb');
add_action('wp_ajax_nopriv_pf_rate_workflow', 'pf_rate_workflow_cb');

function pf_rate_workflow_cb(){
    try {
        // Enhanced security check
        if (!wp_verify_nonce($_POST['nonce'], 'pf-rate-nonce')) {
            wp_send_json_error(['message' => 'Security check failed'], 403);
        }

        // Rate limiting with improved IP detection
        $user_ip = pf_get_client_ip();
        $rate_limit_key = 'pf_rate_limit_' . md5($user_ip);
        if (get_transient($rate_limit_key)) {
            wp_send_json_error(['message' => 'Rate limit exceeded. Please wait before rating again.'], 429);
        }

        // Input validation and sanitization
        $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
        $rating  = isset($_POST['rating'])  ? (int) $_POST['rating']  : 0;

        // Validate inputs
        if (!$post_id || $rating < PF_MIN_RATING || $rating > PF_MAX_RATING) {
            wp_send_json_error(['message' => 'Invalid rating data'], 400);
        }

        if (get_post_type($post_id) !== 'workflows') {
            wp_send_json_error(['message' => 'Invalid workflow'], 404);
        }

        // Set rate limit
        set_transient($rate_limit_key, 1, PF_RATE_LIMIT_DURATION);

    } catch (Exception $e) {
        error_log('[PF Error] Rating error: ' . $e->getMessage());
        wp_send_json_error(['message' => 'An unexpected error occurred'], 500);
    }

    // ---- Dupes blocken
    $already = false;

    if ( is_user_logged_in() ) {
        $user_id = get_current_user_id();
        $flag_key = 'pf_rated_' . $post_id;
        if ( get_user_meta($user_id, $flag_key, true) ) {
            $already = true;
        } else {
            update_user_meta($user_id, $flag_key, current_time('mysql'));
        }
    } else {
        // IP-basiert 24h sperren (für Gäste)
        $ip = pf_get_client_ip();
        $transient_key = 'pf_rated_' . $post_id . '_' . md5($ip);
        if ( get_transient($transient_key) ) {
            $already = true;
        } else {
            set_transient($transient_key, 1, DAY_IN_SECONDS);
        }
    }

    if ( $already ) {
        // Auch in diesem Fall aktuelle Werte zurückgeben
        $sum   = (int) get_post_meta($post_id, 'pf_rating_sum', true);
        $count = (int) get_post_meta($post_id, 'pf_rating_count', true);
        $avg   = $count ? round($sum / $count, 1) : 0;
        wp_send_json_error(['message' => 'already_rated', 'avg' => $avg, 'count' => $count], 409);
    }

    // ---- Wertung speichern
    $sum   = (int) get_post_meta($post_id, 'pf_rating_sum', true);
    $count = (int) get_post_meta($post_id, 'pf_rating_count', true);
    $sum   += $rating;
    $count += 1;

    update_post_meta($post_id, 'pf_rating_sum', $sum);
    update_post_meta($post_id, 'pf_rating_count', $count);

    $avg = round($sum / $count, 1);

    // UX: optional Cookie setzen (nur Komfort)
    setcookie('pf_rated_' . $post_id, '1', time() + DAY_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN);

    wp_send_json_success(['avg' => $avg, 'count' => $count]);
}
/* =====================================================
   Gating for LoggedIn Users
===================================================== */
// Note: pf_user_has_access() function is already defined above in Helper Functions section


/* =====================================================
   Pricing Page Assets
===================================================== */
add_action('wp_enqueue_scripts', function () {
    if (!is_page('pricing')) return;

    $base_dir = get_stylesheet_directory();
    $base_uri = get_stylesheet_directory_uri();

    $core_css = $base_dir . '/assets/css/pf-core.css';
    if (file_exists($core_css)) {
        $version = (function_exists('wp_get_environment_type') && wp_get_environment_type() === 'production') 
            ? wp_get_theme()->get('Version') 
            : filemtime($core_css);
        wp_enqueue_style('pf-core', $base_uri . '/assets/css/pf-core.css', [], $version);
    }

    $pricing_css = $base_dir . '/assets/css/pf-pricing.css';
    if (file_exists($pricing_css)) {
        $version = (function_exists('wp_get_environment_type') && wp_get_environment_type() === 'production') 
            ? wp_get_theme()->get('Version') 
            : filemtime($pricing_css);
        wp_enqueue_style('pf-pricing-css', $base_uri . '/assets/css/pf-pricing.css', ['pf-core'], $version);
    }

    $pricing_js = $base_dir . '/assets/js/pf-pricing.js';
    if (file_exists($pricing_js)) {
        $version = (function_exists('wp_get_environment_type') && wp_get_environment_type() === 'production') 
            ? wp_get_theme()->get('Version') 
            : filemtime($pricing_js);
        wp_enqueue_script('pf-pricing-js', $base_uri . '/assets/js/pf-pricing.js', [], $version, true);
    }
}, 110);

/* =====================================================
   Logo from Customizer
===================================================== */

add_action('after_setup_theme', function () {
  add_theme_support('custom-logo', [
    'height'      => 48,
    'width'       => 200,
    'flex-width'  => true,
    'flex-height' => true,
  ]);
  add_theme_support('title-tag');
});

/* =====================================================
   Admin Columns for Workflows – Extended Overview
===================================================== */
add_filter('manage_workflows_posts_columns', function ($columns) {
    // Bestehende Reihenfolge beibehalten, neue Spalten anhängen
    $columns['pf_version']        = __('Version', 'prompt-finder');
    $columns['pf_last_updated']   = __('Last Update', 'prompt-finder');
    $columns['pf_access_mode']    = __('Access', 'prompt-finder');
    $columns['pf_free_steps']     = __('Free Steps', 'prompt-finder');
    $columns['pf_login_required'] = __('Login', 'prompt-finder');
    $columns['pf_access_tier']    = __('Tier', 'prompt-finder');
    $columns['pf_status']         = __('Status', 'prompt-finder');
    $columns['pf_license']        = __('License', 'prompt-finder');
    $columns['pf_owner']          = __('Owner', 'prompt-finder');

    $columns['pf_steps']          = __('Steps', 'prompt-finder');
    $columns['pf_time_saved']     = __('Time Saved (min)', 'prompt-finder');
    $columns['pf_difficulty']     = __('Difficulty w/o AI', 'prompt-finder');

    $columns['pf_use_case']       = __('Use Case', 'prompt-finder');
    $columns['pf_expected']       = __('Expected Outcome', 'prompt-finder');
    $columns['pf_pain']           = __('Pain Points', 'prompt-finder');

    $columns['pf_rating']         = __('Rating', 'prompt-finder');
    return $columns;
});

add_action('manage_workflows_posts_custom_column', function ($column, $post_id) {
    // Helper zum Kürzen langer Texte
    $short = function ($text, $len = 80) {
        $t = trim((string)$text);
        return (mb_strlen($t) > $len) ? mb_substr($t, 0, $len - 1) . '…' : $t;
    };

    switch ($column) {
        case 'pf_version':
            echo esc_html(function_exists('get_field') ? (get_field('Version', $post_id) ?: '–') : '–');
            break;

        case 'pf_last_updated':
            echo esc_html(get_field('lastest_update', $post_id) ?: '–');
            break;

        case 'pf_access_mode':
            echo esc_html(ucfirst(get_field('access_mode', $post_id) ?: 'free'));
            break;

        case 'pf_free_steps':
            $n = get_field('free_step_limit', $post_id);
            echo esc_html($n === '' || $n === null ? '–' : (string)$n);
            break;

        case 'pf_login_required':
            echo get_field('login_required', $post_id) ? 'Yes' : 'No';
            break;

        case 'pf_access_tier':
            echo esc_html(ucfirst(get_field('access_tier', $post_id) ?: 'free'));
            break;

        case 'pf_status':
            echo esc_html(ucfirst(get_field('status', $post_id) ?: 'draft'));
            break;

        case 'pf_license':
            echo esc_html(get_field('license', $post_id) ?: '–');
            break;

        case 'pf_owner':
            echo esc_html(get_field('owner', $post_id) ?: '–');
            break;

        case 'pf_steps':
            $steps = get_field('steps', $post_id);
            echo is_array($steps) ? count($steps) : 0;
            break;

        case 'pf_time_saved':
            $v = get_field('time_saved_min', $post_id);
            echo esc_html($v === '' || $v === null ? '–' : (string)(int)$v);
            break;

        case 'pf_difficulty':
            $v = get_field('difficulty_without_ai', $post_id);
            echo esc_html($v === '' || $v === null ? '–' : (string)(int)$v);
            break;

        case 'pf_use_case':
            echo esc_html($short(get_field('use_case', $post_id)));
            break;

        case 'pf_expected':
            echo esc_html($short(get_field('expected_outcome', $post_id)));
            break;

        case 'pf_pain':
            echo esc_html($short(get_field('pain_point', $post_id)));
            break;

        case 'pf_rating':
            $sum   = (int) get_post_meta($post_id, 'pf_rating_sum', true);
            $count = (int) get_post_meta($post_id, 'pf_rating_count', true);
            if ($count > 0) {
                $avg = round($sum / $count, 1);
                echo esc_html("★ {$avg} ({$count})");
            } else {
                echo '–';
            }
            break;
    }
}, 10, 2);

/* Sortierbare Spalten */
add_filter('manage_edit-workflows_sortable_columns', function ($columns) {
    $columns['pf_version']      = 'pf_version';
    $columns['pf_last_updated'] = 'pf_last_updated';
    $columns['pf_access_mode']  = 'pf_access_mode';
    $columns['pf_steps']        = 'pf_steps';        // Hinweis: wird nur rudimentär sortiert (s. pre_get_posts)
    $columns['pf_time_saved']   = 'pf_time_saved';
    $columns['pf_difficulty']   = 'pf_difficulty';
    return $columns;
});

/* Sorting-Logik für ACF/Meta-Felder */
add_action('pre_get_posts', function ($query) {
    if (!is_admin() || !$query->is_main_query()) return;
    if ($query->get('post_type') !== 'workflows') return;

    $orderby = $query->get('orderby');

    // Mapping: Orderby → Meta-Key + Type
    $map = [
        'pf_version'      => ['key' => 'Version',               'type' => 'CHAR'],
        'pf_last_updated' => ['key' => 'lastest_update',        'type' => 'CHAR'], // als String (Datumformat je nach ACF)
        'pf_access_mode'  => ['key' => 'access_mode',           'type' => 'CHAR'],
        'pf_time_saved'   => ['key' => 'time_saved_min',        'type' => 'NUMERIC'],
        'pf_difficulty'   => ['key' => 'difficulty_without_ai', 'type' => 'NUMERIC'],
    ];

    if ($orderby === 'pf_steps') {
        // Einfacher Fallback: nach Titel sortieren, da Steps ein Repeater ist (Count nicht direkt sortierbar ohne JOIN)
        $query->set('orderby', 'title');
        return;
    }

    if (isset($map[$orderby])) {
        $meta_key = $map[$orderby]['key'];
        $type     = $map[$orderby]['type'];

        $query->set('meta_key', $meta_key);
        $query->set('orderby', $type === 'NUMERIC' ? 'meta_value_num' : 'meta_value');
    }
});

/* ============ Favorites (MVP) ============ */
add_action('wp_enqueue_scripts', function(){
  if ( is_singular('workflows') || is_post_type_archive('workflows') ) {
    wp_localize_script('pf-workflows-js', 'PF_FAVS', [
      'ajax_url'   => admin_url('admin-ajax.php'),
      'nonce'      => wp_create_nonce('pf-fav-nonce'),
      'logged_in'  => is_user_logged_in(),
      // optional Texte
      'txt_added'  => 'Saved to favorites',
      'txt_removed'=> 'Removed from favorites',
      'txt_login'  => 'Please log in to save favorites',
      'txt_denied' => 'Favorites are for paying users',
    ]);
  }
}, 111);

/* Helper: darf dieser User favorisieren? */
function pf_user_can_favorite(): bool {
  if ( ! is_user_logged_in() ) return false;
  // MVP: alle eingeloggten dürfen
  // Für zahlende Kunden nur:
  // return current_user_can('pf_can_favorite'); // Capability via Membership/Role setzen
  return true;
}

/* AJAX: Toggle Favorite */
add_action('wp_ajax_pf_toggle_favorite', 'pf_toggle_favorite_cb');
function pf_toggle_favorite_cb(){
  try {
    // Enhanced security check
    if (!wp_verify_nonce($_POST['nonce'], 'pf-fav-nonce')) {
      wp_send_json_error(['message' => 'Security check failed'], 403);
    }

    // Rate limiting for favorites
    $user_ip = pf_get_client_ip();
    $rate_limit_key = 'pf_fav_limit_' . md5($user_ip);
    if (get_transient($rate_limit_key)) {
      wp_send_json_error(['message' => 'Rate limit exceeded. Please wait before adding more favorites.'], 429);
    }

    if (!pf_user_can_favorite()) {
      wp_send_json_error(['message' => 'Access denied'], 403);
    }

    $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
    if (!$post_id || get_post_type($post_id) !== 'workflows') {
      wp_send_json_error(['message' => 'Invalid workflow'], 400);
    }

    // Set rate limit
    set_transient($rate_limit_key, 1, PF_FAV_LIMIT_DURATION);

  } catch (Exception $e) {
    error_log('[PF Error] Favorite error: ' . $e->getMessage());
    wp_send_json_error(['message' => 'An unexpected error occurred'], 500);
  }

  $user_id = get_current_user_id();
  $key = 'pf_favs';
  $favs = get_user_meta($user_id, $key, true);
  if ( ! is_array($favs) ) $favs = [];

  $added = false;
  if ( in_array($post_id, $favs, true) ) {
    // remove
    $favs = array_values(array_diff($favs, [$post_id]));
  } else {
    // add
    $favs[] = $post_id;
    $favs = array_values(array_unique($favs));
    $added = true;
  }
  update_user_meta($user_id, $key, $favs);

  wp_send_json_success([
    'added' => $added,
    'count' => count($favs),
  ]);
}

/* (Optional) AJAX: Liste abrufen. */
add_action('wp_ajax_pf_get_favorites', 'pf_get_favorites_cb');
function pf_get_favorites_cb(){
  try {
    // Enhanced security check
    if (!wp_verify_nonce($_POST['nonce'], 'pf-fav-nonce')) {
      wp_send_json_error(['message' => 'Security check failed'], 403);
    }

    if (!is_user_logged_in()) {
      wp_send_json_success(['ids' => []]);
    }

    $favs = get_user_meta(get_current_user_id(), 'pf_favs', true);
    if (!is_array($favs)) {
      $favs = [];
    }

    wp_send_json_success(['ids' => array_map('intval', $favs)]);

  } catch (Exception $e) {
    error_log('[PF Error] Get favorites error: ' . $e->getMessage());
    wp_send_json_error(['message' => 'An unexpected error occurred'], 500);
  }
}


