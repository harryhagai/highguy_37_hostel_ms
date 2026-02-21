# Hostel Project - Proposed SPA Folder Structure

## 1) Current project summary (nilichokagua)
- Root auth/public pages: `index.php`, `login.php`, `register.php`, `logout.php`
- Admin area: `admin/admin_dashboard_layout.php` + module files (`manage_users.php`, `manage_hostel.php`, `manage_rooms.php`, `application_management.php`, `notice.php`)
- User area: `user/user_dashboard_layout.php` + module files (`view_hostels.php`, `book_room.php`, `my_bookings.php`, `user_profile.php`)
- DB config/schema: `config/db_connection.php`, `config/hostel_management.sql`
- Assets/uploads: `assets/images/*`, `uploads/hostels/*`
- Pattern ya sasa: PHP pages zinachanganya UI + business logic + DB queries ndani ya file moja.
- Limitation ya sasa: booking iko room-level, sio bed-level.

## 2) Target architecture (SPA + PHP API + SQL + AJAX)
- Frontend: Single Page App (HTML + Bootstrap + CSS + Vanilla JS modules)
- Frontend-backend communication: AJAX only (`fetch` preferred, `XMLHttpRequest` optional fallback)
- Backend: PHP API endpoints (JSON responses only)
- Database: MySQL schema + seeds in dedicated `database/`
- Domain model mpya: `hostel -> room -> bed`, na user anabook `bed` kwa kipindi maalum.

## 3) Proposed folder tree
```text
hostel/
|-- app/
|   |-- config/
|   |   |-- app.php
|   |   `-- database.php
|   |-- core/
|   |   |-- Database.php
|   |   |-- Request.php
|   |   |-- Response.php
|   |   |-- Auth.php
|   |   `-- Validator.php
|   |-- middleware/
|   |   |-- AuthMiddleware.php
|   |   `-- RoleMiddleware.php
|   |-- controllers/
|   |   |-- AuthController.php
|   |   |-- UserController.php
|   |   |-- HostelController.php
|   |   |-- RoomController.php
|   |   |-- BedController.php
|   |   |-- BookingController.php
|   |   `-- NoticeController.php
|   |-- repositories/
|   |   |-- UserRepository.php
|   |   |-- HostelRepository.php
|   |   |-- RoomRepository.php
|   |   |-- BedRepository.php
|   |   |-- BookingRepository.php
|   |   `-- NoticeRepository.php
|   `-- routes/
|       |-- api.php
|       `-- web.php
|
|-- public/
|   |-- index.php                     # SPA shell entry
|   |-- .htaccess                     # rewrite all SPA routes to index.php
|   |-- api.php                       # API front controller
|   |-- assets/
|   |   |-- css/
|   |   |   |-- bootstrap.min.css
|   |   |   |-- app.css
|   |   |   |-- admin.css
|   |   |   `-- user.css
|   |   |-- js/
|   |   |   |-- app.js
|   |   |   |-- router.js
|   |   |   |-- ajax-client.js
|   |   |   |-- state.js
|   |   |   |-- guards.js
|   |   |   |-- components/
|   |   |   |   |-- sidebar.js
|   |   |   |   |-- header.js
|   |   |   |   |-- modal.js
|   |   |   |   `-- table.js
|   |   |   `-- pages/
|   |   |       |-- auth/
|   |   |       |   |-- login.page.js
|   |   |       |   `-- register.page.js
|   |   |       |-- admin/
|   |   |       |   |-- dashboard.page.js
|   |   |       |   |-- users.page.js
|   |   |       |   |-- hostels.page.js
|   |   |       |   |-- rooms.page.js
|   |   |       |   |-- beds.page.js
|   |   |       |   |-- applications.page.js
|   |   |       |   `-- notices.page.js
|   |   |       `-- user/
|   |   |           |-- dashboard.page.js
|   |   |           |-- profile.page.js
|   |   |           |-- hostels.page.js
|   |   |           |-- bed-booking.page.js
|   |   |           `-- my-bookings.page.js
|   |   `-- images/
|   |       `-- ...existing images
|   `-- uploads/
|       |-- hostels/
|       `-- profiles/
|
|-- database/
|   |-- schema/
|   |   `-- hostel_management.sql
|   |-- migrations/
|   |   `-- 001_init.sql
|   `-- seeders/
|       `-- seed_admin.sql
|
|-- docs/
|   |-- api-endpoints.md
|   |-- folder-structure.md
|   `-- migration-plan.md
|
|-- scripts/
|   |-- seed-admin.php
|   `-- maintenance.php
|
|-- storage/
|   |-- logs/
|   `-- cache/
|
|-- .env
|-- .env.example
|-- composer.json
`-- README.md
```

## 4) API route grouping (suggested)

### Auth
- `POST /api/auth/login`
- `POST /api/auth/register`
- `POST /api/auth/logout`
- `GET /api/auth/me`

