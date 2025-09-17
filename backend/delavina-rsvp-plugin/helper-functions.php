<?php
/**
 * Helper Functions for Wedding RSVP System
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Create a new party with guests
 */
function create_party_with_guests($guest_data) {
    
    // Validate required fields
    if (empty($guest_data['name'])) {
        return new WP_Error('missing_required_fields', 'Primary guest name is required');
    }

    // First create the party
    $party_size = !empty($guest_data['plus_one_name']) ? 2 : 1;
    
    $party_data = array(
        'post_type' => 'party',
        'post_status' => 'publish',
        'post_title' => trim($guest_data['name']) . (!empty($guest_data['plus_one_name']) ? ' & ' . trim($guest_data['plus_one_name']) : ''),
    );

    $party_id = wp_insert_post($party_data);

    if (is_wp_error($party_id)) {
        return $party_id;
    }

    // Set party fields
    update_field('party_size_total', $party_size, $party_id);
    update_field('rsvp_status', 'pending', $party_id);

    // Create primary guest
    $primary_guest_data = array(
        'post_type' => 'guest',
        'post_status' => 'publish',
        'post_title' => trim($guest_data['name']),
    );

    $primary_guest_id = wp_insert_post($primary_guest_data);

    if (is_wp_error($primary_guest_id)) {
        wp_delete_post($party_id, true);
        return $primary_guest_id;
    }

    // Link primary guest to party
    update_field('name', sanitize_text_field($guest_data['name']), $primary_guest_id);
    update_field('party', $party_id, $primary_guest_id);
    update_field('is_primary_contact', true, $primary_guest_id);
    
    if (!empty($guest_data['email'])) {
        update_field('email', sanitize_email($guest_data['email']), $primary_guest_id);
    }

    // Create plus one guest if exists
    if (!empty($guest_data['plus_one_name'])) {
        $plus_one_data = array(
            'post_type' => 'guest',
            'post_status' => 'publish',
            'post_title' => trim($guest_data['plus_one_name']),
        );

        $plus_one_id = wp_insert_post($plus_one_data);

        if (!is_wp_error($plus_one_id)) {
            update_field('name', sanitize_text_field($guest_data['plus_one_name']), $plus_one_id);
            update_field('party', $party_id, $plus_one_id);
            update_field('is_primary_contact', false, $plus_one_id);
        }
    }

    return $party_id;
}

/**
 * Create a new guest (legacy function for compatibility)
 */
function create_guest($guest_data) {
    return create_party_with_guests($guest_data);
}

/**
 * Bulk import guests from array - creates parties with guests
 */
function bulk_import_guests($guests_array) {
    $results = array(
        'success' => 0,
        'errors' => array(),
        'created_ids' => array()
    );

    foreach ($guests_array as $index => $guest_data) {
        $result = create_party_with_guests($guest_data);
        
        if (is_wp_error($result)) {
            $results['errors'][] = array(
                'index' => $index,
                'data' => $guest_data,
                'error' => $result->get_error_message()
            );
        } else {
            $results['success']++;
            $results['created_ids'][] = $result;
        }
    }

    return $results;
}

/**
 * Search guests by name - returns guests with their party info
 */
function search_guests_by_name($search_term, $limit = 20) {
    
    if (empty($search_term)) {
        return array();
    }

    $search_term = sanitize_text_field($search_term);
    
    // Search by guest name in new structure
    $args = array(
        'post_type' => 'guest',
        'post_status' => 'publish',
        'posts_per_page' => $limit,
        'meta_query' => array(
            array(
                'key' => 'name',
                'value' => $search_term,
                'compare' => 'LIKE'
            )
        )
    );

    $guests = get_posts($args);
    
    // Enhance guests with party information
    foreach ($guests as &$guest) {
        $party = get_field('party', $guest->ID);
        $guest->party_info = $party;
        
        if ($party) {
            // Get all guests in this party for display
            $party_guests = get_posts([
                'post_type' => 'guest',
                'post_status' => 'publish',
                'posts_per_page' => -1,
                'meta_query' => [
                    [
                        'key' => 'party',
                        'value' => $party->ID,
                        'compare' => '='
                    ]
                ]
            ]);
            
            $names = [];
            foreach ($party_guests as $pg) {
                $name = get_field('name', $pg->ID);
                if (!empty($name)) {
                    $names[] = trim($name);
                }
            }
            
            $guest->party_names = implode(' & ', $names);
        }
    }

    return $guests;
}

/**
 * Get guest with all details including party information
 */
function get_guest_details($guest_id) {
    
    $guest = get_post($guest_id);
    
    if (!$guest || $guest->post_type !== 'guest') {
        return null;
    }

    // Get the party this guest belongs to
    $party = get_field('party', $guest->ID);
    
    $details = array(
        'id' => $guest->ID,
        'name' => get_field('name', $guest->ID),
        'full_name' => $guest->post_title,
        'email' => get_field('email', $guest->ID),
        'is_primary_contact' => get_field('is_primary_contact', $guest->ID),
        'party_id' => $party ? $party->ID : null,
        'party_size_total' => $party ? get_field('party_size_total', $party->ID) : 1,
        'rsvp_status' => $party ? get_field('rsvp_status', $party->ID) : 'pending',
        'party_size_attending' => $party ? get_field('party_size_attending', $party->ID) : null,
        'dietary_requirements' => $party ? get_field('dietary_requirements', $party->ID) : null,
        'additional_notes' => $party ? get_field('additional_notes', $party->ID) : null,
        'rsvp_submitted_date' => $party ? get_field('rsvp_submitted_date', $party->ID) : null,
        'has_submitted_rsvp' => $party ? (!empty(get_field('rsvp_status', $party->ID)) && get_field('rsvp_status', $party->ID) !== 'pending') : false
    );

    return $details;
}

