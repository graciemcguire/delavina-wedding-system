<?php
/**
 * Plugin Name: Delavina Wedding RSVP System
 * Plugin URI: https://delavina.com
 * Description: A complete wedding RSVP system with guest management and GraphQL API for headless WordPress
 * Version: 1.0.0
 * Author: Delavina
 * License: GPL v2 or later
 * Text Domain: delavina-rsvp
 * 
 * Requires Plugins: advanced-custom-fields, wp-graphql
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('DELAVINA_RSVP_VERSION', '1.0.0');
define('DELAVINA_RSVP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('DELAVINA_RSVP_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Main Plugin Class
 */
class DellavinaRSVPPlugin {
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('admin_notices', array($this, 'check_dependencies'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }

    /**
     * Initialize the plugin
     */
    public function init() {
        
        // Check if required plugins are active
        if (!$this->dependencies_met()) {
            return;
        }

        // Load plugin files
        $this->load_files();
        
        // Load text domain
        load_plugin_textdomain('delavina-rsvp', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    /**
     * Load all plugin files
     */
    private function load_files() {
        require_once DELAVINA_RSVP_PLUGIN_DIR . 'custom-post-types.php';
        require_once DELAVINA_RSVP_PLUGIN_DIR . 'acf-field-groups.php';
        require_once DELAVINA_RSVP_PLUGIN_DIR . 'helper-functions.php';
        require_once DELAVINA_RSVP_PLUGIN_DIR . 'graphql-config.php';
    }

    /**
     * Check if all required dependencies are installed and active
     */
    private function dependencies_met() {
        $required_plugins = array(
            'advanced-custom-fields/acf.php' => 'Advanced Custom Fields',
            'wp-graphql/wp-graphql.php' => 'WPGraphQL'
        );
        
        // Check for WPGraphQL ACF - multiple possible paths
        $wpgraphql_acf_active = false;
        $acf_plugin_paths = array(
            'wp-graphql-acf/wp-graphql-acf.php',
            'wpgraphql-acf/wp-graphql-acf.php',
            'wp-graphql-for-advanced-custom-fields/wp-graphql-acf.php'
        );
        
        foreach ($acf_plugin_paths as $path) {
            if (is_plugin_active($path)) {
                $wpgraphql_acf_active = true;
                break;
            }
        }
        
        if (!$wpgraphql_acf_active) {
            $required_plugins['wp-graphql-acf'] = 'WPGraphQL for Advanced Custom Fields';
        }

        foreach ($required_plugins as $plugin_path => $plugin_name) {
            if (!is_plugin_active($plugin_path)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Display admin notices if dependencies are not met
     */
    public function check_dependencies() {
        if (!$this->dependencies_met()) {
            $required_plugins = array(
                'Advanced Custom Fields',
                'WPGraphQL', 
                'WPGraphQL for Advanced Custom Fields'
            );

            echo '<div class="notice notice-error"><p>';
            echo '<strong>Delavina Wedding RSVP System</strong> requires the following plugins to be installed and activated: ';
            echo implode(', ', $required_plugins);
            echo '</p></div>';
        }
    }

    /**
     * Plugin activation
     */
    public function activate() {
        
        // Check WordPress version
        if (version_compare(get_bloginfo('version'), '5.0', '<')) {
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die('This plugin requires WordPress version 5.0 or higher.');
        }

        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Create default options
        add_option('delavina_rsvp_version', DELAVINA_RSVP_VERSION);
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Flush rewrite rules
        flush_rewrite_rules();
    }
}

// Initialize the plugin
new DellavinaRSVPPlugin();

/**
 * Admin Menu and Dashboard
 */
function delavina_rsvp_admin_menu() {
    add_menu_page(
        'Wedding RSVP',
        'Wedding RSVP', 
        'manage_options',
        'delavina-rsvp',
        'delavina_rsvp_dashboard_page',
        'dashicons-heart',
        26
    );

    add_submenu_page(
        'delavina-rsvp',
        'RSVP Dashboard',
        'Dashboard',
        'manage_options',
        'delavina-rsvp',
        'delavina_rsvp_dashboard_page'
    );

    add_submenu_page(
        'delavina-rsvp',
        'Import Guests',
        'Import Guests',
        'manage_options',
        'delavina-rsvp-import',
        'delavina_rsvp_import_page'
    );
}
add_action('admin_menu', 'delavina_rsvp_admin_menu');

/**
 * Dashboard page
 */
function delavina_rsvp_dashboard_page() {
    $stats = get_rsvp_statistics();
    ?>
    <div class="wrap">
        <h1>Wedding RSVP Dashboard</h1>
        
        <div class="card-container" style="display: flex; gap: 20px; margin: 20px 0;">
            <div class="card" style="background: white; padding: 20px; border: 1px solid #ddd; border-radius: 4px; min-width: 200px;">
                <h3>Total Guests</h3>
                <p style="font-size: 24px; font-weight: bold; color: #0073aa;"><?php echo $stats['total_guests']; ?></p>
            </div>
            
            <div class="card" style="background: white; padding: 20px; border: 1px solid #ddd; border-radius: 4px; min-width: 200px;">
                <h3>Total Invited</h3>
                <p style="font-size: 24px; font-weight: bold; color: #0073aa;"><?php echo $stats['total_invited']; ?></p>
            </div>
            
            <div class="card" style="background: white; padding: 20px; border: 1px solid #ddd; border-radius: 4px; min-width: 200px;">
                <h3>Attending</h3>
                <p style="font-size: 24px; font-weight: bold; color: #46b450;"><?php echo $stats['attending_count']; ?></p>
            </div>
            
            <div class="card" style="background: white; padding: 20px; border: 1px solid #ddd; border-radius: 4px; min-width: 200px;">
                <h3>Pending</h3>
                <p style="font-size: 24px; font-weight: bold; color: #ffb900;"><?php echo $stats['pending_responses']; ?></p>
            </div>
            
            <div class="card" style="background: white; padding: 20px; border: 1px solid #ddd; border-radius: 4px; min-width: 200px;">
                <h3>Declined</h3>
                <p style="font-size: 24px; font-weight: bold; color: #dc3232;"><?php echo $stats['declined']; ?></p>
            </div>
        </div>

        <div style="margin-top: 30px;">
            <a href="<?php echo admin_url('edit.php?post_type=guest'); ?>" class="button button-primary">
                Manage Guests
            </a>
            <a href="<?php echo admin_url('admin.php?page=delavina-rsvp-import'); ?>" class="button">
                Import Guests
            </a>
        </div>

        <div style="margin-top: 30px;">
            <h2>GraphQL Endpoint</h2>
            <p>Your GraphQL endpoint for the React frontend:</p>
            <code><?php echo home_url('/graphql'); ?></code>
        </div>
    </div>
    <?php
}

/**
 * Import page
 */
function delavina_rsvp_import_page() {
    if (isset($_POST['import_guests']) && wp_verify_nonce($_POST['_wpnonce'], 'import_guests')) {
        // Handle CSV import here
        echo '<div class="notice notice-success"><p>Import functionality will be implemented based on your specific CSV format.</p></div>';
    }
    ?>
    <div class="wrap">
        <h1>Import Guests</h1>
        <p>Upload a CSV file with your guest list. Required columns: first_name, last_name, email, phone_number, plus_one_name (optional)</p>
        
        <form method="post" enctype="multipart/form-data">
            <?php wp_nonce_field('import_guests'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row">CSV File</th>
                    <td><input type="file" name="guest_csv" accept=".csv" /></td>
                </tr>
            </table>
            <p class="submit">
                <input type="submit" name="import_guests" class="button-primary" value="Import Guests" />
            </p>
        </form>
    </div>
    <?php
}