### Admin
- `GET /api/admin/stats`
- `GET /api/admin/users`
- `POST /api/admin/users`
- `PUT /api/admin/users/{id}`
- `DELETE /api/admin/users/{id}`
- `GET /api/admin/hostels`
- `POST /api/admin/hostels`
- `PUT /api/admin/hostels/{id}`
- `DELETE /api/admin/hostels/{id}`
- `GET /api/admin/rooms`
- `POST /api/admin/rooms`
- `PUT /api/admin/rooms/{id}`
- `DELETE /api/admin/rooms/{id}`
- `GET /api/admin/beds`
- `POST /api/admin/beds`
- `PUT /api/admin/beds/{id}`
- `DELETE /api/admin/beds/{id}`
- `GET /api/admin/bookings`
- `PATCH /api/admin/bookings/{id}/approve`
- `PATCH /api/admin/bookings/{id}/reject`
- `GET /api/admin/notices`
- `POST /api/admin/notices`
- `PUT /api/admin/notices/{id}`
- `DELETE /api/admin/notices/{id}`

### User
- `GET /api/user/dashboard`
- `GET /api/user/profile`
- `PUT /api/user/profile`
- `POST /api/user/profile/photo`
- `GET /api/user/hostels`
- `GET /api/user/hostels/{id}/rooms`
- `GET /api/user/rooms/{id}/beds`
- `POST /api/user/bookings` (payload: `bed_id`, `start_date`, `end_date`)
- `GET /api/user/bookings`
- `DELETE /api/user/bookings/{id}`

## 4.1) Bed booking rules (important)
- Booking iwe kwa `bed_id` sio `room_id`.
- Booking iwe na `start_date` na `end_date` (au `duration_months` + derived end date).
- Kuzuia overlapping bookings kwa bed moja ndani ya kipindi kinachokutana.
- Room availability ihesabiwe kutoka beds zilizo free kwenye tarehe range husika.
- Admin aweze kuweka bed status: `active`, `maintenance`, `inactive`.

## 4.2) AJAX conventions (important)
- Requests zote za data zitumie `Content-Type: application/json` (isipokuwa file upload, tumia `FormData`).
- API irudishe standard JSON format:
  - success: `{ "ok": true, "data": ... }`
  - error: `{ "ok": false, "message": "...", "errors": {...} }`
- Frontend ifanye partial UI updates baada ya AJAX response (hakuna full page reload).
- Session auth ibaki PHP session cookie (`credentials: "include"` kwenye `fetch`).
- CRUD zote za admin/user modules ziwe AJAX actions (create, edit, delete, approve/reject).
- Booking form ya user itume date range kwa AJAX na API ifanye validation ya conflicts.

## 5) Mapping ya current files -> new structure
- `index.php` -> `public/index.php` (SPA shell only)
- `login.php`, `register.php` -> SPA pages in `public/assets/js/pages/auth/`
- `admin/admin_dashboard_layout.php` -> SPA admin layout components + `admin/*.page.js`
- `admin/manage_users.php` -> `app/controllers/UserController.php` + `public/assets/js/pages/admin/users.page.js`
- `admin/manage_hostel.php` -> `app/controllers/HostelController.php` + `public/assets/js/pages/admin/hostels.page.js`
- `admin/manage_rooms.php` -> `app/controllers/RoomController.php` + `public/assets/js/pages/admin/rooms.page.js`
- `admin/manage_beds.php` (new) -> `app/controllers/BedController.php` + `public/assets/js/pages/admin/beds.page.js`
- `admin/application_management.php` -> `app/controllers/BookingController.php` + `public/assets/js/pages/admin/applications.page.js`
- `admin/notice.php` -> `app/controllers/NoticeController.php` + `public/assets/js/pages/admin/notices.page.js`
- `user/user_dashboard_layout.php` -> SPA user layout components + `user/*.page.js`
- `user/view_hostels.php` -> `GET /api/user/hostels` + `user/hostels.page.js`
- `user/book_room.php` -> `GET /api/user/hostels/{id}/rooms` + `GET /api/user/rooms/{id}/beds` + `POST /api/user/bookings`
- `user/my_bookings.php` -> `GET/DELETE /api/user/bookings`
- `user/user_profile.php` -> `GET/PUT /api/user/profile` + `POST /api/user/profile/photo`
- `config/db_connection.php` -> `app/config/database.php` + `app/core/Database.php`
- `config/hostel_management.sql` -> `database/schema/hostel_management.sql`
- `admin/seed_admin.php` -> `scripts/seed-admin.php` + `database/seeders/seed_admin.sql`

## 6) Migration order (recommended)
1. Tengeneza DB migration ya `beds` table + update `bookings` table iwe bed-level + period fields.
2. Tengeneza backend API skeleton (`public/api.php`, routes, controllers, repositories).
3. Hamisha DB connection + auth/session checks kwenye `core` na `middleware`.
4. Build SPA shell + JS router + auth guard.
5. Hamisha admin modules one-by-one (users -> hostels -> rooms -> beds -> applications -> notices).
6. Hamisha user modules (profile -> hostels -> bed booking -> my bookings).
7. Hamisha uploads path kwenda `public/uploads/` na sanitize file uploads.
8. Ondoa legacy page-by-page PHP UI baada ya parity test kukamilika.

## 7) Important notes kabla ya implementation
- Credentials za DB na secrets ziondoke kwenye source code, ziende `.env`.
- Return JSON only from API; HTML ibaki frontend SPA.
- Consistent booking status enum itumike kila sehemu: `pending | confirmed | cancelled`.
- Route permissions ziwe strict (`admin` vs `user` middleware).
- Usihesabu capacity kwa room manually; count beds halisi kwa room ndiyo source of truth.
