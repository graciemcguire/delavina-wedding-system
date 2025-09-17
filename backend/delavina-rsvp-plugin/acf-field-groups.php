<?php
/**
 * ACF Field Groups for Wedding RSVP System
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register ACF Field Groups
 */
function register_guest_acf_fields() {
    if (function_exists('acf_add_local_field_group')) {
        
        // Party Information Field Group
        acf_add_local_field_group(array(
            'key' => 'group_party_information',
            'title' => 'Party Information',
            'fields' => array(
                array(
                    'key' => 'field_party_size_total',
                    'label' => 'Total Party Size',
                    'name' => 'party_size_total',
                    'type' => 'number',
                    'instructions' => 'Total number of people in this party',
                    'required' => 1,
                    'conditional_logic' => 0,
                    'wrapper' => array(
                        'width' => '50',
                        'class' => '',
                        'id' => '',
                    ),
                    'default_value' => 1,
                    'placeholder' => '',
                    'prepend' => '',
                    'append' => '',
                    'min' => 1,
                    'max' => 10,
                    'step' => 1,
                    'show_in_graphql' => 1,
                ),
            ),
            'location' => array(
                array(
                    array(
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'party',
                    ),
                ),
            ),
            'menu_order' => 0,
            'position' => 'normal',
            'style' => 'default',
            'label_placement' => 'top',
            'instruction_placement' => 'label',
            'hide_on_screen' => '',
            'active' => true,
            'description' => '',
            'show_in_graphql' => 1,
            'graphql_field_name' => 'partyInformation',
        ));

        // Party RSVP Information Field Group
        acf_add_local_field_group(array(
            'key' => 'group_party_rsvp_information',
            'title' => 'RSVP Information',
            'fields' => array(
                array(
                    'key' => 'field_party_rsvp_status',
                    'label' => 'RSVP Status',
                    'name' => 'rsvp_status',
                    'type' => 'select',
                    'instructions' => 'Current RSVP status for this party',
                    'required' => 0,
                    'conditional_logic' => 0,
                    'wrapper' => array(
                        'width' => '50',
                        'class' => '',
                        'id' => '',
                    ),
                    'choices' => array(
                        'pending' => 'Pending',
                        'attending' => 'Attending',
                        'declined' => 'Declined',
                    ),
                    'default_value' => 'pending',
                    'allow_null' => 0,
                    'multiple' => 0,
                    'ui' => 1,
                    'return_format' => 'value',
                    'ajax' => 0,
                    'placeholder' => '',
                    'show_in_graphql' => 1,
                ),
                array(
                    'key' => 'field_party_size_attending',
                    'label' => 'Party Size Attending',
                    'name' => 'party_size_attending',
                    'type' => 'number',
                    'instructions' => 'How many people in this party are attending?',
                    'required' => 0,
                    'conditional_logic' => array(
                        array(
                            array(
                                'field' => 'field_party_rsvp_status',
                                'operator' => '==',
                                'value' => 'attending',
                            ),
                        ),
                    ),
                    'wrapper' => array(
                        'width' => '50',
                        'class' => '',
                        'id' => '',
                    ),
                    'default_value' => 1,
                    'placeholder' => '',
                    'prepend' => '',
                    'append' => '',
                    'min' => 0,
                    'max' => 10,
                    'step' => 1,
                    'show_in_graphql' => 1,
                ),
                array(
                    'key' => 'field_party_dietary_requirements',
                    'label' => 'Dietary Requirements',
                    'name' => 'dietary_requirements',
                    'type' => 'textarea',
                    'instructions' => 'Any dietary requirements or allergies for the party?',
                    'required' => 0,
                    'conditional_logic' => array(
                        array(
                            array(
                                'field' => 'field_party_rsvp_status',
                                'operator' => '==',
                                'value' => 'attending',
                            ),
                        ),
                    ),
                    'wrapper' => array(
                        'width' => '',
                        'class' => '',
                        'id' => '',
                    ),
                    'default_value' => '',
                    'placeholder' => '',
                    'maxlength' => '',
                    'rows' => 3,
                    'new_lines' => '',
                    'show_in_graphql' => 1,
                ),
                array(
                    'key' => 'field_party_rsvp_submitted_date',
                    'label' => 'RSVP Submitted Date',
                    'name' => 'rsvp_submitted_date',
                    'type' => 'date_time_picker',
                    'instructions' => 'When was the RSVP submitted?',
                    'required' => 0,
                    'conditional_logic' => 0,
                    'wrapper' => array(
                        'width' => '',
                        'class' => '',
                        'id' => '',
                    ),
                    'display_format' => 'F j, Y g:i a',
                    'return_format' => 'Y-m-d H:i:s',
                    'first_day' => 1,
                    'show_in_graphql' => 1,
                ),
                array(
                    'key' => 'field_party_additional_notes',
                    'label' => 'Additional Notes',
                    'name' => 'additional_notes',
                    'type' => 'textarea',
                    'instructions' => 'Any additional notes from the party',
                    'required' => 0,
                    'conditional_logic' => 0,
                    'wrapper' => array(
                        'width' => '',
                        'class' => '',
                        'id' => '',
                    ),
                    'default_value' => '',
                    'placeholder' => '',
                    'maxlength' => '',
                    'rows' => 3,
                    'new_lines' => '',
                    'show_in_graphql' => 1,
                ),
            ),
            'location' => array(
                array(
                    array(
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'party',
                    ),
                ),
            ),
            'menu_order' => 1,
            'position' => 'normal',
            'style' => 'default',
            'label_placement' => 'top',
            'instruction_placement' => 'label',
            'hide_on_screen' => '',
            'active' => true,
            'description' => '',
            'show_in_graphql' => 1,
            'graphql_field_name' => 'partyRsvpInformation',
        ));
        
        // Guest Information Field Group
        acf_add_local_field_group(array(
            'key' => 'group_guest_information',
            'title' => 'Guest Information',
            'fields' => array(
                array(
                    'key' => 'field_guest_name',
                    'label' => 'Name',
                    'name' => 'name',
                    'type' => 'text',
                    'instructions' => 'Guest\'s full name',
                    'required' => 1,
                    'conditional_logic' => 0,
                    'wrapper' => array(
                        'width' => '50',
                        'class' => '',
                        'id' => '',
                    ),
                    'default_value' => '',
                    'placeholder' => '',
                    'prepend' => '',
                    'append' => '',
                    'maxlength' => '',
                    'show_in_graphql' => 1,
                ),
                array(
                    'key' => 'field_guest_email',
                    'label' => 'Email',
                    'name' => 'email',
                    'type' => 'email',
                    'instructions' => 'Guest\'s email address',
                    'required' => 0,
                    'conditional_logic' => 0,
                    'wrapper' => array(
                        'width' => '50',
                        'class' => '',
                        'id' => '',
                    ),
                    'default_value' => '',
                    'placeholder' => '',
                    'prepend' => '',
                    'append' => '',
                    'show_in_graphql' => 1,
                ),
                array(
                    'key' => 'field_guest_party',
                    'label' => 'Party',
                    'name' => 'party',
                    'type' => 'post_object',
                    'instructions' => 'Which party does this guest belong to?',
                    'required' => 1,
                    'conditional_logic' => 0,
                    'wrapper' => array(
                        'width' => '50',
                        'class' => '',
                        'id' => '',
                    ),
                    'post_type' => array(
                        0 => 'party',
                    ),
                    'taxonomy' => '',
                    'allow_null' => 0,
                    'multiple' => 0,
                    'return_format' => 'object',
                    'ui' => 1,
                    'show_in_graphql' => 1,
                ),
                array(
                    'key' => 'field_guest_is_primary',
                    'label' => 'Is Primary Contact',
                    'name' => 'is_primary_contact',
                    'type' => 'true_false',
                    'instructions' => 'Is this guest the primary contact for the party?',
                    'required' => 0,
                    'conditional_logic' => 0,
                    'wrapper' => array(
                        'width' => '50',
                        'class' => '',
                        'id' => '',
                    ),
                    'message' => '',
                    'default_value' => 0,
                    'ui' => 1,
                    'ui_on_text' => 'Yes',
                    'ui_off_text' => 'No',
                    'show_in_graphql' => 1,
                ),
            ),
            'location' => array(
                array(
                    array(
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'guest',
                    ),
                ),
            ),
            'menu_order' => 0,
            'position' => 'normal',
            'style' => 'default',
            'label_placement' => 'top',
            'instruction_placement' => 'label',
            'hide_on_screen' => '',
            'active' => true,
            'description' => '',
            'show_in_graphql' => 1,
            'graphql_field_name' => 'guestInformation',
        ));


    }
}
add_action('acf/init', 'register_guest_acf_fields');