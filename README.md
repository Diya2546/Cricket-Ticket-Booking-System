# Cricket Ticket Booking System

A full-stack web application for online cricket match ticket booking with user and admin panels, built using PHP, MySQL, and Bootstrap/Tailwind CSS.

## Features

### User Panel
- **User Registration & Login** with email verification
- **Browse Matches** - View upcoming, live, and completed cricket matches (T20, ODI, Test, IPL, World Cup)
- **Book Tickets** - Interactive seat selection with multiple categories (VIP, Premium, General, Platinum, Economy)
- **Payment Processing** - Supports UPI, Card, Net Banking, Wallet with automatic GST & convenience fee calculation
- **My Bookings** - View, track, and cancel bookings
- **Digital Tickets** - View and print tickets with QR code
- **Feedback System** - Rate and review matches after attending
- **Notifications** - Real-time notifications for bookings, cancellations, and payments
- **Profile Management** - Edit profile, upload photo, change password
- **Forgot Password** - OTP-based password reset via email

### Admin Panel
- **Dashboard** - Real-time stats: total users, bookings, matches, revenue
- **Match Management** - Create, edit, delete matches with team and venue assignment
- **Team & Venue Management** - Add teams with logos, venues with capacity and seat categories
- **Booking Management** - View all bookings, payment status, booking details
- **User Management** - View, search, and manage registered users
- **Seat Category & Pricing** - Configure seat categories, pricing, and amenities per venue
- **Feedback Management** - View user feedback, export to CSV
- **Reports & Analytics** - Revenue reports, booking statistics, data export

### Security
- Password hashing with BCRYPT
- Email verification for new accounts
- OTP-based password reset (6-digit code, 10-min expiry)
- SQL injection prevention with prepared statements
- Session-based authentication
- Login alert emails

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Backend | PHP 8.2+ |
| Database | MySQL / MariaDB 10.4+ |
| Frontend | HTML5, CSS3, JavaScript |
| CSS Frameworks | Tailwind CSS, Bootstrap 5.3 |
| Icons | Font Awesome 6.4 |
| JS Library | jQuery 3.7.1 |
| Email | PHPMailer (Gmail SMTP) |
| Server | Apache (XAMPP) |

## Screenshots

> Add your project screenshots here

## Installation & Setup

### Prerequisites
- [XAMPP](https://www.apachefriends.org/) (PHP 8.2+, MySQL/MariaDB, Apache)
- Git

### Steps

1. **Clone the repository**
   ```bash
   git clone https://github.com/Diya2546/Cricket-Ticket-booking-.git
   ```

2. **Move to XAMPP htdocs folder**
   ```bash
   cp -r Cricket-Ticket-booking- C:/xampp/htdocs/cricket-ticket-booking
   ```

3. **Import the database**
   - Open phpMyAdmin: `http://localhost/phpmyadmin`
   - Create a new database named `cricket_ticket`
   - Import the file: `database/cricket_ticket.sql`

4. **Configure SMTP (for email features)**
   - Create the file `config/smtp_config.php`:
     ```php
     <?php
     return [
         "Host"      => "smtp.gmail.com",
         "Port"      => 587,
         "User"      => "your-email@gmail.com",
         "Password"  => "your-16-digit-app-password",
         "FromEmail" => "your-email@gmail.com",
         "FromName"  => "Cricket Ticket Booking"
     ];
     ```
   - To get a 16-digit app password: Google Account > Security > 2-Step Verification > App Passwords

5. **Start Apache and MySQL** from XAMPP Control Panel

6. **Open in browser**
   ```
   http://localhost/cricket-ticket-booking
   ```

### Default Admin Login
- **Username:** admin
- **Password:** admin123

## Project Structure

```
cricket-ticket-booking/
├── admin/                    # Admin panel (dashboard, matches, users, bookings, reports)
├── assets/                   # CSS & JS libraries (Bootstrap, jQuery)
├── config/                   # SMTP configuration
├── css/                      # Custom stylesheets
├── database/                 # SQL schema with sample data
├── image/                    # Team logos, venue & match images
├── models/                   # PHP models (Booking, Match, User)
├── PHPMailer/                # Email library
├── services/                 # EmailService for notifications
├── uploads/                  # User profile pictures
├── userLogin/                # Registration page assets
├── index.php                 # Homepage with match listings
├── booking.php               # Seat selection & booking
├── booking-confirmation.php  # Payment & confirmation
├── ticket-view.php           # Digital ticket with QR code
├── MyBooking.php             # User's booking history
├── matches.php               # Browse all matches
├── login.php                 # User & admin login
├── register.php              # User registration
├── profile.php               # User profile
├── feedback.php              # Match feedback & ratings
├── connection.php            # Database connection
└── verify_email.php          # Email verification
```

## Database Schema

The database `cricket_ticket` contains the following tables:

| Table | Description |
|-------|-------------|
| `admins` | Admin accounts with roles |
| `users` | Registered users with verification status |
| `teams` | Cricket teams with logos |
| `venues` | Stadiums with capacity |
| `matches` | Scheduled matches with teams and venues |
| `seat_categories` | Ticket categories (VIP, General, etc.) |
| `venue_category` | Seat allocation and pricing per venue |
| `bookings` | User ticket bookings |
| `booking_items` | Individual seats per booking |
| `payments` | Payment transactions |
| `feedback` | User ratings and reviews |
| `notifications` | User notification messages |
| `otp_verifications` | OTP codes for password reset |



### Three Layers of Double-Booking Prevention

| Layer | Where | How |
|-------|-------|-----|
| **1. Real-Time Polling** | Client (JavaScript) | AJAX call to `get-booked-seats.php` every 5 seconds updates the seat grid. If another user books a seat you selected, it auto-deselects and alerts you. |
| **2. Pre-Submit Validation** | Client (JavaScript) | Just before form submission, a final AJAX request checks for conflicts. If any selected seat was booked in the last few seconds, submission is blocked. |
| **3. Database Transaction** | Server (PHP + MySQL) | Inside a `BEGIN TRANSACTION`, the server re-queries all booked seats. If a conflict is found, the entire transaction is rolled back. Seat count is decremented atomically with `WHERE no_of_seats >= quantity`. |

### Key Implementation Details

- **Seat Grid**: 6 rows (A-F) x 8 seats per row = 48 seats per category
- **Seat States**: Available (clickable) / Booked (red, blocked) / Selected (green, user's pick)
- **Polling Endpoint**: `get-booked-seats.php` with cache-busting (`t=${Date.now()}`)
- **Atomic Seat Update**: `UPDATE venue_category SET no_of_seats = no_of_seats - $qty WHERE no_of_seats >= $qty` prevents overselling
- **Booking Code Format**: `CT` + timestamp + 3-digit random (e.g., `CT20260319093548944`)
- **Only Confirmed Bookings Count**: Cancelled/failed bookings don't block seats

### Pricing Calculation

```
Base Price     = Unit Price x Quantity
Convenience Fee = Base Price x 2%
GST            = Base Price x 18%
─────────────────────────────────
Total Amount   = Base Price + Convenience Fee + GST
```

## User Flow

1. **Register** with name, email, phone, and password
2. **Verify email** via the verification link sent to your inbox
3. **Browse matches** on the homepage
4. **Select seats** by category and quantity (real-time availability)
5. **Make payment** via UPI, Card, Net Banking, or Wallet
6. **View digital ticket** with QR code
7. **Cancel booking** before the cancellation deadline (if needed)
8. **Give feedback** after attending the match

## License

This project is open source and available for educational purposes.
