# Password Reset Mechanism Logic

## Scope
This document explains how `forgot password` and `reset password` work in this project based on current implementation.

## Main Files Involved
- `auth/forgot_password.php` (UI page for reset request)
- `controllers/auth/forgot_password_controller.php` (forgot-password logic)
- `auth/reset_password.php` (UI page for new password form)
- `controllers/auth/reset_password_controller.php` (reset logic)
- `helpers/password_reset_helper.php` (token lifecycle + mail link build/send)
- `helpers/auth_throttle_helper.php` (anti-bruteforce lock for forgot-password attempts)

## Data Dependencies
- `users` table: account lookup + password update
- `password_reset_tokens` table:
  - stores `selector`, `token_hash`, `user_id`, `email`, `expires_at`, `used_at`, request metadata
- `auth_attempt_locks` table:
  - tracks failed forgot-password attempts and temporary lock windows
- `remember_tokens` table (optional):
  - all remember-me sessions are revoked after successful password reset

## Email Sending Pipeline (Complete)
1. `controllers/auth/forgot_password_controller.php` calls:
   - `auth_password_reset_send_email($email, $username, $resetLink)`.
2. `helpers/password_reset_helper.php` prepares:
   - subject (`Reset your HostelPro password`)
   - HTML body
   - text fallback body (`altBody`)
3. `helpers/mailer.php` (`hostel_send_mail`) does:
   - verify `vendor/autoload.php` exists
   - verify PHPMailer class is available
   - load SMTP settings from `config/mail_config.php`
   - validate sender/recipient emails
   - validate SMTP credentials
   - send via SMTP with PHPMailer
4. Return contract:
   - success: `['ok' => true]`
   - failure: `['ok' => false, 'error' => '...reason...']`
5. On mail failure:
   - forgot-password flow throws exception
   - request returns controlled user error message
   - technical details go to PHP error log

## SMTP Config Used
Source file: `config/mail_config.php`
- `host`
- `port`
- `encryption`
- `username`
- `password`
- `from_email`
- `from_name`

Recommended production setup:
- keep credentials in environment variables only
- avoid committing real SMTP password in repository
- rotate SMTP password if it was exposed in code history

## Other System (Future User) Email + Token Contract
Use this payload when another system needs to handle delivery or continue reset flow.

Payload fields to share at token creation time:
- `email`: user email (recipient)
- `selector`: public token part (24 hex)
- `token`: secret token part (64 hex, plain only at creation time)
- `expires_at`: token expiry datetime
- `reset_link`: full URL built by system

Example payload:
```json
{
  "email": "future.user@example.com",
  "selector": "a1b2c3d4e5f60718293a4b5c",
  "token": "9f6d3a...64_hex_chars_total...",
  "expires_at": "2026-02-23 18:45:00",
  "reset_link": "https://your-domain.com/auth/reset_password.php?selector=a1b2...&token=9f6d..."
}
```

Important:
- raw `token` is available only when `auth_password_reset_issue(...)` returns it.
- DB stores only `token_hash`; you cannot recover raw token later.
- token must be treated as secret, short-lived, and one-time use.

### Email Template For Other System
Subject:
`Reset your HostelPro password`

Body:
```text
Hello {{name}},

We received a request to reset your password.

Use this link:
{{reset_link}}

If your other system needs manual fields:
- Email: {{email}}
- Selector: {{selector}}
- Token: {{token}}

This reset token expires at {{expires_at}} and can be used only once.
If you did not request this, ignore this email.
```

## Auth Forms UI Description

### Shared UI Pattern (All Auth Pages)
- Layout is a 2-column auth panel:
  - left side: context, guidance list, watermark icon
  - right side: actionable form
- Shared visual style:
  - font: `Manrope`
  - framework: Bootstrap 5 + Bootstrap Icons
  - color theme: aqua/teal with light background gradients
  - rounded card and soft shadow (`auth-panel`)
- Shared components:
  - alert box for `general_errors`
  - inline field feedback using `.invalid-feedback`
  - branded primary action button (`.btn-brand`)
  - footer links: switch auth page + back to home/login
- Responsive behavior:
  - desktop: side-by-side columns
  - tablet/mobile: info panel stacks on top of form

