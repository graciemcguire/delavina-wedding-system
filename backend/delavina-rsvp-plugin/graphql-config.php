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
                'partyId' => [
                    'type' => 'ID',
                    'description' => 'The ID of the party submitting the RSVP'
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
                    'description' => 'Additional notes from the party'
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
                'party' => [
                    'type' => 'Party',
                    'description' => 'The updated party object'
                ]
            ],
            'mutateAndGetPayload' => function($input, $context, $info) {
                
                // Validate required fields
                if (empty($input['partyId'])) {
                    return [
                        'success' => false,
                        'message' => 'Party ID is required',
                        'party' => null
                    ];
                }

                if (empty($input['rsvpStatus']) || !in_array($input['rsvpStatus'], ['attending', 'declined'])) {
                    return [
                        'success' => false,
                        'message' => 'Valid RSVP status is required (attending or declined)',
                        'party' => null
                    ];
                }

                // Get the party post
                $party_id = intval($input['partyId']);
                $party_post = get_post($party_id);

                if (!$party_post || $party_post->post_type !== 'party') {
                    return [
                        'success' => false,
                        'message' => 'Party not found',
                        'party' => null
                    ];
                }

                try {
                    // Update RSVP fields on the party
                    update_field('rsvp_status', $input['rsvpStatus'], $party_id);
                    update_field('rsvp_submitted_date', current_time('Y-m-d H:i:s'), $party_id);

                    if ($input['rsvpStatus'] === 'attending') {
                        $party_size = isset($input['partySizeAttending']) ? intval($input['partySizeAttending']) : 1;
                        update_field('party_size_attending', $party_size, $party_id);
                        
                        if (isset($input['dietaryRequirements'])) {
                            update_field('dietary_requirements', sanitize_textarea_field($input['dietaryRequirements']), $party_id);
                        }
                    }

                    if (isset($input['additionalNotes'])) {
                        update_field('additional_notes', sanitize_textarea_field($input['additionalNotes']), $party_id);
                    }

                    return [
                        'success' => true,
                        'message' => 'RSVP submitted successfully',
                        'party' => $party_post
                    ];

                } catch (Exception $e) {
                    return [
                        'success' => false,
                        'message' => 'Error submitting RSVP: ' . $e->getMessage(),
                        'party' => null
                    ];
                }
            }
        ]);
    }

    /**
     * Register custom queries and modify default search
     */
    public function register_custom_queries() {
        
        // Hook into WPGraphQL guest queries to enhance search
        add_filter('graphql_PostObjectsConnectionResolver_get_query_args', function($query_args, $source, $args, $context, $info) {
            
            // Only enhance guest post queries with search
            if ($query_args['post_type'] !== 'guest' || empty($args['where']['search'])) {
                return $query_args;
            }
            
            $search_term = sanitize_text_field($args['where']['search']);
            
            // Remove the default WordPress search parameter to avoid conflicts
            unset($query_args['s']);
            
            // Create meta query for ACF fields only
            $meta_query = [
                [
                    'key' => 'name',
                    'value' => $search_term,
                    'compare' => 'LIKE'
                ]
            ];
            
            // Replace any existing meta query with our search
            $query_args['meta_query'] = $meta_query;
            
            return $query_args;
        }, 10, 5);

        // Search guests by name - finds guests whose names match the search term
        register_graphql_field('RootQuery', 'searchGuests', [
            'type' => ['list_of' => 'Guest'],
            'description' => 'Search for guests by name',
            'args' => [
                'searchTerm' => [
                    'type' => 'String',
                    'description' => 'The name to search for'
                ]
            ],
            'resolve' => function($source, $args, $context, $info) {
                
                if (empty($args['searchTerm'])) {
                    return [];
                }

                $search_term = sanitize_text_field($args['searchTerm']);
                
                $guest_args = [
                    'post_type' => 'guest',
                    'post_status' => 'publish',
                    'posts_per_page' => 20,
                    'meta_query' => [
                        [
                            'key' => 'name',
                            'value' => $search_term,
                            'compare' => 'LIKE'
                        ]
                    ]
                ];

                $results = get_posts($guest_args);
                
                // Force convert to proper GraphQL objects
                $graphql_objects = [];
                foreach ($results as $post) {
                    if ($post && $post->post_type === 'guest') {
                        // Let WPGraphQL handle the conversion
                        $graphql_objects[] = new \WPGraphQL\Model\Post($post);
                    }
                }
                
                return $graphql_objects;
            }
        ]);

        // Quick check if parties exist
        register_graphql_field('RootQuery', 'checkParties', [
            'type' => 'String',
            'description' => 'Check if parties exist',
            'resolve' => function($source, $args, $context, $info) {
                $parties = get_posts([
                    'post_type' => 'party',
                    'post_status' => 'publish',
                    'posts_per_page' => 5
                ]);
                
                return sprintf('Found %d parties: %s', 
                    count($parties),
                    implode(', ', array_map(function($p) { return $p->post_title; }, $parties))
                );
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

// Add relationship between Guest and Party in GraphQL
add_action('graphql_register_types', function() {
    register_graphql_connection([
        'fromType' => 'Party',
        'toType' => 'Guest',
        'fromFieldName' => 'partyGuests',
        'connectionArgs' => [
            'party' => [
                'type' => 'ID',
                'description' => 'Filter guests by party ID'
            ]
        ],
        'resolve' => function($source, $args, $context, $info) {
            $resolver = new \WPGraphQL\Data\Connection\PostObjectConnectionResolver($source, $args, $context, $info, 'guest');
            
            $resolver->set_query_arg('meta_query', [
                [
                    'key' => 'party',
                    'value' => $source->ID,
                    'compare' => '='
                ]
            ]);
            
            return $resolver->get_connection();
        }
    ]);
});

/**
 * Add custom fields to GraphQL Guest type
 */
function add_guest_computed_fields() {
    
    // Add full name field
    register_graphql_field('Guest', 'fullName', [
        'type' => 'String',
        'description' => 'The guest\'s full name',
        'resolve' => function($post, $args, $context, $info) {
            $name = get_field('name', $post->ID);
            return trim($name);
        }
    ]);

    // Add party details field - connects guest to their party
    register_graphql_field('Guest', 'partyDetails', [
        'type' => 'Party',
        'description' => 'The party this guest belongs to',
        'resolve' => function($post, $args, $context, $info) {
            // Try multiple ways to get the party
            $party = get_field('party', $post->ID);
            
            // If ACF doesn't work, try direct meta
            if (!$party) {
                $party_id = get_post_meta($post->ID, 'party', true);
                if ($party_id) {
                    $party = get_post($party_id);
                }
            }
            
            error_log('Guest ' . $post->ID . ' party: ' . ($party ? $party->ID : 'NULL'));
            
            if ($party && is_object($party) && $party->post_type === 'party') {
                // Convert to GraphQL model object
                return new \WPGraphQL\Model\Post($party);
            }
            return null;
        }
    ]);
    
    // Debug field to check party relationship
    register_graphql_field('Guest', 'debugParty', [
        'type' => 'String',
        'description' => 'Debug party relationship',
        'resolve' => function($post, $args, $context, $info) {
            $party = get_field('party', $post->ID);
            $party_id = get_post_meta($post->ID, 'party', true);
            return sprintf('ACF party: %s, Meta party: %s', 
                $party ? 'Found (' . $party->ID . ')' : 'NULL',
                $party_id ? $party_id : 'NULL'
            );
        }
    ]);

    // Add party size total field
    register_graphql_field('Guest', 'partySizeTotal', [
        'type' => 'Int',
        'description' => 'Total party size',
        'resolve' => function($post, $args, $context, $info) {
            $party = get_field('party', $post->ID);
            if ($party) {
                return get_field('party_size_total', $party->ID) ?: 1;
            }
            return 1;
        }
    ]);

    // Add RSVP submission status
    register_graphql_field('Guest', 'hasSubmittedRSVP', [
        'type' => 'Boolean',
        'description' => 'Whether the party has submitted their RSVP',
        'resolve' => function($post, $args, $context, $info) {
            $party = get_field('party', $post->ID);
            if ($party) {
                $rsvp_status = get_field('rsvp_status', $party->ID);
                return !empty($rsvp_status) && $rsvp_status !== 'pending';
            }
            return false;
        }
    ]);

    // Add RSVP status field
    register_graphql_field('Guest', 'rsvpStatus', [
        'type' => 'String',
        'description' => 'RSVP status from the party',
        'resolve' => function($post, $args, $context, $info) {
            $party = get_field('party', $post->ID);
            if ($party) {
                return get_field('rsvp_status', $party->ID) ?: 'pending';
            }
            return 'pending';
        }
    ]);

    // Add party size attending field
    register_graphql_field('Guest', 'partySizeAttending', [
        'type' => 'Int',
        'description' => 'Number of people attending from the party',
        'resolve' => function($post, $args, $context, $info) {
            $party = get_field('party', $post->ID);
            if ($party) {
                return get_field('party_size_attending', $party->ID);
            }
            return null;
        }
    ]);

    // Add dietary requirements field
    register_graphql_field('Guest', 'dietaryRequirements', [
        'type' => 'String',
        'description' => 'Dietary requirements from the party',
        'resolve' => function($post, $args, $context, $info) {
            $party = get_field('party', $post->ID);
            if ($party) {
                return get_field('dietary_requirements', $party->ID);
            }
            return null;
        }
    ]);

    // Add additional notes field
    register_graphql_field('Guest', 'additionalNotes', [
        'type' => 'String',
        'description' => 'Additional notes from the party',
        'resolve' => function($post, $args, $context, $info) {
            $party = get_field('party', $post->ID);
            if ($party) {
                return get_field('additional_notes', $party->ID);
            }
            return null;
        }
    ]);

    // Add RSVP submitted date field
    register_graphql_field('Guest', 'rsvpSubmittedDate', [
        'type' => 'String',
        'description' => 'Date when RSVP was submitted',
        'resolve' => function($post, $args, $context, $info) {
            $party = get_field('party', $post->ID);
            if ($party) {
                return get_field('rsvp_submitted_date', $party->ID);
            }
            return null;
        }
    ]);
    
    // Add Party computed fields
    register_graphql_field('Party', 'partyNames', [
        'type' => 'String',
        'description' => 'Display names for all guests in the party',
        'resolve' => function($post, $args, $context, $info) {
            $guests = get_posts([
                'post_type' => 'guest',
                'post_status' => 'publish', 
                'posts_per_page' => -1,
                'meta_query' => [
                    [
                        'key' => 'party',
                        'value' => $post->ID,
                        'compare' => '='
                    ]
                ]
            ]);
            
            $names = [];
            foreach ($guests as $guest) {
                $name = get_field('name', $guest->ID);
                if (!empty($name)) {
                    $names[] = trim($name);
                }
            }
            
            return implode(' & ', $names);
        }
    ]);

    register_graphql_field('Party', 'guests', [
        'type' => ['list_of' => 'Guest'],
        'description' => 'All guests in this party',
        'resolve' => function($post, $args, $context, $info) {
            $guests = get_posts([
                'post_type' => 'guest',
                'post_status' => 'publish',
                'posts_per_page' => -1,
                'meta_query' => [
                    [
                        'key' => 'party',
                        'value' => $post->ID,
                        'compare' => '='
                    ]
                ]
            ]);
            
            // Convert to GraphQL model objects
            $graphql_guests = [];
            foreach ($guests as $guest) {
                if ($guest && $guest->post_type === 'guest') {
                    $graphql_guests[] = new \WPGraphQL\Model\Post($guest);
                }
            }
            
            return $graphql_guests;
        }
    ]);

    register_graphql_field('Party', 'hasSubmittedRSVP', [
        'type' => 'Boolean',
        'description' => 'Whether the party has submitted their RSVP',
        'resolve' => function($post, $args, $context, $info) {
            $rsvp_status = get_field('rsvp_status', $post->ID);
            return !empty($rsvp_status) && $rsvp_status !== 'pending';
        }
    ]);
}
add_action('graphql_register_types', 'add_guest_computed_fields');