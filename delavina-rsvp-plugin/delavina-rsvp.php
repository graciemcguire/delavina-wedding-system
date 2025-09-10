<?php
/**
 * Plugin Name: Delavina Wedding RSVP System
 * Plugin URI: https://delavina.com
 * Description: A complete wedding RSVP system with guest management and GraphQL API for headless WordPress
 * Version: 2.0.0
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
        add_action('init', array($this, 'init'), 5);
        add_action('admin_notices', array($this, 'check_dependencies'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // Load files immediately so post type registration can happen on init
        $this->load_files();
    }

    /**
     * Initialize the plugin
     */
    public function init() {
        
        // Check if required plugins are active
        if (!$this->dependencies_met()) {
            return;
        }
        
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
        // Check if functions/classes exist instead of specific plugin paths
        $dependencies = array(
            'acf' => function_exists('acf'),
            'wpgraphql' => class_exists('WPGraphQL'),
            'wpgraphql_acf' => $this->check_wpgraphql_acf()
        );
        
        // Return true only if all dependencies are met
        return $dependencies['acf'] && $dependencies['wpgraphql'] && $dependencies['wpgraphql_acf'];
    }
    
    /**
     * Check for WPGraphQL ACF with multiple detection methods
     */
    private function check_wpgraphql_acf() {
        // Method 1: Check for functions
        if (function_exists('wpgraphql_acf_init') || function_exists('acf_get_field_groups')) {
            return true;
        }
        
        // Method 2: Check for classes
        if (class_exists('WPGraphQL_ACF') || class_exists('\WPGraphQL\ACF\ACF')) {
            return true;
        }
        
        // Method 3: Check if WPGraphQL has ACF support loaded
        if (class_exists('WPGraphQL') && method_exists('WPGraphQL', 'get_allowed_post_types')) {
            // If we can access GraphQL schema and ACF fields are registered
            return function_exists('acf_get_field_groups');
        }
        
        return false;
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

        // Register post type and flush rewrite rules
        if (function_exists('delavina_flush_rewrites')) {
            delavina_flush_rewrites();
        }
        
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
        delavina_handle_csv_import();
    }
    ?>
    <div class="wrap">
        <h1>Import Guests</h1>
        <p>Upload a CSV file with your guest list. Expected columns: name, email, (skip), plus_one_name</p>
        
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

/**
 * Handle CSV import
 */
function delavina_handle_csv_import() {
    if (!isset($_FILES['guest_csv']) || $_FILES['guest_csv']['error'] !== UPLOAD_ERR_OK) {
        echo '<div class="notice notice-error"><p>Please select a valid CSV file.</p></div>';
        return;
    }

    $file_path = $_FILES['guest_csv']['tmp_name'];
    $guests_data = array();
    
    // Read CSV file
    if (($handle = fopen($file_path, "r")) !== FALSE) {
        
        // Skip header row
        $header = fgetcsv($handle, 1000, ",");
        
        $row_count = 0;
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            $row_count++;
            
            // Map CSV data to our format
            // CSV format: name, email, phone (skip), plus_one_name
            $guest_data = array(
                'name' => trim($data[0]),
                'email' => !empty($data[1]) ? trim($data[1]) : '',
                'plus_one_name' => !empty($data[3]) ? trim($data[3]) : ''
            );
            
            // Skip empty names
            if (empty($guest_data['name'])) {
                continue;
            }
            
            $guests_data[] = $guest_data;
        }
        
        fclose($handle);
    } else {
        echo '<div class="notice notice-error"><p>Error: Could not read CSV file</p></div>';
        return;
    }
    
    if (empty($guests_data)) {
        echo '<div class="notice notice-error"><p>No valid guest data found in CSV file.</p></div>';
        return;
    }
    
    // Import using bulk function
    $results = bulk_import_guests($guests_data);
    
    // Display detailed results with debug info
    echo '<div class="notice notice-info"><p>';
    echo "<strong>Import Debug Info:</strong><br>";
    echo "CSV rows processed: " . count($guests_data) . "<br>";
    echo "Successful imports: " . $results['success'] . "<br>";
    echo "Errors: " . count($results['errors']) . "<br>";
    if (!empty($results['created_ids'])) {
        echo "Created post IDs: " . implode(', ', $results['created_ids']) . "<br>";
    }
    echo '</p></div>';
    
    if ($results['success'] > 0) {
        echo '<div class="notice notice-success"><p>';
        echo "Successfully imported {$results['success']} guests!";
        echo '</p></div>';
    }
    
    if (!empty($results['errors'])) {
        echo '<div class="notice notice-error"><p>';
        echo "Errors encountered: " . count($results['errors']) . " guests could not be imported.<br>";
        foreach ($results['errors'] as $i => $error) {
            echo "Error " . ($i + 1) . ": " . $error['error'] . "<br>";
            if ($i >= 2) {
                echo "... and " . (count($results['errors']) - 3) . " more errors<br>";
                break;
            }
        }
        echo '</p></div>';
    }
}