### Login Form UI (`auth/login.php`)
- Heading block:
  - title: `Login`
  - subtitle: `Enter your credentials to access your account.`
- Fields:
  - `email` (type email, envelope icon)
  - `password` (type password, lock icon, show/hide toggle eye button)
  - `remember_me` checkbox
  - hidden `csrf_token`
- Actions:
  - submit: `Login`
  - link: `Forgot password?` -> `forgot_password.php`
  - link: `Create one` -> `register.php`
- Validation UX (`assets/js/login.js`):
  - client validation on input/blur/submit
  - invalid fields get `.is-invalid`
  - first invalid field gets focus on submit fail

### Register Form UI (`auth/register.php`)
- Heading block:
  - title: `Register`
  - subtitle: `Fill the details below to create your account.`
- Fields:
  - `username` (Full Name, regex for at least 3 names)
  - `email` (must match `@gmail.com`)
  - `phone` (`06...`, `07...`, or `+255...`)
  - optional `gender` select (Male/Female) when supported
  - `password` (letters + numbers, min 6, toggle eye button)
  - hidden `csrf_token`
- Actions:
  - submit: `Create Account`
  - link: `Sign in` -> `login.php`
- Validation UX (`assets/js/register.js`):
  - live validation + blur validation
  - uses both `.is-valid` and `.is-invalid`
  - shows green check indicators for valid inputs
  - blocks submit until all visible fields are valid

### Forgot Password Form UI (`auth/forgot_password.php`)
- Heading block:
  - title: `Reset Request`
  - subtitle: `Submit your email to receive a password reset link.`
- Field:
  - `email` only
  - hidden `csrf_token`
- Actions:
  - submit: `Send Reset Link`
  - link: `Back to login` -> `login.php`
- Feedback:
  - success alert when reset link mail is sent
  - error alert list for general failures
  - inline field error for invalid/unregistered email

### Reset Password Form UI (`auth/reset_password.php`)
- Heading block:
  - title: `Reset Password`
  - subtitle: `Enter your new password below.`
- Token state handling:
  - if token invalid/expired:
    - warning alert shown
    - button: `Request New Link` -> `forgot_password.php`
  - if token valid:
    - form displayed
- Fields (token valid state):
  - `password` (new password, toggle eye button)
  - `password_confirm` (confirm password, toggle eye button)
  - hidden `csrf_token`, `selector`, `token`
- Actions:
  - submit: `Update Password`
  - link: `Back to Login` -> `login.php`
- Validation UX (`assets/js/reset-password.js`):
  - validates password rule and confirm match
  - re-validates confirm field when password changes
  - focuses first invalid field on submit fail

### Loading/Submit UX (Shared Script)
- `assets/js/ui-spinner.js` adds loading spinner to submit buttons/links.
- Prevents repeated clicks during navigation or form submit.
- Supports optional confirm dialog flow via `data-confirm`.

## Forgot Password Flow
1. User opens `auth/forgot_password.php`.
2. Controller ensures session exists and creates `$_SESSION['csrf_token']` if missing.
3. On submit (`POST`):
   - validates CSRF token.
   - normalizes identifier using email (or IP fallback) via `auth_throttle_identifier(...)`.
   - checks lock state via `auth_throttle_is_locked(...)`.
4. If not locked:
   - validates email format.
   - finds user by email in `users`.
5. If user exists:
   - creates reset token using `auth_password_reset_issue(...)`:
     - cleans old/expired/used tokens.
     - removes previous tokens for same user/email.
     - generates:
       - `selector` (24 hex chars)
       - `token` (64 hex chars, plain token only sent by email)
       - `token_hash = sha256(token)` stored in DB
     - sets expiry (default 30 minutes).
   - builds reset URL with `auth_password_reset_build_link(selector, token)`.
   - sends mail via `auth_password_reset_send_email(...)`.
   - clears throttle failures with `auth_throttle_clear(...)`.
6. If validation/user lookup fails:
   - failure is counted with `auth_throttle_register_failure(..., maxAttempts=3, lockSeconds=10800)`.
   - after 3 failures, identifier is locked for 3 hours.

