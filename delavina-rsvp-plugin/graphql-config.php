<?php
/**
 * WPGraphQL Configuration for Wedding RSVP System
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register custom GraphQL mutations and queries
 */
class WeddingRSVPGraphQL {
    
    public function __construct() {
        add_action('graphql_register_types', array($this, 'register_custom_mutations'));
        add_action('graphql_register_types', array($this, 'register_custom_queries'));
        add_filter('graphql_connection_max_query_amount', array($this, 'increase_query_limit'), 10, 2);
    }

    /**
     * Register custom mutations
     */
    public function register_custom_mutations() {
        
        // Submit RSVP Mutation
        register_graphql_mutation('submitRSVP', [
            'inputFields' => [
                'guestId' => [
                    'type' => 'ID',
                    'description' => 'The ID of the guest submitting the RSVP'
                ],
                'rsvpStatus' => [
                    'type' => 'String',
                    'description' => 'attending or declined'
                ],
                'partySizeAttending' => [
                    'type' => 'Int',
                    'description' => 'Number of people attending'
                ],
                'dietaryRequirements' => [
                    'type' => 'String',
                    'description' => 'Any dietary requirements or allergies'
                ],
                'additionalNotes' => [
                    'type' => 'String',
                    'description' => 'Additional notes from the guest'
                ]
            ],
            'outputFields' => [
                'success' => [
                    'type' => 'Boolean',
                    'description' => 'Whether the RSVP was successfully submitted'
                ],
                'message' => [
                    'type' => 'String',
                    'description' => 'Success or error message'
                ],
                'guest' => [
                    'type' => 'Guest',
                    'description' => 'The updated guest object'
                ]
            ],
            'mutateAndGetPayload' => function($input, $context, $info) {
                
                // Validate required fields
                if (empty($input['guestId'])) {
                    return [
                        'success' => false,
                        'message' => 'Guest ID is required',
                        'guest' => null
                    ];
                }

                if (empty($input['rsvpStatus']) || !in_array($input['rsvpStatus'], ['attending', 'declined'])) {
                    return [
                        'success' => false,
                        'message' => 'Valid RSVP status is required (attending or declined)',
                        'guest' => null
                    ];
                }

                // Get the guest post
                $guest_id = intval($input['guestId']);
                $guest_post = get_post($guest_id);

                if (!$guest_post || $guest_post->post_type !== 'guest') {
                    return [
                        'success' => false,
                        'message' => 'Guest not found',
                        'guest' => null
                    ];
                }

                try {
                    // Update RSVP fields
                    update_field('rsvp_status', $input['rsvpStatus'], $guest_id);
                    update_field('rsvp_submitted_date', current_time('Y-m-d H:i:s'), $guest_id);

                    if ($input['rsvpStatus'] === 'attending') {
                        $party_size = isset($input['partySizeAttending']) ? intval($input['partySizeAttending']) : 1;
                        update_field('party_size_attending', $party_size, $guest_id);
                        
                        if (isset($input['dietaryRequirements'])) {
                            update_field('dietary_requirements', sanitize_textarea_field($input['dietaryRequirements']), $guest_id);
                        }
                    }

                    if (isset($input['additionalNotes'])) {
                        update_field('additional_notes', sanitize_textarea_field($input['additionalNotes']), $guest_id);
                    }

                    return [
                        'success' => true,
                        'message' => 'RSVP submitted successfully',
                        'guest' => $guest_post
                    ];

                } catch (Exception $e) {
                    return [
                        'success' => false,
                        'message' => 'Error submitting RSVP: ' . $e->getMessage(),
                        'guest' => null
                    ];
                }
            }
        ]);
    }

