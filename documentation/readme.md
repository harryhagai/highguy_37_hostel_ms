# Hostel Management System Directory Structure


hostel_management/
│
├── admin/
│   ├── admin_dashboard_layout.php      // Admin dashboard layout (header/sidebar/footer)
│   ├── dashboard.php                   // Admin dashboard main page
│   ├── manage_users.php                // Admin: Manage users (CRUD)
│   ├── manage_hostel.php               // Admin: Manage hostels (CRUD)
│   └── manage_rooms.php                // Admin: Manage rooms (CRUD)
    └── notice.php                // Admin create notice(CRUD)
│
├── user/
│   ├── user_dashboard_layout.php       // User dashboard layout (header/sidebar/footer)
│   ├── view_profile.php                // User: View/update profile
│   ├── book_room.php                   // User: Book a room
│   └── view_bookings.php               // User: View own bookings
    └── logout.php
│
├── config/
│   ├── db_connection.php               // PDO/MySQL database connection
│   └── hostel_management.sql           // SQL file for database schema
│
├── assets/
│   ├
│   └── images/                        // Images used in the application
│
├── uploads/
│   └── (profile photos, hostel images)                // Uploaded user profile and hostel images
│
├── index.php                           // Home/welcome page
├── login.php                           // Login page
├── register.php                        // Registration page
├── logout.php                          // Logout logic
├── readme.md                           // Project documentation
