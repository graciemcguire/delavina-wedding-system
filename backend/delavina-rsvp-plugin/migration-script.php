<?php
/**
 * Migration Script for Wedding RSVP System
 * Converts old guest-based RSVP structure to new party-based structure
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Migrate old guest structure to new party structure
 */
function migrate_guests_to_parties() {
    
    // Get all existing guests that don't have party assignments yet
    $guests = get_posts(array(
        'post_type' => 'guest',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'meta_query' => array(
            array(
                'key' => 'party',
                'compare' => 'NOT EXISTS'
            )
        )
    ));

    $migrated = 0;
    $errors = array();

    foreach ($guests as $guest) {
        
        try {
            // Get guest data
            $guest_name = get_field('name', $guest->ID) ?: $guest->post_title;
            $guest_email = get_field('email', $guest->ID);
            $has_plus_one = get_field('has_plus_one', $guest->ID);
            $plus_one_name = get_field('plus_one_name', $guest->ID);
            
            // Get old RSVP data if it exists
            $old_rsvp_status = get_field('rsvp_status', $guest->ID);
            $old_party_size_attending = get_field('party_size_attending', $guest->ID);
            $old_dietary_requirements = get_field('dietary_requirements', $guest->ID);
            $old_additional_notes = get_field('additional_notes', $guest->ID);
            $old_rsvp_date = get_field('rsvp_submitted_date', $guest->ID);

            // Create new party
            $party_title = $guest_name;
            if ($has_plus_one && !empty($plus_one_name)) {
                $party_title .= ' & ' . $plus_one_name;
            }

            $party_data = array(
                'post_type' => 'party',
                'post_status' => 'publish',
                'post_title' => $party_title
            );

            $party_id = wp_insert_post($party_data);

            if (is_wp_error($party_id)) {
                $errors[] = 'Failed to create party for guest ' . $guest_name . ': ' . $party_id->get_error_message();
                continue;
            }

            // Set party fields
            $party_size = $has_plus_one ? 2 : 1;
            update_field('party_size_total', $party_size, $party_id);
            
            // Migrate RSVP data to party
            if (!empty($old_rsvp_status)) {
                update_field('rsvp_status', $old_rsvp_status, $party_id);
            } else {
                update_field('rsvp_status', 'pending', $party_id);
            }
            
            if (!empty($old_party_size_attending)) {
                update_field('party_size_attending', $old_party_size_attending, $party_id);
            }
            
            if (!empty($old_dietary_requirements)) {
                update_field('dietary_requirements', $old_dietary_requirements, $party_id);
            }
            
            if (!empty($old_additional_notes)) {
                update_field('additional_notes', $old_additional_notes, $party_id);
            }
            
            if (!empty($old_rsvp_date)) {
                update_field('rsvp_submitted_date', $old_rsvp_date, $party_id);
            }

            // Update existing guest to link to party
            update_field('party', $party_id, $guest->ID);
            update_field('is_primary_contact', true, $guest->ID);

            // Clean up old fields from guest
            delete_field('has_plus_one', $guest->ID);
            delete_field('plus_one_name', $guest->ID);
            delete_field('rsvp_status', $guest->ID);
            delete_field('party_size_attending', $guest->ID);
            delete_field('dietary_requirements', $guest->ID);
            delete_field('additional_notes', $guest->ID);
            delete_field('rsvp_submitted_date', $guest->ID);

            // Create plus one guest if exists
            if ($has_plus_one && !empty($plus_one_name)) {
                $plus_one_data = array(
                    'post_type' => 'guest',
                    'post_status' => 'publish',
                    'post_title' => trim($plus_one_name)
                );

                $plus_one_id = wp_insert_post($plus_one_data);

                if (!is_wp_error($plus_one_id)) {
                    update_field('name', sanitize_text_field($plus_one_name), $plus_one_id);
                    update_field('party', $party_id, $plus_one_id);
                    update_field('is_primary_contact', false, $plus_one_id);
                }
            }

            $migrated++;

        } catch (Exception $e) {
            $errors[] = 'Error migrating guest ' . $guest_name . ': ' . $e->getMessage();
        }
    }

    return array(
        'migrated' => $migrated,
        'errors' => $errors,
        'total_guests' => count($guests)
    );
}

/**
 * Check if migration is needed
 */
function migration_needed() {
    // Check if there are guests without party assignments
    $guests_without_parties = get_posts(array(
        'post_type' => 'guest',
        'post_status' => 'publish',
        'posts_per_page' => 1,
        'meta_query' => array(
            array(
                'key' => 'party',
                'compare' => 'NOT EXISTS'
            )
        )
    ));

    return !empty($guests_without_parties);
}

/**
 * Admin notice for migration
 */
function migration_admin_notice() {
    if (migration_needed()) {
        echo '<div class="notice notice-warning"><p>';
        echo '<strong>Wedding RSVP System:</strong> Migration needed to update your guest data structure. ';
        echo '<a href="' . admin_url('admin.php?page=delavina-rsvp-migrate') . '">Run Migration</a>';
        echo '</p></div>';
    }
}

/**
 * Add migration page to admin menu
 */
function add_migration_admin_page() {
    add_submenu_page(
        'delavina-rsvp',
        'Migrate Data',
        'Migrate Data',
        'manage_options',
        'delavina-rsvp-migrate',
        'migration_admin_page'
    );
}

/**
 * Migration admin page
 */
function migration_admin_page() {
    
    if (isset($_POST['run_migration']) && wp_verify_nonce($_POST['_wpnonce'], 'run_migration')) {
        $results = migrate_guests_to_parties();
        
        echo '<div class="notice notice-success"><p>';
        echo '<strong>Migration Complete!</strong><br>';
        echo 'Migrated: ' . $results['migrated'] . ' guests<br>';
        echo 'Total processed: ' . $results['total_guests'] . '<br>';
        if (!empty($results['errors'])) {
            echo 'Errors: ' . count($results['errors']) . '<br>';
        }
        echo '</p></div>';
        
        if (!empty($results['errors'])) {
            echo '<div class="notice notice-error"><p>';
            echo '<strong>Migration Errors:</strong><br>';
            foreach ($results['errors'] as $error) {
                echo $error . '<br>';
            }
            echo '</p></div>';
        }
    }
    
    $needs_migration = migration_needed();
    
    ?>
    <div class="wrap">
        <h1>Data Migration</h1>
        
        <?php if ($needs_migration): ?>
            <div class="notice notice-warning"><p>
                <strong>Migration Required:</strong> Your guest data needs to be migrated to the new party-based structure.
            </p></div>
            
            <p>This migration will:</p>
            <ul>
                <li>Create party records for each guest</li>
                <li>Move RSVP information from guests to parties</li>
                <li>Create separate guest records for plus ones</li>
                <li>Link guests to their respective parties</li>
            </ul>
            
            <form method="post">
                <?php wp_nonce_field('run_migration'); ?>
                <p class="submit">
                    <input type="submit" name="run_migration" class="button-primary" value="Run Migration" 
                           onclick="return confirm('Are you sure you want to run the migration? This will modify your data structure.');" />
                </p>
            </form>
            
        <?php else: ?>
            <div class="notice notice-success"><p>
                <strong>No Migration Needed:</strong> Your data structure is up to date.
            </p></div>
        <?php endif; ?>
        
    </div>
    <?php
}

// Hook into admin if migration is needed
if (is_admin() && migration_needed()) {
    add_action('admin_notices', 'migration_admin_notice');
    add_action('admin_menu', 'add_migration_admin_page', 20);
}