    /**
     * Register custom queries
     */
    public function register_custom_queries() {
        
        // Search Guests Query
        register_graphql_field('RootQuery', 'searchGuests', [
            'type' => ['list_of' => 'Guest'],
            'description' => 'Search for guests by name',
            'args' => [
                'searchTerm' => [
                    'type' => 'String',
                    'description' => 'The search term to match against guest names'
                ]
            ],
            'resolve' => function($source, $args, $context, $info) {
                
                if (empty($args['searchTerm'])) {
                    return [];
                }

                $search_term = sanitize_text_field($args['searchTerm']);
                
                // Search by first name, last name, plus one name, or full name
                $meta_query = [
                    'relation' => 'OR',
                    [
                        'key' => 'first_name',
                        'value' => $search_term,
                        'compare' => 'LIKE'
                    ],
                    [
                        'key' => 'last_name',
                        'value' => $search_term,
                        'compare' => 'LIKE'
                    ],
                    [
                        'key' => 'plus_one_name',
                        'value' => $search_term,
                        'compare' => 'LIKE'
                    ]
                ];

                // Also search post titles (full names)
                $query_args = [
                    'post_type' => 'guest',
                    'post_status' => 'publish',
                    'posts_per_page' => 20,
                    'meta_query' => $meta_query,
                    's' => $search_term
                ];

                $guests = get_posts($query_args);
                
                return $guests;
            }
        ]);

        // Get Guest by ID with full details
        register_graphql_field('RootQuery', 'guestDetails', [
            'type' => 'Guest',
            'description' => 'Get full guest details by ID',
            'args' => [
                'id' => [
                    'type' => 'ID',
                    'description' => 'The guest ID'
                ]
            ],
            'resolve' => function($source, $args, $context, $info) {
                
                if (empty($args['id'])) {
                    return null;
                }

                $guest_id = intval($args['id']);
                $guest_post = get_post($guest_id);

                if (!$guest_post || $guest_post->post_type !== 'guest') {
                    return null;
                }

                return $guest_post;
            }
        ]);
    }

    /**
     * Increase query limit for guest searches
     */
    public function increase_query_limit($amount, $source) {
        if ($source === 'guests') {
            return 100;
        }
        return $amount;
    }
}

// Initialize the GraphQL configuration
new WeddingRSVPGraphQL();

/**
 * Add custom fields to GraphQL Guest type
 */
function add_guest_computed_fields() {
    
    // Add full name field
    register_graphql_field('Guest', 'fullName', [
        'type' => 'String',
        'description' => 'The guest\'s full name',
        'resolve' => function($post, $args, $context, $info) {
            $first_name = get_field('first_name', $post->ID);
            $last_name = get_field('last_name', $post->ID);
            return trim($first_name . ' ' . $last_name);
        }
    ]);

    // Add party display names field
    register_graphql_field('Guest', 'partyNames', [
        'type' => 'String',
        'description' => 'Display names for the entire party (guest + plus one)',
        'resolve' => function($post, $args, $context, $info) {
            $first_name = get_field('first_name', $post->ID);
            $last_name = get_field('last_name', $post->ID);
            $plus_one_name = get_field('plus_one_name', $post->ID);
            
            $names = trim($first_name . ' ' . $last_name);
            
            if (!empty($plus_one_name)) {
                $names .= ' & ' . trim($plus_one_name);
            }
            
            return $names;
        }
    ]);

    // Add party size total field
    register_graphql_field('Guest', 'partySizeTotal', [
        'type' => 'Int',
        'description' => 'Total party size (guest + plus one)',
        'resolve' => function($post, $args, $context, $info) {
            $has_plus_one = get_field('has_plus_one', $post->ID);
            return $has_plus_one ? 2 : 1;
        }
    ]);

    // Add RSVP submission status
    register_graphql_field('Guest', 'hasSubmittedRSVP', [
        'type' => 'Boolean',
        'description' => 'Whether the guest has submitted their RSVP',
        'resolve' => function($post, $args, $context, $info) {
            $rsvp_status = get_field('rsvp_status', $post->ID);
            return !empty($rsvp_status) && $rsvp_status !== 'pending';
        }
    ]);
}
add_action('graphql_register_types', 'add_guest_computed_fields');