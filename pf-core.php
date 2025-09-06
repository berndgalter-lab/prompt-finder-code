<?php
declare(strict_types=1);

/**
 * Plugin Name: Prompt Finder Core
 * Plugin URI: https://github.com/berndgalter-lab/prompt-finder-code
 * Description: Core functionality extensions for the Prompt Finder Platform - integrates with existing workflows
 * Version: 1.0.0
 * Author: Bernd Galter
 * Author URI: https://promptfinder.de
 * License: GPL v2 or later
 * Text Domain: pf-core
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * Network: false
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit('Direct access denied.');
}

// Define plugin constants
define('PF_CORE_VERSION', '1.0.0');
define('PF_CORE_PLUGIN_URL', plugin_dir_url(__FILE__));
define('PF_CORE_PLUGIN_PATH', plugin_dir_path(__FILE__));

/**
 * Main Plugin Class - Extends existing theme functionality
 */
class PromptFinderCore {
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'admin_scripts'));
        
        // Hook into existing workflow functionality
        add_action('wp_enqueue_scripts', array($this, 'frontend_enhancements'));
        add_filter('the_content', array($this, 'enhance_workflow_content'));
        
        // Plugin activation/deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // API endpoints for n8n integration
        add_action('rest_api_init', array($this, 'register_api_endpoints'));
        
        // Enhanced search functionality
        add_action('pre_get_posts', array($this, 'enhance_workflow_search'));
        
        // Auto-sync workflows to optimized table
        add_action('save_post', array($this, 'auto_sync_workflow'), 10, 2);
        add_action('delete_post', array($this, 'auto_delete_workflow'));
    }
    
    public function init(): void {
        // Load plugin textdomain for internationalization
        load_plugin_textdomain(
            'pf-core', 
            false, 
            dirname(plugin_basename(__FILE__)) . '/languages'
        );
        
        // Register additional workflow meta fields
        $this->register_workflow_meta();
        
        // Add custom capabilities
        $this->add_custom_capabilities();
        
        // Register cron job for workflow analytics
        add_action('pf_log_workflow_view', [$this, 'log_workflow_view']);
        
        // Register AJAX handlers for analytics
        add_action('wp_ajax_pf_get_analytics', [$this, 'ajax_get_analytics']);
        add_action('wp_ajax_pf_export_data', [$this, 'ajax_export_data']);
    }
    
    /**
     * Frontend enhancements
     * 
     * @since 1.0.0
     * @return void
     */
    public function frontend_enhancements(): void {
        try {
            // Frontend enhancements can be added here
            // Currently handled by theme functions.php
            
            // Example: Add custom CSS for admin users
            if (is_user_logged_in() && current_user_can('manage_options')) {
                wp_add_inline_style('pf-core', '
                    .pf-debug { 
                        border: 1px dashed #0073aa; 
                        background: #f0f8ff; 
                        padding: 10px; 
                        margin: 10px 0; 
                    }
                ');
            }
        } catch (Exception $e) {
            error_log('[PF Core] Frontend enhancements error: ' . $e->getMessage());
        }
    }
    
    /**
     * Enhance workflow content
     * 
     * @since 1.0.0
     * @param string $content The content to enhance
     * @return string Enhanced content
     */
    public function enhance_workflow_content(string $content): string {
        try {
            // Only enhance workflow post types
            if (!is_singular('workflows')) {
                return $content;
            }
            
            // Add analytics tracking for workflow views
            if (!is_admin()) {
                $workflow_id = get_the_ID();
                if ($workflow_id) {
                    // Log workflow view (non-blocking)
                    wp_schedule_single_event(time(), 'pf_log_workflow_view', [$workflow_id]);
                }
            }
            
            return $content;
        } catch (Exception $e) {
            error_log('[PF Core] Content enhancement error: ' . $e->getMessage());
            return $content;
        }
    }
    
    /**
     * Add admin menu for plugin management
     */
    public function add_admin_menu() {
        add_menu_page(
            'Prompt Finder Core',
            'PF Core',
            'manage_options',
            'pf-core-dashboard',
            array($this, 'admin_dashboard'),
            'dashicons-admin-tools',
            25
        );
        
        add_submenu_page(
            'pf-core-dashboard',
            'Analytics',
            'Analytics',
            'manage_options',
            'pf-analytics',
            array($this, 'admin_analytics')
        );
        
        add_submenu_page(
            'pf-core-dashboard',
            'API Integration',
            'API Integration',
            'manage_options',
            'pf-api',
            array($this, 'admin_api')
        );
        
        add_submenu_page(
            'pf-core-dashboard',
            'User Management',
            'User Management',
            'manage_options',
            'pf-users',
            array($this, 'admin_user_management')
        );
        
        add_submenu_page(
            'pf-core-dashboard',
            'Core Settings',
            'Settings',
            'manage_options',
            'pf-core-settings',
            array($this, 'admin_settings')
        );
        
        add_submenu_page(
            'pf-core-dashboard',
            'Database Management',
            'Database',
            'manage_options',
            'pf-database',
            array($this, 'admin_database')
        );
        
        add_submenu_page(
            'pf-core-dashboard',
            'Create Workflow',
            'Create Workflow',
            'manage_options',
            'pf-create-workflow',
            array($this, 'admin_create_workflow')
        );
    }
    
    /**
     * Enqueue admin scripts and styles
     * 
     * @since 1.0.0
     * @param string $hook Current admin page hook
     * @return void
     */
    public function admin_scripts(string $hook): void {
        if (strpos($hook, 'pf-core') === false) {
            return;
        }
        
        try {
            // Enqueue admin styles
            wp_enqueue_style(
                'pf-core-admin', 
                PF_CORE_PLUGIN_URL . 'admin/css/admin.css', 
                [], 
                PF_CORE_VERSION
            );
            
            // Enqueue admin scripts
            wp_enqueue_script(
                'pf-core-admin', 
                PF_CORE_PLUGIN_URL . 'admin/js/admin.js', 
                ['jquery'], 
                PF_CORE_VERSION, 
                true
            );
            
            // Localize script with security nonce
            wp_localize_script('pf-core-admin', 'PF_ADMIN', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('pf_admin_nonce'),
                'strings' => [
                    'confirm_delete' => __('Are you sure you want to delete this item?', 'pf-core'),
                    'error_generic' => __('An error occurred. Please try again.', 'pf-core'),
                    'success_saved' => __('Settings saved successfully.', 'pf-core'),
                ]
            ]);
        } catch (Exception $e) {
            error_log('[PF Core] Admin scripts error: ' . $e->getMessage());
        }
    }
    
    /**
     * Dashboard - Enhanced analytics for existing workflows
     */
    public function admin_dashboard() {
        // Get workflow statistics
        $workflow_count = wp_count_posts('workflows');
        $total_users = count_users();
        $recent_workflows = get_posts(array(
            'post_type' => 'workflows',
            'posts_per_page' => 5,
            'post_status' => 'publish',
            'orderby' => 'date',
            'order' => 'DESC'
        ));
        
        // Get rating statistics
        global $wpdb;
        $avg_rating = $wpdb->get_var("
            SELECT AVG(rating_sum / rating_count) 
            FROM (
                SELECT 
                    CAST(pm1.meta_value AS DECIMAL(10,2)) as rating_sum,
                    CAST(pm2.meta_value AS DECIMAL(10,2)) as rating_count
                FROM {$wpdb->postmeta} pm1 
                INNER JOIN {$wpdb->postmeta} pm2 ON pm1.post_id = pm2.post_id 
                INNER JOIN {$wpdb->posts} p ON pm1.post_id = p.ID
                WHERE pm1.meta_key = 'pf_rating_sum' 
                AND pm2.meta_key = 'pf_rating_count'
                AND p.post_type = 'workflows'
                AND pm2.meta_value > 0
            ) as ratings
        ");
        
        ?>
        <div class="wrap">
            <h1>Prompt Finder Core Dashboard</h1>
            
            <div class="pf-dashboard-stats">
                <div class="pf-stat-card">
                    <h3>Total Workflows</h3>
                    <div class="pf-stat-number"><?php echo $workflow_count->publish; ?></div>
                    <div class="pf-stat-meta"><?php echo $workflow_count->draft; ?> drafts</div>
                </div>
                
                <div class="pf-stat-card">
                    <h3>Registered Users</h3>
                    <div class="pf-stat-number"><?php echo $total_users['total_users']; ?></div>
                </div>
                
                <div class="pf-stat-card">
                    <h3>Average Rating</h3>
                    <div class="pf-stat-number"><?php echo $avg_rating ? number_format($avg_rating, 1) : '—'; ?></div>
                    <div class="pf-stat-meta">⭐ out of 5</div>
                </div>
                
                <div class="pf-stat-card">
                    <h3>Platform Status</h3>
                    <div class="pf-stat-number pf-status-online">🟢 Online</div>
                </div>
            </div>
            
            <div class="pf-dashboard-grid">
                <div class="pf-dashboard-section">
                    <h2>Recent Workflows</h2>
                    <div class="pf-recent-list">
                        <?php foreach($recent_workflows as $workflow): ?>
                            <div class="pf-recent-item">
                                <strong><?php echo esc_html($workflow->post_title); ?></strong>
                                <span class="pf-recent-date"><?php echo get_the_date('M j, Y', $workflow); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <a href="<?php echo admin_url('edit.php?post_type=workflows'); ?>" class="button">View All Workflows</a>
                </div>
                
                <div class="pf-dashboard-section">
                    <h2>Quick Actions</h2>
                    <div class="pf-quick-actions">
                        <a href="<?php echo admin_url('post-new.php?post_type=workflows'); ?>" class="button button-primary">Add New Workflow</a>
                        <a href="<?php echo admin_url('admin.php?page=pf-analytics'); ?>" class="button">View Analytics</a>
                        <a href="<?php echo admin_url('admin.php?page=pf-api'); ?>" class="button">n8n Integration</a>
                        <a href="<?php echo admin_url('admin.php?page=pf-core-settings'); ?>" class="button">Settings</a>
                    </div>
                </div>
            </div>
        </div>
        
        <style>
        .pf-dashboard-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        .pf-stat-card {
            background: white;
            padding: 20px;
            border: 1px solid #ccc;
            border-radius: 8px;
            text-align: center;
        }
        .pf-stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #0073aa;
            margin: 10px 0;
        }
        .pf-stat-meta {
            color: #666;
            font-size: 0.9rem;
        }
        .pf-status-online {
            color: #46b450;
        }
        .pf-dashboard-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-top: 30px;
        }
        .pf-dashboard-section {
            background: white;
            padding: 20px;
            border: 1px solid #ccc;
            border-radius: 8px;
        }
        .pf-recent-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        .pf-recent-date {
            color: #666;
        }
        .pf-quick-actions {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        </style>
        <?php
    }
    
    /**
     * Analytics page with detailed workflow metrics
     */
    public function admin_analytics() {
        global $wpdb;
        
        // Get workflow access mode distribution
        $access_modes = $wpdb->get_results("
            SELECT pm.meta_value as access_mode, COUNT(*) as count
            FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
            WHERE pm.meta_key = 'access_mode' 
            AND p.post_type = 'workflows'
            AND p.post_status = 'publish'
            GROUP BY pm.meta_value
        ");
        
        // Get most popular workflows by rating count
        $popular_workflows = $wpdb->get_results("
            SELECT p.post_title, pm.meta_value as rating_count
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE pm.meta_key = 'pf_rating_count'
            AND p.post_type = 'workflows'
            AND p.post_status = 'publish'
            ORDER BY CAST(pm.meta_value AS UNSIGNED) DESC
            LIMIT 10
        ");
        
        ?>
        <div class="wrap">
            <h1>Prompt Finder Analytics</h1>
            
            <div class="pf-analytics-grid">
                <div class="pf-analytics-section">
                    <h2>Workflow Access Modes</h2>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>Access Mode</th>
                                <th>Count</th>
                                <th>Percentage</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $total = array_sum(array_column($access_modes, 'count'));
                            foreach($access_modes as $mode): ?>
                                <tr>
                                    <td><?php echo esc_html(ucfirst($mode->access_mode ?: 'free')); ?></td>
                                    <td><?php echo $mode->count; ?></td>
                                    <td><?php echo $total ? round(($mode->count / $total) * 100, 1) : 0; ?>%</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="pf-analytics-section">
                    <h2>Most Popular Workflows</h2>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>Workflow Title</th>
                                <th>Ratings</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($popular_workflows as $workflow): ?>
                                <tr>
                                    <td><?php echo esc_html($workflow->post_title); ?></td>
                                    <td><?php echo $workflow->rating_count; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <style>
        .pf-analytics-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-top: 20px;
        }
        .pf-analytics-section {
            background: white;
            padding: 20px;
            border: 1px solid #ccc;
            border-radius: 8px;
        }
        </style>
        <?php
    }
    
    /**
     * API Integration for n8n
     */
    public function admin_api() {
        if (isset($_POST['save_api_settings'])) {
            check_admin_referer('pf_api_settings');
            
            update_option('pf_api_enabled', !empty($_POST['pf_api_enabled']));
            update_option('pf_n8n_webhook_url', sanitize_url($_POST['pf_n8n_webhook_url']));
            update_option('pf_api_key', sanitize_text_field($_POST['pf_api_key']));
            
            echo '<div class="notice notice-success"><p>API settings saved!</p></div>';
        }
        
        $api_enabled = get_option('pf_api_enabled', false);
        $webhook_url = get_option('pf_n8n_webhook_url', '');
        $api_key = get_option('pf_api_key', wp_generate_password(32, false));
        
        ?>
        <div class="wrap">
            <h1>API Integration</h1>
            
            <form method="post">
                <?php wp_nonce_field('pf_api_settings'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">Enable API</th>
                        <td>
                            <label>
                                <input type="checkbox" name="pf_api_enabled" value="1" <?php checked($api_enabled); ?>>
                                Enable REST API endpoints for external integrations
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">API Key</th>
                        <td>
                            <input type="text" name="pf_api_key" value="<?php echo esc_attr($api_key); ?>" class="regular-text" readonly>
                            <p class="description">Use this key for API authentication</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">n8n Webhook URL</th>
                        <td>
                            <input type="url" name="pf_n8n_webhook_url" value="<?php echo esc_url($webhook_url); ?>" class="regular-text">
                            <p class="description">Webhook URL for n8n automation triggers</p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button('Save API Settings', 'primary', 'save_api_settings'); ?>
            </form>
            
            <?php if ($api_enabled): ?>
                <div class="pf-api-endpoints">
                    <h2>Available API Endpoints</h2>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>Endpoint</th>
                                <th>Method</th>
                                <th>Description</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><code>/wp-json/pf/v1/workflows</code></td>
                                <td>GET</td>
                                <td>List all workflows</td>
                            </tr>
                            <tr>
                                <td><code>/wp-json/pf/v1/workflows/{id}</code></td>
                                <td>GET</td>
                                <td>Get specific workflow</td>
                            </tr>
                            <tr>
                                <td><code>/wp-json/pf/v1/analytics</code></td>
                                <td>GET</td>
                                <td>Get platform analytics</td>
                            </tr>
                            <tr>
                                <td><code>/wp-json/pf/v1/users/stats</code></td>
                                <td>GET</td>
                                <td>Get user statistics</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    public function admin_user_management() {
        ?>
        <div class="wrap">
            <h1>User Management</h1>
            
            <div class="pf-user-stats">
                <?php
                $users = get_users();
                $pro_users = array_filter($users, function($user) {
                    return user_can($user, 'pf_pro');
                });
                ?>
                
                <div class="pf-stat-cards">
                    <div class="pf-stat-card">
                        <h3>Total Users</h3>
                        <div class="pf-stat-number"><?php echo count($users); ?></div>
                    </div>
                    <div class="pf-stat-card">
                        <h3>Pro Users</h3>
                        <div class="pf-stat-number"><?php echo count($pro_users); ?></div>
                    </div>
                </div>
            </div>
            
            <h2>User Capabilities Management</h2>
            <p>Manage user roles and capabilities for the Prompt Finder platform.</p>
            
            <div class="pf-capabilities">
                <h3>Available Capabilities</h3>
                <ul>
                    <li><strong>pf_pro:</strong> Access to pro workflows and features</li>
                    <li><strong>pf_can_favorite:</strong> Ability to save workflows as favorites</li>
                    <li><strong>pf_advanced_search:</strong> Access to advanced search features</li>
                </ul>
            </div>
        </div>
        <?php
    }
    
    public function admin_settings() {
        if (isset($_POST['save_settings'])) {
            check_admin_referer('pf_core_settings');
            
            update_option('pf_platform_mode', sanitize_text_field($_POST['pf_platform_mode']));
            update_option('pf_default_access_mode', sanitize_text_field($_POST['pf_default_access_mode']));
            update_option('pf_free_step_limit', intval($_POST['pf_free_step_limit']));
            update_option('pf_require_login', !empty($_POST['pf_require_login']));
            
            echo '<div class="notice notice-success"><p>Settings saved!</p></div>';
        }
        
        ?>
        <div class="wrap">
            <h1>Prompt Finder Core Settings</h1>
            
            <form method="post">
                <?php wp_nonce_field('pf_core_settings'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">Platform Mode</th>
                        <td>
                            <select name="pf_platform_mode">
                                <option value="development" <?php selected(get_option('pf_platform_mode', 'development'), 'development'); ?>>Development</option>
                                <option value="production" <?php selected(get_option('pf_platform_mode'), 'production'); ?>>Production</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Default Access Mode</th>
                        <td>
                            <select name="pf_default_access_mode">
                                <option value="free" <?php selected(get_option('pf_default_access_mode', 'free'), 'free'); ?>>Free</option>
                                <option value="half_locked" <?php selected(get_option('pf_default_access_mode'), 'half_locked'); ?>>Half Locked</option>
                                <option value="pro" <?php selected(get_option('pf_default_access_mode'), 'pro'); ?>>Pro Only</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Free Step Limit</th>
                        <td>
                            <input type="number" name="pf_free_step_limit" value="<?php echo esc_attr(get_option('pf_free_step_limit', 1)); ?>" min="1">
                            <p class="description">Number of free steps for half-locked workflows</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Require Login</th>
                        <td>
                            <label>
                                <input type="checkbox" name="pf_require_login" value="1" <?php checked(get_option('pf_require_login', false)); ?>>
                                Require users to login to access locked content
                            </label>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button('Save Settings', 'primary', 'save_settings'); ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Database management page
     */
    public function admin_database() {
        global $wpdb;
        
        if (isset($_POST['action'])) {
            check_admin_referer('pf_database_action');
            
            switch ($_POST['action']) {
                case 'migrate_workflows':
                    $this->migrate_existing_workflows();
                    echo '<div class="notice notice-success"><p>Workflows migrated successfully!</p></div>';
                    break;
                    
                case 'sync_all_workflows':
                    $workflows = get_posts([
                        'post_type' => 'workflows',
                        'posts_per_page' => -1,
                        'post_status' => 'any'
                    ]);
                    
                    $synced = 0;
                    foreach ($workflows as $workflow) {
                        if ($this->sync_workflow_to_optimized_table($workflow->ID)) {
                            $synced++;
                        }
                    }
                    
                    echo '<div class="notice notice-success"><p>Synced ' . $synced . ' workflows!</p></div>';
                    break;
                    
                case 'clear_optimized_table':
                    $workflows_table = $wpdb->prefix . 'pf_workflows_optimized';
                    $wpdb->query("TRUNCATE TABLE $workflows_table");
                    echo '<div class="notice notice-success"><p>Optimized table cleared!</p></div>';
                    break;
            }
        }
        
        // Get table statistics
        $workflows_table = $wpdb->prefix . 'pf_workflows_optimized';
        $optimized_count = $wpdb->get_var("SELECT COUNT(*) FROM $workflows_table");
        $total_workflows = wp_count_posts('workflows');
        
        ?>
        <div class="wrap">
            <h1>Database Management</h1>
            
            <div class="pf-database-stats">
                <div class="pf-stat-card">
                    <h3>Total Workflows (WordPress)</h3>
                    <div class="pf-stat-number"><?php echo $total_workflows->publish; ?></div>
                    <div class="pf-stat-meta"><?php echo $total_workflows->draft; ?> drafts</div>
                </div>
                
                <div class="pf-stat-card">
                    <h3>Optimized Table</h3>
                    <div class="pf-stat-number"><?php echo $optimized_count; ?></div>
                    <div class="pf-stat-meta">workflows synced</div>
                </div>
                
                <div class="pf-stat-card">
                    <h3>Sync Status</h3>
                    <div class="pf-stat-number pf-status-<?php echo $optimized_count > 0 ? 'synced' : 'not-synced'; ?>">
                        <?php echo $optimized_count > 0 ? '✅ Synced' : '❌ Not Synced'; ?>
                    </div>
                </div>
            </div>
            
            <div class="pf-database-actions">
                <h2>Database Actions</h2>
                
                <?php if ($total_workflows->publish > 0 && $optimized_count == 0): ?>
                    <!-- Migration needed -->
                    <form method="post" style="display: inline-block; margin-right: 10px;">
                        <?php wp_nonce_field('pf_database_action'); ?>
                        <input type="hidden" name="action" value="migrate_workflows">
                        <input type="submit" class="button button-primary" value="Migrate All Workflows" 
                               onclick="return confirm('This will migrate all workflows to the optimized table. Continue?')">
                    </form>
                <?php else: ?>
                    <!-- No migration needed -->
                    <div class="pf-info-box">
                        <p><strong>✅ No migration needed!</strong> All workflows will be automatically saved to the optimized table.</p>
                    </div>
                <?php endif; ?>
                
                <form method="post" style="display: inline-block; margin-right: 10px;">
                    <?php wp_nonce_field('pf_database_action'); ?>
                    <input type="hidden" name="action" value="sync_all_workflows">
                    <input type="submit" class="button" value="Sync All Workflows">
                </form>
                
                <form method="post" style="display: inline-block;">
                    <?php wp_nonce_field('pf_database_action'); ?>
                    <input type="hidden" name="action" value="clear_optimized_table">
                    <input type="submit" class="button button-secondary" value="Clear Optimized Table" 
                           onclick="return confirm('This will delete all data from the optimized table. Are you sure?')">
                </form>
            </div>
            
            <div class="pf-database-info">
                <h2>Optimized Table Benefits</h2>
                <ul>
                    <li><strong>Faster Queries:</strong> All workflow data in one table with optimized indexes</li>
                    <li><strong>Better Search:</strong> Full-text search on title and summary</li>
                    <li><strong>Reduced Joins:</strong> No need to join multiple tables for workflow data</li>
                    <li><strong>Auto-Sync:</strong> Automatically syncs when workflows are saved/updated</li>
                    <li><strong>Analytics Ready:</strong> Built-in usage and rating tracking</li>
                </ul>
                
                <h3>Table Structure</h3>
                <pre style="background: #f1f1f1; padding: 15px; border-radius: 5px; overflow-x: auto;">
CREATE TABLE wp_pf_workflows_optimized (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    post_id bigint(20) NOT NULL,
    title varchar(255) NOT NULL,
    slug varchar(255) NOT NULL,
    summary text,
    access_mode varchar(50) DEFAULT 'free',
    version varchar(50),
    lastest_update datetime,
    steps_count int(11) DEFAULT 0,
    steps_data longtext,
    rating_sum int(11) DEFAULT 0,
    rating_count int(11) DEFAULT 0,
    usage_count int(11) DEFAULT 0,
    is_published tinyint(1) DEFAULT 1,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY post_id (post_id),
    KEY title (title),
    KEY slug (slug),
    KEY access_mode (access_mode),
    KEY is_published (is_published),
    KEY rating_sum (rating_sum),
    KEY usage_count (usage_count),
    FULLTEXT KEY search_index (title, summary)
);
                </pre>
            </div>
        </div>
        
        <style>
        .pf-database-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        .pf-stat-card {
            background: white;
            padding: 20px;
            border: 1px solid #ccc;
            border-radius: 8px;
            text-align: center;
        }
        .pf-stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #0073aa;
            margin: 10px 0;
        }
        .pf-stat-meta {
            color: #666;
            font-size: 0.9rem;
        }
        .pf-status-synced {
            color: #46b450;
        }
        .pf-status-not-synced {
            color: #dc3232;
        }
        .pf-database-actions {
            background: white;
            padding: 20px;
            border: 1px solid #ccc;
            border-radius: 8px;
            margin: 20px 0;
        }
        .pf-database-info {
            background: white;
            padding: 20px;
            border: 1px solid #ccc;
            border-radius: 8px;
            margin: 20px 0;
        }
        .pf-database-info ul {
            margin: 15px 0;
        }
        .pf-database-info li {
            margin: 8px 0;
        }
        .pf-info-box {
            background: #e7f3ff;
            border: 1px solid #0073aa;
            border-radius: 8px;
            padding: 15px;
            margin: 10px 0;
            color: #0073aa;
        }
        .pf-info-box p {
            margin: 0;
            font-weight: 500;
        }
        </style>
        <?php
    }
    
    /**
     * Create workflow admin page
     */
    public function admin_create_workflow() {
        if (isset($_POST['create_workflow'])) {
            check_admin_referer('pf_create_workflow');
            
            $workflow_data = [
                'title' => sanitize_text_field($_POST['title']),
                'summary' => sanitize_textarea_field($_POST['summary']),
                'access_mode' => sanitize_text_field($_POST['access_mode']),
                'version' => sanitize_text_field($_POST['version']),
                'steps' => json_decode(stripslashes($_POST['steps']), true) ?: []
            ];
            
            $workflow_id = $this->create_workflow_in_optimized_table($workflow_data);
            
            if ($workflow_id) {
                echo '<div class="notice notice-success"><p>Workflow created successfully! ID: ' . $workflow_id . '</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>Failed to create workflow. Check error logs.</p></div>';
            }
        }
        
        ?>
        <div class="wrap">
            <h1>Create New Workflow</h1>
            <p>Create workflows directly in the optimized database table for maximum performance.</p>
            
            <form method="post" id="pf-create-workflow-form">
                <?php wp_nonce_field('pf_create_workflow'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="title">Workflow Title *</label></th>
                        <td>
                            <input type="text" name="title" id="title" class="regular-text" required>
                            <p class="description">The main title of your workflow</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="summary">Summary</label></th>
                        <td>
                            <textarea name="summary" id="summary" rows="3" class="large-text"></textarea>
                            <p class="description">Brief description of what this workflow does</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="access_mode">Access Mode</label></th>
                        <td>
                            <select name="access_mode" id="access_mode">
                                <option value="free">Free</option>
                                <option value="half_locked">Half Locked</option>
                                <option value="pro">Pro Only</option>
                            </select>
                            <p class="description">Who can access this workflow</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="version">Version</label></th>
                        <td>
                            <input type="text" name="version" id="version" class="regular-text" placeholder="1.0.0">
                            <p class="description">Version number for this workflow</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="steps">Steps (JSON) *</label></th>
                        <td>
                            <textarea name="steps" id="steps" rows="10" class="large-text code" required placeholder='[
  {
    "title": "Step 1: Define the task",
    "objective": "Clearly define what you want to achieve",
    "prompt": "I want you to help me with: {task_description}",
    "variables": [
      {
        "var_name": "task_description",
        "var_label": "Task Description",
        "var_description": "Describe what you want to accomplish",
        "required": true
      }
    ],
    "estimated_time_min": 5
  }
]'></textarea>
                            <p class="description">Define your workflow steps in JSON format. Use the example above as a template.</p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button('Create Workflow', 'primary', 'create_workflow'); ?>
            </form>
            
            <div class="pf-workflow-help">
                <h2>Workflow Creation Help</h2>
                <h3>JSON Structure:</h3>
                <pre style="background: #f1f1f1; padding: 15px; border-radius: 5px; overflow-x: auto;">
{
  "title": "Step title",
  "objective": "What this step accomplishes",
  "prompt": "The actual prompt with {variables}",
  "variables": [
    {
      "var_name": "variable_name",
      "var_label": "Display Label",
      "var_description": "Help text",
      "required": true,
      "example_value": "Example"
    }
  ],
  "estimated_time_min": 5,
  "example_output": "Expected result",
  "checklist": [
    {"check_item": "Check this"},
    {"check_item": "And this"}
  ]
}
                </pre>
                
                <h3>Benefits of Direct Creation:</h3>
                <ul>
                    <li><strong>Maximum Performance:</strong> No ACF overhead</li>
                    <li><strong>Optimized Storage:</strong> Direct database storage</li>
                    <li><strong>Full-Text Search:</strong> Built-in search capabilities</li>
                    <li><strong>Analytics Ready:</strong> Usage tracking included</li>
                </ul>
            </div>
        </div>
        
        <style>
        .pf-workflow-help {
            background: white;
            padding: 20px;
            border: 1px solid #ccc;
            border-radius: 8px;
            margin-top: 30px;
        }
        .pf-workflow-help h2 {
            margin-top: 0;
        }
        .pf-workflow-help h3 {
            color: #0073aa;
            margin-top: 20px;
        }
        .pf-workflow-help ul {
            margin: 15px 0;
        }
        .pf-workflow-help li {
            margin: 8px 0;
        }
        </style>
        <?php
    }
    
    /**
     * Register REST API endpoints for n8n integration
     */
    public function register_api_endpoints() {
        if (!get_option('pf_api_enabled', false)) return;
        
        register_rest_route('pf/v1', '/workflows', array(
            'methods' => 'GET',
            'callback' => array($this, 'api_get_workflows'),
            'permission_callback' => array($this, 'api_permissions_check'),
        ));
        
        register_rest_route('pf/v1', '/workflows/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'api_get_workflow'),
            'permission_callback' => array($this, 'api_permissions_check'),
        ));
        
        register_rest_route('pf/v1', '/analytics', array(
            'methods' => 'GET',
            'callback' => array($this, 'api_get_analytics'),
            'permission_callback' => array($this, 'api_permissions_check'),
        ));
    }
    
    public function api_permissions_check($request) {
        $api_key = $request->get_header('X-API-Key');
        return $api_key === get_option('pf_api_key');
    }
    
    public function api_get_workflows($request) {
        $workflows = get_posts(array(
            'post_type' => 'workflows',
            'posts_per_page' => 50,
            'post_status' => 'publish'
        ));
        
        $data = array();
        foreach ($workflows as $workflow) {
            $data[] = array(
                'id' => $workflow->ID,
                'title' => $workflow->post_title,
                'slug' => $workflow->post_name,
                'date' => $workflow->post_date,
                'access_mode' => get_field('access_mode', $workflow->ID),
                'steps' => count(get_field('steps', $workflow->ID) ?: []),
                'rating_avg' => $this->get_workflow_rating($workflow->ID),
                'url' => get_permalink($workflow->ID)
            );
        }
        
        return rest_ensure_response($data);
    }
    
    public function api_get_workflow($request) {
        $id = $request['id'];
        $workflow = get_post($id);
        
        if (!$workflow || $workflow->post_type !== 'workflows') {
            return new WP_Error('not_found', 'Workflow not found', array('status' => 404));
        }
        
        $steps = get_field('steps', $id);
        
        $data = array(
            'id' => $workflow->ID,
            'title' => $workflow->post_title,
            'content' => $workflow->post_content,
            'summary' => get_field('Summary', $id),
            'access_mode' => get_field('access_mode', $id),
            'steps' => $steps,
            'version' => get_field('Version', $id),
            'rating' => $this->get_workflow_rating($id),
            'url' => get_permalink($id)
        );
        
        return rest_ensure_response($data);
    }
    
    private function get_workflow_rating($post_id) {
        $sum = (int) get_post_meta($post_id, 'pf_rating_sum', true);
        $count = (int) get_post_meta($post_id, 'pf_rating_count', true);
        
        return array(
            'average' => $count ? round($sum / $count, 1) : 0,
            'count' => $count
        );
    }
    
    /**
     * Register additional meta fields for workflows
     */
    private function register_workflow_meta() {
        // Enhanced search meta
        register_meta('post', 'pf_search_keywords', array(
            'object_subtype' => 'workflows',
            'type' => 'string',
            'single' => true,
            'show_in_rest' => true,
        ));
        
        // Usage tracking
        register_meta('post', 'pf_usage_count', array(
            'object_subtype' => 'workflows',
            'type' => 'integer',
            'single' => true,
            'default' => 0,
        ));
    }
    
    /**
     * Add custom capabilities for user roles
     */
    private function add_custom_capabilities() {
        $admin = get_role('administrator');
        if ($admin) {
            $admin->add_cap('pf_pro');
            $admin->add_cap('pf_can_favorite');
            $admin->add_cap('pf_advanced_search');
        }
    }
    
    /**
     * Enhance workflow search functionality
     */
    public function enhance_workflow_search($query) {
        if (!is_admin() && $query->is_main_query() && $query->is_search()) {
            if (isset($_GET['post_type']) && $_GET['post_type'] === 'workflows') {
                // Enhanced search for workflows
                $query->set('meta_query', array(
                    'relation' => 'OR',
                    array(
                        'key' => 'pf_search_keywords',
                        'value' => $query->get('s'),
                        'compare' => 'LIKE'
                    )
                ));
            }
        }
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Create additional database tables if needed
        $this->create_analytics_tables();
        
        // Set default options
        add_option('pf_platform_mode', 'development');
        add_option('pf_core_version', PF_CORE_VERSION);
        add_option('pf_api_key', wp_generate_password(32, false));
        
        // Add custom capabilities to existing roles
        $this->add_custom_capabilities();
        
        flush_rewrite_rules();
    }
    
    public function deactivate() {
        flush_rewrite_rules();
    }
    
    /**
     * Create analytics tables for enhanced tracking
     */
    private function create_analytics_tables() {
        global $wpdb;
        
        // Usage tracking table
        $usage_table = $wpdb->prefix . 'pf_workflow_usage';
        
        $sql = "CREATE TABLE IF NOT EXISTS $usage_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            workflow_id bigint(20) NOT NULL,
            user_id bigint(20) NULL,
            session_id varchar(255) NOT NULL,
            ip_address varchar(45) NOT NULL,
            user_agent text,
            step_completed int(11) NOT NULL DEFAULT 0,
            completed_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY workflow_id (workflow_id),
            KEY user_id (user_id),
            KEY created_at (created_at)
        ) DEFAULT CHARSET=utf8mb4;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Search analytics table
        $search_table = $wpdb->prefix . 'pf_search_analytics';
        
        $sql = "CREATE TABLE IF NOT EXISTS $search_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            search_term varchar(255) NOT NULL,
            results_count int(11) NOT NULL DEFAULT 0,
            user_id bigint(20) NULL,
            ip_address varchar(45) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY search_term (search_term),
            KEY created_at (created_at)
        ) DEFAULT CHARSET=utf8mb4;";
        
        dbDelta($sql);
        
        // ACF Workflows optimized table
        $this->create_workflows_table();
    }
    
    /**
     * Create optimized workflows table for ACF data
     */
    private function create_workflows_table() {
        global $wpdb;
        
        $workflows_table = $wpdb->prefix . 'pf_workflows_optimized';
        
        $sql = "CREATE TABLE IF NOT EXISTS $workflows_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            post_id bigint(20) NOT NULL,
            title varchar(255) NOT NULL,
            slug varchar(255) NOT NULL,
            summary text,
            access_mode varchar(50) DEFAULT 'free',
            version varchar(50),
            lastest_update datetime,
            steps_count int(11) DEFAULT 0,
            steps_data longtext,
            rating_sum int(11) DEFAULT 0,
            rating_count int(11) DEFAULT 0,
            usage_count int(11) DEFAULT 0,
            is_published tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY post_id (post_id),
            KEY title (title),
            KEY slug (slug),
            KEY access_mode (access_mode),
            KEY is_published (is_published),
            KEY rating_sum (rating_sum),
            KEY usage_count (usage_count),
            KEY created_at (created_at),
            KEY updated_at (updated_at),
            FULLTEXT KEY search_index (title, summary)
        ) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        
        dbDelta($sql);
        
        // Migrate existing workflows to optimized table
        $this->migrate_existing_workflows();
    }
    
    /**
     * Migrate existing workflows to optimized table
     * Note: This is only needed if you have existing workflows in the old format
     */
    private function migrate_existing_workflows() {
        global $wpdb;
        
        $workflows_table = $wpdb->prefix . 'pf_workflows_optimized';
        
        // Check if migration is needed
        $existing_count = $wpdb->get_var("SELECT COUNT(*) FROM $workflows_table");
        if ($existing_count > 0) {
            return; // Already migrated
        }
        
        // Get all published workflows
        $workflows = get_posts([
            'post_type' => 'workflows',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => [
                [
                    'key' => 'steps',
                    'compare' => 'EXISTS'
                ]
            ]
        ]);
        
        if (empty($workflows)) {
            error_log('[PF Core] No existing workflows found to migrate');
            return;
        }
        
        foreach ($workflows as $workflow) {
            $this->sync_workflow_to_optimized_table($workflow->ID);
        }
        
        error_log('[PF Core] Migrated ' . count($workflows) . ' workflows to optimized table');
    }
    
    /**
     * Sync workflow data to optimized table
     */
    public function sync_workflow_to_optimized_table(int $post_id): bool {
        global $wpdb;
        
        try {
            $workflow = get_post($post_id);
            if (!$workflow || $workflow->post_type !== 'workflows') {
                return false;
            }
            
            // Get ACF fields
            $steps = get_field('steps', $post_id) ?: [];
            $summary = get_field('Summary', $post_id) ?: '';
            $access_mode = get_field('access_mode', $post_id) ?: 'free';
            $version = get_field('Version', $post_id) ?: '';
            $lastest_update = get_field('lastest_update', $post_id) ?: '';
            
            // Get rating data
            $rating_sum = (int) get_post_meta($post_id, 'pf_rating_sum', true);
            $rating_count = (int) get_post_meta($post_id, 'pf_rating_count', true);
            $usage_count = (int) get_post_meta($post_id, 'pf_usage_count', true);
            
            $workflows_table = $wpdb->prefix . 'pf_workflows_optimized';
            
            $data = [
                'post_id' => $post_id,
                'title' => $workflow->post_title,
                'slug' => $workflow->post_name,
                'summary' => $summary,
                'access_mode' => $access_mode,
                'version' => $version,
                'lastest_update' => $lastest_update,
                'steps_count' => count($steps),
                'steps_data' => json_encode($steps),
                'rating_sum' => $rating_sum,
                'rating_count' => $rating_count,
                'usage_count' => $usage_count,
                'is_published' => $workflow->post_status === 'publish' ? 1 : 0,
                'created_at' => $workflow->post_date,
                'updated_at' => $workflow->post_modified
            ];
            
            // Insert or update
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $workflows_table WHERE post_id = %d",
                $post_id
            ));
            
            if ($existing) {
                $result = $wpdb->update($workflows_table, $data, ['post_id' => $post_id]);
            } else {
                $result = $wpdb->insert($workflows_table, $data);
            }
            
            return $result !== false;
            
        } catch (Exception $e) {
            error_log('[PF Core] Sync workflow error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get optimized workflow data
     */
    public function get_optimized_workflow(int $post_id): ?array {
        global $wpdb;
        
        $workflows_table = $wpdb->prefix . 'pf_workflows_optimized';
        
        $workflow = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $workflows_table WHERE post_id = %d AND is_published = 1",
            $post_id
        ), ARRAY_A);
        
        if (!$workflow) {
            return null;
        }
        
        // Decode steps data
        $workflow['steps'] = json_decode($workflow['steps_data'], true) ?: [];
        unset($workflow['steps_data']);
        
        return $workflow;
    }
    
    /**
     * Search optimized workflows
     */
    public function search_optimized_workflows(string $search_term, array $filters = []): array {
        global $wpdb;
        
        $workflows_table = $wpdb->prefix . 'pf_workflows_optimized';
        
        $where_conditions = ["is_published = 1"];
        $where_values = [];
        
        // Search term
        if (!empty($search_term)) {
            $where_conditions[] = "(title LIKE %s OR summary LIKE %s)";
            $search_like = '%' . $wpdb->esc_like($search_term) . '%';
            $where_values[] = $search_like;
            $where_values[] = $search_like;
        }
        
        // Access mode filter
        if (!empty($filters['access_mode'])) {
            $where_conditions[] = "access_mode = %s";
            $where_values[] = $filters['access_mode'];
        }
        
        // Rating filter
        if (!empty($filters['min_rating'])) {
            $where_conditions[] = "(rating_sum / GREATEST(rating_count, 1)) >= %f";
            $where_values[] = (float) $filters['min_rating'];
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        $sql = "SELECT * FROM $workflows_table WHERE $where_clause ORDER BY usage_count DESC, rating_sum DESC LIMIT 50";
        
        if (!empty($where_values)) {
            $sql = $wpdb->prepare($sql, $where_values);
        }
        
        $results = $wpdb->get_results($sql, ARRAY_A);
        
        // Decode steps data for each result
        foreach ($results as &$result) {
            $result['steps'] = json_decode($result['steps_data'], true) ?: [];
            unset($result['steps_data']);
        }
        
        return $results;
    }
    
    /**
     * Create new workflow directly in optimized table
     * This bypasses ACF and stores data directly in the optimized table
     */
    public function create_workflow_in_optimized_table(array $workflow_data): ?int {
        global $wpdb;
        
        try {
            $workflows_table = $wpdb->prefix . 'pf_workflows_optimized';
            
            // Validate required fields
            if (empty($workflow_data['title']) || empty($workflow_data['steps'])) {
                throw new Exception('Title and steps are required');
            }
            
            $data = [
                'post_id' => $workflow_data['post_id'] ?? 0,
                'title' => sanitize_text_field($workflow_data['title']),
                'slug' => sanitize_title($workflow_data['title']),
                'summary' => sanitize_textarea_field($workflow_data['summary'] ?? ''),
                'access_mode' => sanitize_text_field($workflow_data['access_mode'] ?? 'free'),
                'version' => sanitize_text_field($workflow_data['version'] ?? ''),
                'lastest_update' => $workflow_data['lastest_update'] ?? '',
                'steps_count' => count($workflow_data['steps']),
                'steps_data' => json_encode($workflow_data['steps']),
                'rating_sum' => 0,
                'rating_count' => 0,
                'usage_count' => 0,
                'is_published' => 1,
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ];
            
            $result = $wpdb->insert($workflows_table, $data);
            
            if ($result === false) {
                throw new Exception('Failed to insert workflow: ' . $wpdb->last_error);
            }
            
            $workflow_id = $wpdb->insert_id;
            error_log('[PF Core] Created workflow ' . $workflow_id . ' directly in optimized table');
            
            return $workflow_id;
            
        } catch (Exception $e) {
            error_log('[PF Core] Create workflow error: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Auto-sync workflow when saved
     * This ensures all workflows are stored in the optimized table
     */
    public function auto_sync_workflow(int $post_id, WP_Post $post): void {
        if ($post->post_type === 'workflows') {
            // Always sync to optimized table
            $success = $this->sync_workflow_to_optimized_table($post_id);
            
            if ($success) {
                error_log('[PF Core] Successfully synced workflow ' . $post_id . ' to optimized table');
            } else {
                error_log('[PF Core] Failed to sync workflow ' . $post_id . ' to optimized table');
            }
        }
    }
    
    /**
     * Auto-delete workflow from optimized table
     */
    public function auto_delete_workflow(int $post_id): void {
        global $wpdb;
        
        $workflow = get_post($post_id);
        if ($workflow && $workflow->post_type === 'workflows') {
            $workflows_table = $wpdb->prefix . 'pf_workflows_optimized';
            $wpdb->delete($workflows_table, ['post_id' => $post_id]);
        }
    }
}

// Utility functions that integrate with your existing theme
function pf_core_get_user_plan(): string {
    if (current_user_can('pf_pro')) return 'pro';
    if (is_user_logged_in()) {
        $plan = get_user_meta(get_current_user_id(), 'pf_plan', true);
        return $plan ?: 'free';
    }
    return 'guest';
}

function pf_core_log_workflow_usage($workflow_id, $step_completed = 0, $completed = false) {
    global $wpdb;
    
    $table = $wpdb->prefix . 'pf_workflow_usage';
    $session_id = session_id() ?: wp_generate_password(32, false);
    $user_id = is_user_logged_in() ? get_current_user_id() : null;
    
    $data = array(
        'workflow_id' => $workflow_id,
        'user_id' => $user_id,
        'session_id' => $session_id,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        'step_completed' => $step_completed,
        'completed_at' => $completed ? current_time('mysql') : null
    );
    
    $wpdb->insert($table, $data);
}

function pf_core_log_search($search_term, $results_count = 0) {
    global $wpdb;
    
    $table = $wpdb->prefix . 'pf_search_analytics';
    $user_id = is_user_logged_in() ? get_current_user_id() : null;
    
    $data = array(
        'search_term' => sanitize_text_field($search_term),
        'results_count' => intval($results_count),
        'user_id' => $user_id,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'
    );
    
    $wpdb->insert($table, $data);
}

function pf_core_get_workflow_analytics($workflow_id) {
    global $wpdb;
    
    $table = $wpdb->prefix . 'pf_workflow_usage';
    
    $stats = $wpdb->get_row($wpdb->prepare("
        SELECT 
            COUNT(*) as total_views,
            COUNT(DISTINCT user_id) as unique_users,
            COUNT(DISTINCT session_id) as unique_sessions,
            AVG(step_completed) as avg_steps_completed,
            COUNT(CASE WHEN completed_at IS NOT NULL THEN 1 END) as completions
        FROM $table 
        WHERE workflow_id = %d
    ", $workflow_id));
    
    return $stats;
}

// Integration with existing theme functions
add_action('init', function() {
    // Hook into existing workflow view tracking
    if (is_singular('workflows') && !is_admin()) {
        $workflow_id = get_the_ID();
        pf_core_log_workflow_usage($workflow_id);
        
        // Update usage count meta
        $current_count = get_post_meta($workflow_id, 'pf_usage_count', true) ?: 0;
        update_post_meta($workflow_id, 'pf_usage_count', $current_count + 1);
    }
});

// Enhance existing search functionality
add_action('pre_get_posts', function($query) {
    if (!is_admin() && $query->is_main_query() && $query->is_search()) {
        $search_term = get_search_query();
        if ($search_term) {
            $results_count = $query->found_posts ?? 0;
            pf_core_log_search($search_term, $results_count);
        }
    }
});

// Add workflow completion tracking via AJAX
add_action('wp_ajax_pf_track_completion', 'pf_core_track_completion');
add_action('wp_ajax_nopriv_pf_track_completion', 'pf_core_track_completion');

function pf_core_track_completion() {
    check_ajax_referer('pf_core_nonce', 'nonce');
    
    $workflow_id = intval($_POST['workflow_id'] ?? 0);
    $step = intval($_POST['step'] ?? 0);
    $completed = !empty($_POST['completed']);
    
    if ($workflow_id) {
        pf_core_log_workflow_usage($workflow_id, $step, $completed);
        
        // Trigger n8n webhook if configured
        $webhook_url = get_option('pf_n8n_webhook_url');
        if ($webhook_url && $completed) {
            wp_remote_post($webhook_url, array(
                'body' => json_encode(array(
                    'event' => 'workflow_completed',
                    'workflow_id' => $workflow_id,
                    'user_id' => get_current_user_id(),
                    'timestamp' => current_time('mysql')
                )),
                'headers' => array('Content-Type' => 'application/json')
            ));
        }
    }
    
    wp_send_json_success();
}

// Add workflow usage tracking to admin columns
add_filter('manage_workflows_posts_columns', function($columns) {
    $columns['pf_usage_stats'] = 'Usage Stats';
    return $columns;
});

add_action('manage_workflows_posts_custom_column', function($column, $post_id) {
    if ($column === 'pf_usage_stats') {
        $stats = pf_core_get_workflow_analytics($post_id);
        $usage_count = get_post_meta($post_id, 'pf_usage_count', true) ?: 0;
        
        echo sprintf(
            'Views: %d<br>Completions: %d<br>Avg Steps: %.1f',
            $usage_count,
            $stats->completions ?? 0,
            $stats->avg_steps_completed ?? 0
        );
    }
}, 10, 2);

// Enhanced JavaScript for frontend tracking
add_action('wp_enqueue_scripts', function() {
    if (is_singular('workflows')) {
        wp_enqueue_script('pf-core-tracking', PF_CORE_PLUGIN_URL . 'assets/tracking.js', array('jquery'), PF_CORE_VERSION, true);
        
        wp_localize_script('pf-core-tracking', 'PF_CORE', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('pf_core_nonce'),
            'workflow_id' => get_the_ID(),
            'user_id' => get_current_user_id(),
            'tracking_enabled' => true
        ));
    }
});

    /**
     * Log workflow view for analytics
     * 
     * @since 1.0.0
     * @param int $workflow_id Workflow post ID
     * @return void
     */
    public function log_workflow_view(int $workflow_id): void {
        try {
            global $wpdb;
            
            $table = $wpdb->prefix . 'pf_workflow_usage';
            $user_id = is_user_logged_in() ? get_current_user_id() : null;
            $session_id = session_id() ?: wp_generate_password(32, false);
            
            $data = [
                'workflow_id' => $workflow_id,
                'user_id' => $user_id,
                'session_id' => $session_id,
                'ip_address' => $this->get_client_ip(),
                'user_agent' => sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? ''),
                'step_completed' => 0,
                'completed_at' => null,
                'created_at' => current_time('mysql')
            ];
            
            $wpdb->insert($table, $data);
            
            // Update usage count meta
            $current_count = get_post_meta($workflow_id, 'pf_usage_count', true) ?: 0;
            update_post_meta($workflow_id, 'pf_usage_count', $current_count + 1);
            
        } catch (Exception $e) {
            error_log('[PF Core] Log workflow view error: ' . $e->getMessage());
        }
    }
    
    /**
     * Get client IP address with proxy support
     * 
     * @since 1.0.0
     * @return string Client IP address
     */
    private function get_client_ip(): string {
        $ip_keys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = sanitize_text_field($_SERVER[$key]);
                
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
        
        return sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    }
    
    /**
     * AJAX handler for analytics data
     * 
     * @since 1.0.0
     * @return void
     */
    public function ajax_get_analytics(): void {
        try {
            // Security check
            if (!wp_verify_nonce($_POST['nonce'] ?? '', 'pf_admin_nonce')) {
                wp_send_json_error(['message' => __('Security check failed', 'pf-core')], 403);
            }
            
            // Permission check
            if (!current_user_can('manage_options')) {
                wp_send_json_error(['message' => __('Insufficient permissions', 'pf-core')], 403);
            }
            
            $analytics = $this->get_analytics_data();
            wp_send_json_success($analytics);
            
        } catch (Exception $e) {
            error_log('[PF Core] Analytics AJAX error: ' . $e->getMessage());
            wp_send_json_error(['message' => __('An error occurred', 'pf-core')], 500);
        }
    }
    
    /**
     * AJAX handler for data export
     * 
     * @since 1.0.0
     * @return void
     */
    public function ajax_export_data(): void {
        try {
            // Security check
            if (!wp_verify_nonce($_POST['nonce'] ?? '', 'pf_admin_nonce')) {
                wp_send_json_error(['message' => __('Security check failed', 'pf-core')], 403);
            }
            
            // Permission check
            if (!current_user_can('manage_options')) {
                wp_send_json_error(['message' => __('Insufficient permissions', 'pf-core')], 403);
            }
            
            $export_data = $this->export_workflow_data();
            wp_send_json_success($export_data);
            
        } catch (Exception $e) {
            error_log('[PF Core] Export AJAX error: ' . $e->getMessage());
            wp_send_json_error(['message' => __('Export failed', 'pf-core')], 500);
        }
    }
    
    /**
     * Get analytics data
     * 
     * @since 1.0.0
     * @return array Analytics data
     */
    private function get_analytics_data(): array {
        global $wpdb;
        
        // Get workflow statistics
        $workflow_count = wp_count_posts('workflows');
        
        // Get user statistics
        $user_count = count_users();
        
        // Get rating statistics
        $avg_rating = $wpdb->get_var("
            SELECT AVG(rating_sum / rating_count) 
            FROM (
                SELECT 
                    CAST(pm1.meta_value AS DECIMAL(10,2)) as rating_sum,
                    CAST(pm2.meta_value AS DECIMAL(10,2)) as rating_count
                FROM {$wpdb->postmeta} pm1 
                INNER JOIN {$wpdb->postmeta} pm2 ON pm1.post_id = pm2.post_id 
                INNER JOIN {$wpdb->posts} p ON pm1.post_id = p.ID
                WHERE pm1.meta_key = 'pf_rating_sum' 
                AND pm2.meta_key = 'pf_rating_count'
                AND p.post_type = 'workflows'
                AND pm2.meta_value > 0
            ) as ratings
        ");
        
        return [
            'workflows' => [
                'total' => $workflow_count->publish ?? 0,
                'drafts' => $workflow_count->draft ?? 0,
            ],
            'users' => [
                'total' => $user_count['total_users'] ?? 0,
            ],
            'ratings' => [
                'average' => $avg_rating ? round($avg_rating, 1) : 0,
            ],
            'timestamp' => current_time('mysql'),
        ];
    }
    
    /**
     * Export workflow data
     * 
     * @since 1.0.0
     * @return array Export data
     */
    private function export_workflow_data(): array {
        $workflows = get_posts([
            'post_type' => 'workflows',
            'posts_per_page' => -1,
            'post_status' => 'publish'
        ]);
        
        $export_data = [];
        foreach ($workflows as $workflow) {
            $export_data[] = [
                'id' => $workflow->ID,
                'title' => $workflow->post_title,
                'slug' => $workflow->post_name,
                'date' => $workflow->post_date,
                'url' => get_permalink($workflow->ID),
                'rating_sum' => get_post_meta($workflow->ID, 'pf_rating_sum', true),
                'rating_count' => get_post_meta($workflow->ID, 'pf_rating_count', true),
                'usage_count' => get_post_meta($workflow->ID, 'pf_usage_count', true),
            ];
        }
        
        return [
            'workflows' => $export_data,
            'exported_at' => current_time('mysql'),
            'total_count' => count($export_data),
        ];
    }
}

// Initialize the plugin
new PromptFinderCore();

// Export function for external integrations
function pf_core_export_workflow_data($workflow_id) {
    $workflow = get_post($workflow_id);
    if (!$workflow || $workflow->post_type !== 'workflows') return null;
    
    $steps = get_field('steps', $workflow_id);
    $analytics = pf_core_get_workflow_analytics($workflow_id);
    
    return array(
        'id' => $workflow->ID,
        'title' => $workflow->post_title,
        'slug' => $workflow->post_name,
        'summary' => get_field('Summary', $workflow_id),
        'access_mode' => get_field('access_mode', $workflow_id),
        'steps' => $steps,
        'step_count' => is_array($steps) ? count($steps) : 0,
        'version' => get_field('Version', $workflow_id),
        'last_updated' => get_field('lastest_update', $workflow_id),
        'analytics' => $analytics,
        'rating' => array(
            'sum' => get_post_meta($workflow_id, 'pf_rating_sum', true),
            'count' => get_post_meta($workflow_id, 'pf_rating_count', true),
            'average' => pf_core_get_workflow_rating($workflow_id)['average']
        ),
        'url' => get_permalink($workflow_id),
        'created_at' => $workflow->post_date,
        'modified_at' => $workflow->post_modified
    );
}

?>