## Reset Password Flow
1. User clicks email link to `auth/reset_password.php?selector=...&token=...`.
2. Controller reads `selector` and `token` from `GET`/`POST`.
3. Token is validated by `auth_password_reset_find(...)`:
   - format check:
     - selector must be 24 hex chars
     - token must be 64 hex chars
   - fetch row by `selector`
   - rejects if:
     - not found
     - expired
     - already used
     - hash mismatch (`sha256(incoming token)` != stored hash)
   - invalid/expired rows are cleaned.
4. On reset form submit (`POST`):
   - validates CSRF token.
   - requires valid token row.
   - validates new password:
     - at least 6 chars
     - must include letters and numbers
   - validates password confirmation with secure compare.
5. If valid:
   - hashes password using `password_hash(..., PASSWORD_DEFAULT)`.
   - updates `users.password` by `user_id` (or fallback by email).
   - marks current token used with `auth_password_reset_consume(...)`.
   - deletes all reset tokens for that user/email with `auth_password_reset_invalidate_user_tokens(...)`.
   - revokes remember-me sessions via `auth_password_reset_revoke_remember_tokens(...)`.
   - sets success flash and redirects to `auth/login.php`.

## Logging (Now Implemented)
These logs are written using `error_log(...)`:

Email send logs (`helpers/mailer.php`)
- `hostel_send_mail failed: composer autoload missing`
- `hostel_send_mail failed: PHPMailer class missing after autoload`
- `hostel_send_mail failed: invalid recipient email`
- `hostel_send_mail failed: invalid sender email config`
- `hostel_send_mail failed: smtp credentials missing, recipient=...`
- `hostel_send_mail success: recipient=..., subject=...`
- `hostel_send_mail failed: recipient=..., error=...`

Forgot-password logs (`controllers/auth/forgot_password_controller.php`)
- success log after mail + throttle clear:
  - `Forgot password reset mail sent: user_id=..., selector=..., expires_at=...`
- failure log in catch:
  - `Forgot password request failed: ...`

Reset-password logs (`controllers/auth/reset_password_controller.php`)
- success log after password update + token invalidation:
  - `Password reset successful: user_id=..., token_id=...`
- failure log in catch:
  - `Reset password failed: ...`

Note:
- raw reset token value is not logged.
- recipient is masked in mailer logs.

## Security Controls Implemented
- CSRF protection on both forgot and reset forms
- token split model (`selector` + secret `token`)
- DB stores only token hash (not raw token)
- one-time token use (`used_at`) + expiry enforcement
- automatic cleanup of stale/used tokens
- brute-force throttling on forgot-password endpoint
- remember-me session revocation after password change
- `hash_equals(...)` used for sensitive comparisons

## Error Handling Behavior
- Known infrastructure errors are exposed in a controlled way (missing mail config, missing tables, PHPMailer issues).
- Unknown/internal errors are logged and returned as generic user-safe messages.

## Required DB Schema (Minimal)
Use this when tables are missing.

```sql
CREATE TABLE IF NOT EXISTS password_reset_tokens (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    email VARCHAR(190) NOT NULL,
    selector CHAR(24) NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    requested_ip VARCHAR(45) NULL,
    requested_user_agent VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_password_reset_selector (selector),
    KEY idx_password_reset_user (user_id),
    KEY idx_password_reset_email (email),
    KEY idx_password_reset_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS auth_attempt_locks (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    action_name VARCHAR(64) NOT NULL,
    identifier VARCHAR(190) NOT NULL,
    attempt_count INT NOT NULL DEFAULT 0,
    first_attempt_at DATETIME NULL,
    last_attempt_at DATETIME NULL,
    locked_until DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_auth_attempt_action_identifier (action_name, identifier),
    KEY idx_auth_attempt_locked_until (locked_until)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

## High-Level Sequence (Pseudo)
```text
Forgot:
  validate_csrf -> check_lock -> validate_email -> find_user
  -> issue_token -> build_link -> send_email -> clear_throttle -> success
  else -> register_failure -> maybe_lock

Reset:
  validate_selector_token -> show_form
  on_submit:
    validate_csrf + validate_password_rules + validate_confirmation
    -> update_user_password
    -> consume_token
    -> invalidate_all_user_reset_tokens
    -> revoke_remember_tokens
    -> redirect_login
```
