# Travel Agency Portal - Setup Guide

## Prerequisites

- PHP 8.2 or higher
- Composer
- Node.js 18+ and npm
- MySQL 8.0+
- Git

## Backend Setup (Laravel)

### 1. Navigate to backend directory
```bash
cd backend
```

### 2. Install PHP dependencies
```bash
composer install
```

### 3. Install Laravel Sanctum
```bash
composer require laravel/sanctum
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
```

### 4. Install Stripe SDK
```bash
composer require stripe/stripe-php
```

### 5. Configure environment
```bash
cp .env.example .env
php artisan key:generate
```

### 6. Update .env file
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=travel_agency
DB_USERNAME=root
DB_PASSWORD=your_password

FRONTEND_URL=http://localhost:3000

STRIPE_KEY=your_stripe_publishable_key
STRIPE_SECRET_KEY=your_stripe_secret_key

SSLCOMMERZ_STORE_ID=your_store_id
SSLCOMMERZ_STORE_PASSWORD=your_store_password
SSLCOMMERZ_IS_SANDBOX=true
```

### 7. Run migrations
```bash
php artisan migrate
```

### 8. Create storage link
```bash
php artisan storage:link
```

### 9. Start Laravel server
```bash
php artisan serve
```

The backend will be available at `http://localhost:8000`

## Frontend Setup (React)

### 1. Navigate to frontend directory
```bash
cd frontend
```

### 2. Install dependencies
```bash
npm install
```

### 3. Configure environment
Create a `.env` file:
```env
VITE_API_URL=http://localhost:8000/api
VITE_STRIPE_PUBLISHABLE_KEY=your_stripe_publishable_key
```

### 4. Start development server
```bash
npm run dev
```

The frontend will be available at `http://localhost:3000`

## Database Seeding (Optional)

Create a seeder to add initial data:

```bash
php artisan make:seeder AdminUserSeeder
```

Then run:
```bash
php artisan db:seed --class=AdminUserSeeder
```

## Testing the Application

1. **Register a new user**: Navigate to `/register`
2. **Browse packages**: Navigate to `/packages`
3. **Create a booking**: Select a package and click "Book Now"
4. **View dashboard**: After login, access `/dashboard`

## API Testing

You can test the API using Postman or any API client:

- Base URL: `http://localhost:8000/api`
- Authentication: Bearer token (obtained from `/api/login`)

## Common Issues

### CORS Error
- Make sure `FRONTEND_URL` in `.env` matches your frontend URL
- Check `config/cors.php` configuration

### Database Connection Error
- Verify database credentials in `.env`
- Ensure MySQL service is running
- Check database exists: `CREATE DATABASE travel_agency;`

### Storage Link Error
- Run: `php artisan storage:link`
- Ensure `storage/app/public` directory exists

### Frontend API Connection Error
- Verify `VITE_API_URL` in frontend `.env`
- Check backend is running on port 8000
- Verify CORS configuration

## Production Deployment

### Backend
1. Set `APP_ENV=production` in `.env`
2. Set `APP_DEBUG=false`
3. Run `php artisan config:cache`
4. Run `php artisan route:cache`
5. Run `php artisan view:cache`

### Frontend
1. Run `npm run build`
2. Deploy the `dist` folder to your web server

## Security Checklist

- [ ] Change default admin credentials
- [ ] Use strong database passwords
- [ ] Enable HTTPS in production
- [ ] Set secure session configuration
- [ ] Configure CORS properly
- [ ] Use environment variables for sensitive data
- [ ] Enable rate limiting
- [ ] Regular security updates

## Support

For issues or questions, please refer to the main README.md file.