/**
 * Update party RSVP via guest
 */
function update_guest_rsvp($guest_id, $rsvp_data) {
    
    // Validate guest exists
    $guest = get_post($guest_id);
    if (!$guest || $guest->post_type !== 'guest') {
        return new WP_Error('invalid_guest', 'Guest not found');
    }

    // Get the party this guest belongs to
    $party = get_field('party', $guest_id);
    if (!$party) {
        return new WP_Error('no_party', 'Guest is not associated with a party');
    }

    // Validate RSVP status
    if (empty($rsvp_data['rsvp_status']) || !in_array($rsvp_data['rsvp_status'], ['attending', 'declined'])) {
        return new WP_Error('invalid_status', 'Valid RSVP status is required');
    }

    try {
        // Update RSVP status and date on the party
        update_field('rsvp_status', $rsvp_data['rsvp_status'], $party->ID);
        update_field('rsvp_submitted_date', current_time('Y-m-d H:i:s'), $party->ID);

        // Update attending-specific fields
        if ($rsvp_data['rsvp_status'] === 'attending') {
            if (isset($rsvp_data['party_size_attending'])) {
                $party_size_total = get_field('party_size_total', $party->ID) ?: 1;
                $party_size = max(0, min($party_size_total, intval($rsvp_data['party_size_attending'])));
                update_field('party_size_attending', $party_size, $party->ID);
            }
            
            if (isset($rsvp_data['dietary_requirements'])) {
                update_field('dietary_requirements', sanitize_textarea_field($rsvp_data['dietary_requirements']), $party->ID);
            }
        }

        // Update additional notes
        if (isset($rsvp_data['additional_notes'])) {
            update_field('additional_notes', sanitize_textarea_field($rsvp_data['additional_notes']), $party->ID);
        }

        return true;

    } catch (Exception $e) {
        return new WP_Error('update_failed', 'Failed to update RSVP: ' . $e->getMessage());
    }
}

/**
 * Get RSVP statistics based on parties
 */
function get_rsvp_statistics() {
    
    $stats = array(
        'total_guests' => 0,
        'total_invited' => 0,
        'pending_responses' => 0,
        'attending' => 0,
        'declined' => 0,
        'attending_count' => 0,
        'dietary_requirements_count' => 0
    );

    // Get all parties
    $parties = get_posts(array(
        'post_type' => 'party',
        'post_status' => 'publish',
        'posts_per_page' => -1
    ));

    // Count total guests
    $guests = get_posts(array(
        'post_type' => 'guest',
        'post_status' => 'publish',
        'posts_per_page' => -1
    ));
    $stats['total_guests'] = count($guests);

    foreach ($parties as $party) {
        $party_size_total = get_field('party_size_total', $party->ID) ?: 1;
        $rsvp_status = get_field('rsvp_status', $party->ID) ?: 'pending';
        $party_size_attending = get_field('party_size_attending', $party->ID);
        $dietary_requirements = get_field('dietary_requirements', $party->ID);

        // Count total invited
        $stats['total_invited'] += $party_size_total;

        // Count RSVP responses
        switch ($rsvp_status) {
            case 'pending':
                $stats['pending_responses']++;
                break;
            case 'attending':
                $stats['attending']++;
                $stats['attending_count'] += intval($party_size_attending) ?: $party_size_total;
                break;
            case 'declined':
                $stats['declined']++;
                break;
        }

        // Count dietary requirements
        if (!empty($dietary_requirements)) {
            $stats['dietary_requirements_count']++;
        }
    }

    return $stats;
}

/**
 * Export guest list to CSV format
 */
function export_guests_to_csv() {
    
    $guests = get_posts(array(
        'post_type' => 'guest',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'orderby' => 'title',
        'order' => 'ASC'
    ));

    $csv_data = array();
    
    // Headers
    $csv_data[] = array(
        'ID',
        'Name',
        'Email',
        'Has Plus One',
        'Plus One Name',
        'Party Size Total',
        'RSVP Status',
        'Party Size Attending',
        'Dietary Requirements',
        'Additional Notes',
        'RSVP Submitted Date'
    );

    // Data rows
    foreach ($guests as $guest) {
        $details = get_guest_details($guest->ID);
        
        $csv_data[] = array(
            $details['id'],
            $details['name'],
            $details['email'],
            $details['has_plus_one'] ? 'Yes' : 'No',
            $details['plus_one_name'],
            $details['party_size_total'],
            ucfirst($details['rsvp_status']),
            $details['party_size_attending'],
            $details['dietary_requirements'],
            $details['additional_notes'],
            $details['rsvp_submitted_date']
        );
    }

    return $csv_data;
}

/**
 * Validate email address format
 */
function validate_guest_email($email) {
    return empty($email) || is_email($email);
}

