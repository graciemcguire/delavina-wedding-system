# Delavina Wedding System

A complete wedding RSVP system with WordPress backend and React frontend.

## Project Structure

```
delavina-wedding-system/
├── backend/           # WordPress plugin and server-side code
│   ├── delavina-rsvp-plugin/  # WordPress plugin source
│   └── guests-091025.csv      # Guest data (gitignored)
└── frontend/          # React TypeScript application
    ├── src/
    ├── public/
    └── package.json
```

## Backend (WordPress Plugin)

- **WordPress plugin** for guest management
- **GraphQL API** for headless frontend communication
- **CSV import** for guest data
- **RSVP management** and statistics

**GraphQL Endpoint**: `https://delavina1.wpenginepowered.com/graphql`

## Frontend (React App)

- **React TypeScript** application
- **RSVP form** and guest search
- **Responsive design** for wedding guests

## Development

### Backend
1. Upload `backend/delavina-rsvp-plugin-v2.0.0.zip` to WordPress
2. Import guest CSV through WordPress admin

### Frontend
```bash
cd frontend
npm start
```

## API Integration

The React frontend communicates with the WordPress backend via GraphQL:

- **Search guests**: `searchGuests(searchTerm: String)`
- **Submit RSVP**: `submitRSVP(input: RSVPInput)`
- **Get guest details**: `guestDetails(id: ID)`