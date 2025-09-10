# Delavina Wedding RSVP System

A complete WordPress plugin for managing wedding guests and RSVPs with a GraphQL API designed for headless WordPress implementations.

## Features

- **Guest Management**: Add and manage wedding guests with contact information
- **Plus One Support**: Track which guests have plus ones
- **RSVP System**: Guests can search their name and submit RSVP responses
- **GraphQL API**: Complete API for React frontend integration
- **Admin Dashboard**: View RSVP statistics and manage guests
- **Import/Export**: Bulk import guests and export responses

## Required Plugins

This plugin requires the following WordPress plugins to be installed and activated:

1. **Advanced Custom Fields** (ACF)
2. **WPGraphQL** 
3. **WPGraphQL for Advanced Custom Fields**

## Installation

### 1. Install Required Plugins

Install and activate the required plugins from the WordPress plugin directory:

```bash
# Via WP-CLI
wp plugin install advanced-custom-fields --activate
wp plugin install wp-graphql --activate
wp plugin install wp-graphql-acf --activate
```

### 2. Install the RSVP Plugin

1. Upload all plugin files to `/wp-content/plugins/delavina-rsvp/`
2. Activate the plugin through the WordPress admin
3. The plugin will create the necessary custom post types and fields

### 3. Plugin Files Structure

```
delavina-rsvp/
├── delavina-rsvp.php          # Main plugin file
├── custom-post-types.php      # Guest post type registration
├── acf-field-groups.php       # ACF field definitions
├── graphql-config.php         # GraphQL schema and mutations
├── helper-functions.php       # Utility functions
└── README.md                  # This file
```

## GraphQL API

### Endpoint

Your GraphQL endpoint will be available at:
```
https://yoursite.com/graphql
```

### Key Queries

#### Search Guests
```graphql
query SearchGuests($searchTerm: String!) {
  searchGuests(searchTerm: $searchTerm) {
    id
    title
    guestInformation {
      firstName
      lastName
      email
      phoneNumber
    }
    plusOneInformation {
      hasPlusOne
      plusOneName
    }
    rsvpInformation {
      rsvpStatus
      rsvpSubmittedDate
    }
    fullName
    partySizeTotal
    hasSubmittedRSVP
  }
}
```

#### Get Guest Details
```graphql
query GetGuestDetails($id: ID!) {
  guestDetails(id: $id) {
    id
    title
    guestInformation {
      firstName
      lastName
      email
      phoneNumber
    }
    plusOneInformation {
      hasPlusOne
      plusOneName
    }
    rsvpInformation {
      rsvpStatus
      partySizeAttending
      dietaryRequirements
      additionalNotes
      rsvpSubmittedDate
    }
    fullName
    partySizeTotal
    hasSubmittedRSVP
  }
}
```

### Key Mutations

#### Submit RSVP
```graphql
mutation SubmitRSVP($input: SubmitRsvpInput!) {
  submitRSVP(input: $input) {
    success
    message
    guest {
      id
      title
      rsvpInformation {
        rsvpStatus
        partySizeAttending
        rsvpSubmittedDate
      }
    }
  }
}
```

**Input Variables:**
```json
{
  "input": {
    "guestId": "123",
    "rsvpStatus": "attending",
    "partySizeAttending": 2,
    "dietaryRequirements": "Vegetarian",
    "additionalNotes": "Looking forward to it!"
  }
}
```

## React Frontend Integration

### Example: Guest Search Component

```jsx
import { useState } from 'react';
import { gql, useLazyQuery } from '@apollo/client';

const SEARCH_GUESTS = gql`
  query SearchGuests($searchTerm: String!) {
    searchGuests(searchTerm: $searchTerm) {
      id
      fullName
      plusOneInformation {
        hasPlusOne
        plusOneName
      }
      hasSubmittedRSVP
    }
  }
