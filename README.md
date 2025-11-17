# Travel Agency Portal

A comprehensive B2C travel agency management system built with Laravel (backend) and React (frontend).

## Features

### Core Modules

1. **Tour Package Management**
   - CRUD operations for tour packages
   - Image upload and management
   - Category and search system
   - Availability management

2. **Real-time Booking Engine**
   - Date and participant availability checking
   - Multi-step booking process
   - Customer registration during booking
   - Booking confirmation system

3. **Payment Integration**
   - Stripe integration
   - SSLCommerz integration
   - Payment status tracking
   - Invoice generation

4. **Customer Inquiry & Ticket System**
   - Customer inquiry submission
   - Support ticket management
   - Agent assignment
   - Ticket replies and status tracking

5. **Agent Panel**
   - Agent dashboard with statistics
   - Booking management
   - Commission tracking
   - Customer management

6. **Authentication & Authorization**
   - Role-based access control (Customer, Agent, Admin)
   - JWT token authentication
   - Profile management

## Technology Stack

### Backend
- Laravel 12
- MySQL Database
- Laravel Sanctum (API Authentication)
- Stripe SDK
- SSLCommerz SDK

### Frontend
- React 18
- React Router
- Axios
- Tailwind CSS
- React Hook Form
- Stripe React Elements

## Project Structure

```
Travel Agency Portal/
├── backend/          # Laravel API
│   ├── app/
│   │   ├── Http/
│   │   │   └── Controllers/API/
│   │   └── Models/
│   ├── database/
│   │   └── migrations/
│   └── routes/
│       └── api.php
└── frontend/         # React Application
    ├── src/
    │   ├── components/
    │   ├── pages/
    │   ├── services/
    │   └── utils/
    └── public/
```

## Installation

### Backend Setup

1. Navigate to backend directory:
```bash
cd backend
```

2. Install dependencies:
```bash
composer install
```

3. Copy environment file:
```bash
cp .env.example .env
```

4. Generate application key:
```bash
php artisan key:generate
```

5. Configure database in `.env`:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=travel_agency
DB_USERNAME=root
DB_PASSWORD=your_password
```

6. Run migrations:
```bash
php artisan migrate
```

7. Install Laravel Sanctum:
```bash
composer require laravel/sanctum
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
php artisan migrate
```

8. Install Stripe SDK:
```bash
composer require stripe/stripe-php
```

9. Start the server:
```bash
php artisan serve
```

### Frontend Setup

1. Navigate to frontend directory:
```bash
cd frontend
```

2. Install dependencies:
```bash
npm install
```

3. Create `.env` file:
```env
REACT_APP_API_URL=http://localhost:8000/api
REACT_APP_STRIPE_PUBLISHABLE_KEY=your_stripe_publishable_key
```

4. Start development server:
```bash
npm start
```

## API Endpoints

### Authentication
- `POST /api/register` - Register new user
- `POST /api/login` - Login user
- `POST /api/logout` - Logout user
- `GET /api/user` - Get authenticated user

### Packages
- `GET /api/packages` - List packages
- `GET /api/packages/{id}` - Get package details
- `POST /api/packages` - Create package (Admin/Agent)
- `PUT /api/packages/{id}` - Update package (Admin/Agent)
- `DELETE /api/packages/{id}` - Delete package (Admin/Agent)
- `GET /api/packages/{id}/availability` - Check availability

### Bookings
- `GET /api/bookings` - List bookings
- `POST /api/bookings` - Create booking
- `GET /api/bookings/{id}` - Get booking details
- `PUT /api/bookings/{id}/cancel` - Cancel booking

### Payments
- `POST /api/payments/stripe` - Process Stripe payment
- `POST /api/payments/sslcommerz` - Process SSLCommerz payment
- `POST /api/payments/{id}/verify` - Verify payment

### Inquiries & Tickets
- `GET /api/inquiries` - List inquiries
- `POST /api/inquiries` - Create inquiry
- `GET /api/tickets` - List tickets
- `POST /api/tickets` - Create ticket
- `POST /api/tickets/{id}/replies` - Add reply

### Agent Panel
- `GET /api/agent/dashboard` - Agent dashboard
- `GET /api/agent/bookings` - Agent bookings
- `GET /api/agent/commissions` - Agent commissions

## Database Schema

### Main Tables
- `users` - User accounts (customers, agents, admins)
- `packages` - Tour packages
- `bookings` - Customer bookings
- `payments` - Payment transactions
- `inquiries` - Customer inquiries
- `tickets` - Support tickets
- `ticket_replies` - Ticket replies
- `package_availability` - Package availability calendar
- `agent_commissions` - Agent commission records

## Security Features

- Role-based access control (RBAC)
- API token authentication
- CORS configuration
- Input validation
- SQL injection prevention
- XSS protection
- CSRF protection

## Business Metrics

- **Booking Conversion**: 15-25% from package view to booking
- **Payment Success Rate**: 95%+ successful transactions
- **Customer Satisfaction**: 4.5+ star ratings
- **Agent Commission**: Automated calculation and payout

## Technical Metrics

- **Page Load Time**: < 2 seconds
- **API Response Time**: < 200ms
- **Uptime**: 99.5% availability
- **Mobile Responsive**: Full mobile compatibility

## Development Phases

### Phase 1: Core Setup ✅
- Laravel backend setup with authentication
- React frontend scaffolding
- Basic database schema
- Admin panel structure

### Phase 2: Package Management
- Tour package CRUD
- Image upload and management
- Category and search system
- Basic frontend listing

### Phase 3: Booking Engine
- Availability system
- Multi-step booking process
- Customer registration
- Booking confirmation

### Phase 4: Payment Integration
- Stripe integration
- SSLCommerz integration
- Payment status handling
- Invoice generation

### Phase 5: Support & Agent Systems
- Ticket management
- Agent panel
- Commission system
- Customer communication

## License

MIT License

## Support

For support, email support@travelagency.com or create a ticket in the system.

