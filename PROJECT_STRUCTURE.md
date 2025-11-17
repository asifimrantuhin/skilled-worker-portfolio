# Travel Agency Portal - Project Structure

## Backend Structure (Laravel)

```
backend/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   └── API/
│   │   │       ├── AgentController.php
│   │   │       ├── AuthController.php
│   │   │       ├── BookingController.php
│   │   │       ├── DashboardController.php
│   │   │       ├── InquiryController.php
│   │   │       ├── PackageController.php
│   │   │       ├── PaymentController.php
│   │   │       └── TicketController.php
│   │   └── Middleware/
│   │       └── RoleMiddleware.php
│   ├── Models/
│   │   ├── AgentCommission.php
│   │   ├── Booking.php
│   │   ├── Inquiry.php
│   │   ├── Package.php
│   │   ├── PackageAvailability.php
│   │   ├── Payment.php
│   │   ├── Ticket.php
│   │   ├── TicketReply.php
│   │   └── User.php
│   └── Services/
│       └── SSLCommerzService.php
├── database/
│   ├── migrations/
│   │   ├── 0001_01_01_000000_create_users_table.php
│   │   ├── 2024_01_01_000001_create_packages_table.php
│   │   ├── 2024_01_01_000002_create_bookings_table.php
│   │   ├── 2024_01_01_000003_create_payments_table.php
│   │   ├── 2024_01_01_000004_create_inquiries_table.php
│   │   ├── 2024_01_01_000005_create_tickets_table.php
│   │   ├── 2024_01_01_000006_create_ticket_replies_table.php
│   │   ├── 2024_01_01_000007_create_package_availability_table.php
│   │   └── 2024_01_01_000008_create_agent_commissions_table.php
│   └── seeders/
│       └── AdminUserSeeder.php
└── routes/
    └── api.php
```

## Frontend Structure (React)

```
frontend/
├── src/
│   ├── components/
│   │   ├── Layout/
│   │   │   ├── Navbar.jsx
│   │   │   └── Footer.jsx
│   │   └── PrivateRoute.jsx
│   ├── contexts/
│   │   └── AuthContext.jsx
│   ├── pages/
│   │   ├── Agent/
│   │   │   ├── AgentDashboard.jsx
│   │   │   ├── AgentBookings.jsx
│   │   │   └── AgentCommissions.jsx
│   │   ├── Auth/
│   │   │   ├── Login.jsx
│   │   │   └── Register.jsx
│   │   ├── Booking.jsx
│   │   ├── BookingDetail.jsx
│   │   ├── Dashboard.jsx
│   │   ├── Home.jsx
│   │   ├── Inquiry.jsx
│   │   ├── MyBookings.jsx
│   │   ├── PackageDetail.jsx
│   │   ├── Packages.jsx
│   │   ├── TicketDetail.jsx
│   │   └── Tickets.jsx
│   ├── services/
│   │   └── api.js
│   ├── App.jsx
│   ├── index.css
│   └── main.jsx
├── index.html
├── package.json
├── vite.config.js
└── tailwind.config.js
```

## Key Features Implemented

### 1. Authentication & Authorization
- User registration and login
- Role-based access control (Customer, Agent, Admin)
- JWT token authentication via Laravel Sanctum
- Protected routes in React

### 2. Tour Package Management
- CRUD operations for packages
- Image upload support
- Search and filtering
- Availability checking
- Category and destination filtering

### 3. Booking System
- Multi-step booking process
- Traveler information collection
- Availability validation
- Booking confirmation
- Booking cancellation

### 4. Payment Integration
- Stripe payment gateway integration
- SSLCommerz payment gateway integration
- Payment status tracking
- Transaction management

### 5. Customer Support
- Inquiry submission system
- Support ticket management
- Ticket replies and status tracking
- Agent assignment

### 6. Agent Panel
- Agent dashboard with statistics
- Booking management
- Commission tracking
- Customer management

### 7. Dashboard
- Customer dashboard with booking overview
- Agent dashboard with commission stats
- Admin dashboard (structure ready)

## Database Schema

### Core Tables
- **users**: User accounts with roles
- **packages**: Tour packages
- **bookings**: Customer bookings
- **payments**: Payment transactions
- **inquiries**: Customer inquiries
- **tickets**: Support tickets
- **ticket_replies**: Ticket conversation
- **package_availability**: Availability calendar
- **agent_commissions**: Commission records

## API Endpoints Summary

### Authentication
- `POST /api/register` - Register
- `POST /api/login` - Login
- `POST /api/logout` - Logout
- `GET /api/user` - Get user

### Packages
- `GET /api/packages` - List packages
- `GET /api/packages/{id}` - Get package
- `POST /api/packages` - Create (Admin/Agent)
- `PUT /api/packages/{id}` - Update (Admin/Agent)
- `DELETE /api/packages/{id}` - Delete (Admin/Agent)
- `GET /api/packages/{id}/availability` - Check availability

### Bookings
- `GET /api/bookings` - List bookings
- `POST /api/bookings` - Create booking
- `GET /api/bookings/{id}` - Get booking
- `PUT /api/bookings/{id}/cancel` - Cancel booking

### Payments
- `POST /api/payments/stripe` - Stripe payment
- `POST /api/payments/sslcommerz` - SSLCommerz payment
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

## Next Steps

1. **Install Dependencies**
   - Backend: `composer install` in backend directory
   - Frontend: `npm install` in frontend directory

2. **Configure Environment**
   - Copy `.env.example` to `.env` in backend
   - Create `.env` in frontend with API URL

3. **Run Migrations**
   - `php artisan migrate`
   - `php artisan db:seed --class=AdminUserSeeder`

4. **Start Servers**
   - Backend: `php artisan serve`
   - Frontend: `npm run dev`

5. **Test Application**
   - Register a user
   - Create a package (as admin/agent)
   - Make a booking
   - Test payment flow

## Development Notes

- All API responses follow a consistent format with `success`, `message`, and data fields
- Error handling is implemented in both backend and frontend
- CORS is configured for frontend-backend communication
- Role-based middleware protects admin/agent routes
- React context manages authentication state
- Tailwind CSS provides modern, responsive UI

## Security Considerations

- Password hashing using bcrypt
- API token authentication
- Role-based access control
- Input validation on all endpoints
- CORS configuration
- SQL injection prevention (Eloquent ORM)
- XSS protection (React escapes by default)

