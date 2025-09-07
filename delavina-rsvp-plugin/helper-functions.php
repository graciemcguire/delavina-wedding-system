<?php
/**
 * Helper Functions for Wedding RSVP System
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Create a new guest
 */
function create_guest($guest_data) {
    
    // Validate required fields
    if (empty($guest_data['first_name']) || empty($guest_data['last_name'])) {
        return new WP_Error('missing_required_fields', 'First name and last name are required');
    }

    // Create the post
    $post_data = array(
        'post_type' => 'guest',
        'post_status' => 'publish',
        'post_title' => trim($guest_data['first_name'] . ' ' . $guest_data['last_name']),
    );

    $guest_id = wp_insert_post($post_data);

    if (is_wp_error($guest_id)) {
        return $guest_id;
    }

    // Add custom fields
    update_field('first_name', sanitize_text_field($guest_data['first_name']), $guest_id);
    update_field('last_name', sanitize_text_field($guest_data['last_name']), $guest_id);
    
    if (!empty($guest_data['email'])) {
        update_field('email', sanitize_email($guest_data['email']), $guest_id);
    }
    
    if (!empty($guest_data['phone_number'])) {
        update_field('phone_number', sanitize_text_field($guest_data['phone_number']), $guest_id);
    }
    
    // Plus one information
    $has_plus_one = !empty($guest_data['plus_one_name']);
    update_field('has_plus_one', $has_plus_one, $guest_id);
    
    if ($has_plus_one) {
        update_field('plus_one_name', sanitize_text_field($guest_data['plus_one_name']), $guest_id);
    }

    // Set default RSVP status
    update_field('rsvp_status', 'pending', $guest_id);

    return $guest_id;
}

/**
 * Bulk import guests from array
 */
function bulk_import_guests($guests_array) {
    $results = array(
        'success' => 0,
        'errors' => array(),
        'created_ids' => array()
    );

    foreach ($guests_array as $index => $guest_data) {
        $result = create_guest($guest_data);
        
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
 * Search guests by name
 */
function search_guests_by_name($search_term, $limit = 20) {
    
    if (empty($search_term)) {
        return array();
    }

    $search_term = sanitize_text_field($search_term);
    
    // Search by ACF fields and post title
    $args = array(
        'post_type' => 'guest',
        'post_status' => 'publish',
        'posts_per_page' => $limit,
        'meta_query' => array(
            'relation' => 'OR',
            array(
                'key' => 'first_name',
                'value' => $search_term,
                'compare' => 'LIKE'
            ),
            array(
                'key' => 'last_name',
                'value' => $search_term,
                'compare' => 'LIKE'
            )
        ),
        's' => $search_term
    );

    return get_posts($args);
}

/**
 * Get guest with all details
 */
function get_guest_details($guest_id) {
    
    $guest = get_post($guest_id);
    
    if (!$guest || $guest->post_type !== 'guest') {
        return null;
    }

    $details = array(
        'id' => $guest->ID,
        'first_name' => get_field('first_name', $guest->ID),
        'last_name' => get_field('last_name', $guest->ID),
        'full_name' => $guest->post_title,
        'email' => get_field('email', $guest->ID),
        'phone_number' => get_field('phone_number', $guest->ID),
        'has_plus_one' => get_field('has_plus_one', $guest->ID),
        'plus_one_name' => get_field('plus_one_name', $guest->ID),
        'party_size_total' => get_field('has_plus_one', $guest->ID) ? 2 : 1,
        'rsvp_status' => get_field('rsvp_status', $guest->ID),
        'party_size_attending' => get_field('party_size_attending', $guest->ID),
        'dietary_requirements' => get_field('dietary_requirements', $guest->ID),
        'additional_notes' => get_field('additional_notes', $guest->ID),
        'rsvp_submitted_date' => get_field('rsvp_submitted_date', $guest->ID),
        'has_submitted_rsvp' => !empty(get_field('rsvp_status', $guest->ID)) && get_field('rsvp_status', $guest->ID) !== 'pending'
    );

    return $details;
}

/**
 * Update guest RSVP
 */
function update_guest_rsvp($guest_id, $rsvp_data) {
    
    // Validate guest exists
    $guest = get_post($guest_id);
    if (!$guest || $guest->post_type !== 'guest') {
        return new WP_Error('invalid_guest', 'Guest not found');
    }

    // Validate RSVP status
    if (empty($rsvp_data['rsvp_status']) || !in_array($rsvp_data['rsvp_status'], ['attending', 'declined'])) {
        return new WP_Error('invalid_status', 'Valid RSVP status is required');
    }

    try {
        // Update RSVP status and date
        update_field('rsvp_status', $rsvp_data['rsvp_status'], $guest_id);
        update_field('rsvp_submitted_date', current_time('Y-m-d H:i:s'), $guest_id);

        // Update attending-specific fields
        if ($rsvp_data['rsvp_status'] === 'attending') {
            if (isset($rsvp_data['party_size_attending'])) {
                $party_size = max(0, min(2, intval($rsvp_data['party_size_attending'])));
                update_field('party_size_attending', $party_size, $guest_id);
            }
            
            if (isset($rsvp_data['dietary_requirements'])) {
                update_field('dietary_requirements', sanitize_textarea_field($rsvp_data['dietary_requirements']), $guest_id);
            }
        }

        // Update additional notes
        if (isset($rsvp_data['additional_notes'])) {
            update_field('additional_notes', sanitize_textarea_field($rsvp_data['additional_notes']), $guest_id);
        }

        return true;

    } catch (Exception $e) {
        return new WP_Error('update_failed', 'Failed to update RSVP: ' . $e->getMessage());
    }
}

/**
 * Get RSVP statistics
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

    // Get all guests
    $guests = get_posts(array(
        'post_type' => 'guest',
        'post_status' => 'publish',
        'posts_per_page' => -1
    ));

    $stats['total_guests'] = count($guests);

    foreach ($guests as $guest) {
        $has_plus_one = get_field('has_plus_one', $guest->ID);
        $rsvp_status = get_field('rsvp_status', $guest->ID);
        $party_size_attending = get_field('party_size_attending', $guest->ID);
        $dietary_requirements = get_field('dietary_requirements', $guest->ID);

        // Count total invited (including plus ones)
        $stats['total_invited'] += $has_plus_one ? 2 : 1;

        // Count RSVP responses
        switch ($rsvp_status) {
            case 'pending':
                $stats['pending_responses']++;
                break;
            case 'attending':
                $stats['attending']++;
                $stats['attending_count'] += intval($party_size_attending) ?: 1;
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
        'First Name',
        'Last Name',
        'Email',
        'Phone Number',
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
            $details['first_name'],
            $details['last_name'],
            $details['email'],
            $details['phone_number'],
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

/**
 * Sanitize phone number
 */
function sanitize_phone_number($phone) {
    // Remove all non-numeric characters except + and -
    return preg_replace('/[^0-9+\-\s\(\)]/', '', $phone);
}