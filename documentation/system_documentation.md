# HostelPro System Documentation

## 1. System Overview
HostelPro is a role-based hostel management system built with native PHP, MySQL (PDO), Bootstrap, and JavaScript.
It supports public browsing, user self-service booking flows, and admin operational control.

## 2. Main Actors
- Guest
- Registered User
- Admin
- Email Service (Gmail SMTP via PHPMailer)
- Payment Verification Process

## 3. Core Functional Modules
- Authentication
- Password recovery and reset
- User profile management
- Hostel, room, and bed discovery
- Bed/room booking submission
- Payment proof submission and verification
- Booking/application management
- Notices and announcements
- Admin settings, semester settings, payment settings

## 4. Role Capabilities
### Guest
- View public hostel information
- Register account
- Login
- Request password reset

### Registered User
- Access user dashboard
- View hostels/rooms/beds
- Book a bed/room
- View own bookings, assigned bed/room, and notices
- Submit payment verification details
- Update own profile

### Admin
- Access admin dashboard and analytics
- Manage users
- Manage hostels
- Manage rooms
- Manage beds
- Review and manage applications/bookings
- Manage notices
- Configure payment and semester settings

## 5. Security and Control Rules
- CSRF token checks on auth and sensitive forms
- Password hashing with `password_hash()` / `password_verify()`
- Remember-me token rotation and secure cookie handling
- Password reset token flow with random token generation, hashed storage, expiry, and one-time consumption
- Throttling policy: Login 3 failed attempts locks for 3 hours
- Throttling policy: Forgot password 3 failed attempts locks for 3 hours

## 6. Important Tables in Current Flow
- `users`
- `hostels`
- `rooms`
- `beds`
- `bookings`
- `notices`
- `remember_tokens`
- `password_reset_tokens`
- `auth_attempt_locks`

## 7. Key Files (Current Implementation)
- Auth pages: `auth/login.php`, `auth/register.php`, `auth/forgot_password.php`, `auth/reset_password.php`
- Auth controllers: `controllers/auth/login_controller.php`, `controllers/auth/register_controller.php`, `controllers/auth/forgot_password_controller.php`, `controllers/auth/reset_password_controller.php`
- Security helpers: `helpers/auth_throttle_helper.php`, `helpers/password_reset_helper.php`, `controllers/auth/remember_me.php`
- Admin modules: `admin/*.php` + `controllers/admin/*_controller.php`
- User modules: `user/*.php` + `controllers/user/*_controller.php`
- DB config: `config/db_connection.php`
- Mail config: `config/mail_config.php`

## 8. Operational Notes
- SMTP must be configured correctly in `config/mail_config.php` (or environment variables)
- SQL migrations for auth lock and password reset token tables must be run before production use
- Logs are used for failure diagnosis (mail send failures, runtime exceptions, and auth-related issues)