`;

function GuestSearch() {
  const [searchTerm, setSearchTerm] = useState('');
  const [searchGuests, { data, loading }] = useLazyQuery(SEARCH_GUESTS);

  const handleSearch = () => {
    if (searchTerm.length > 2) {
      searchGuests({ variables: { searchTerm } });
    }
  };

  return (
    <div>
      <input 
        value={searchTerm}
        onChange={(e) => setSearchTerm(e.target.value)}
        onKeyUp={handleSearch}
        placeholder="Search your name..."
      />
      
      {loading && <p>Searching...</p>}
      
      {data?.searchGuests?.map(guest => (
        <div key={guest.id} className="guest-result">
          <h3>{guest.fullName}</h3>
          {guest.plusOneInformation.hasPlusOne && (
            <p>Plus One: {guest.plusOneInformation.plusOneName}</p>
          )}
          {guest.hasSubmittedRSVP && <span>✓ RSVP Submitted</span>}
        </div>
      ))}
    </div>
  );
}
```

### Example: RSVP Form Component

```jsx
import { gql, useMutation } from '@apollo/client';

const SUBMIT_RSVP = gql`
  mutation SubmitRSVP($input: SubmitRsvpInput!) {
    submitRSVP(input: $input) {
      success
      message
    }
  }
`;

function RSVPForm({ guestId, partySizeTotal }) {
  const [submitRSVP, { loading }] = useMutation(SUBMIT_RSVP);
  
  const handleSubmit = async (formData) => {
    try {
      const { data } = await submitRSVP({
        variables: {
          input: {
            guestId,
            rsvpStatus: formData.attending ? 'attending' : 'declined',
            partySizeAttending: formData.partySizeAttending,
            dietaryRequirements: formData.dietary,
            additionalNotes: formData.notes
          }
        }
      });
      
      if (data.submitRSVP.success) {
        alert('RSVP submitted successfully!');
      }
    } catch (error) {
      console.error('RSVP submission failed:', error);
    }
  };

  // Form implementation here...
}
```

## WordPress Admin

### Dashboard
Access the RSVP dashboard at: `WordPress Admin > Wedding RSVP > Dashboard`

The dashboard shows:
- Total guests and invitations
- RSVP response statistics
- Attendance counts
- Quick links to manage guests

### Managing Guests
- Add guests individually through `Wedding RSVP > Guests > Add New`
- Bulk import guests via CSV (implement as needed)
- View and edit guest details
- Export guest lists and RSVP responses

## Guest Data Structure

Each guest has the following fields:

**Basic Information:**
- First Name (required)
- Last Name (required)
- Email (optional)
- Phone Number (optional)

**Plus One:**
- Has Plus One (yes/no)
- Plus One Name (if applicable)

**RSVP Information:**
- RSVP Status (pending/attending/declined)
- Party Size Attending (0-2)
- Dietary Requirements (text)
- Additional Notes (text)
- RSVP Submitted Date (auto-generated)

## CORS Configuration

For headless WordPress, ensure your WordPress site allows CORS requests from your React app domain. Add this to your theme's `functions.php`:

```php
function add_cors_http_header(){
    header("Access-Control-Allow-Origin: https://your-react-app-domain.com");
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization");
}
add_action('init','add_cors_http_header');
```

## Security Notes

- The GraphQL API is read-only for guest searches
- RSVP submissions don't require authentication (guests search by name)
- Admin functions require proper WordPress permissions
- All data is sanitized and validated

## Customization

The plugin is designed to be easily customizable:

- Modify ACF fields in `acf-field-groups.php`
- Add new GraphQL queries/mutations in `graphql-config.php`
- Extend helper functions in `helper-functions.php`
- Customize admin interface in main plugin file

## Support

For questions about implementation or customization, refer to:
- WPGraphQL documentation: https://www.wpgraphql.com/
- ACF documentation: https://www.advancedcustomfields.com/
- WordPress Plugin Development: https://developer.wordpress.org/plugins/