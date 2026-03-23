# Auth UI Design (Reusable)

## Purpose
Hii document inaeleza UI design ya:
- Login
- Forgot Password
- Reset Password

Lengo ni kuwa na muonekano mmoja unaoweza kutumika kwenye systems nyingine bila kubadilisha logic ya backend.

## Design Goals
- Muonekano wa kisasa, safi, na rahisi kusoma.
- Form ziwe wazi na actionable kwa user wa kwanza.
- Reusable components: card, input group, alerts, buttons.
- Accessibility-first: keyboard navigation, contrast nzuri, clear error messages.
- Responsive: desktop, tablet, mobile.

## Shared Visual System

### Color Tokens
Tumia CSS variables hizi kwa consistency:

```css
:root {
  --auth-bg-start: #f0fdfa;
  --auth-bg-end: #ecfeff;
  --auth-surface: #ffffff;
  --auth-text: #0f172a;
  --auth-muted: #475569;
  --auth-primary: #0f766e;
  --auth-primary-hover: #115e59;
  --auth-border: #cbd5e1;
  --auth-danger: #dc2626;
  --auth-success: #16a34a;
  --auth-focus: #14b8a6;
}
```

### Typography
- Font primary: `Manrope`, fallback `Segoe UI`, `sans-serif`.
- Heading weight: 700.
- Body weight: 400-500.
- Minimum text size: 14px.

### Radius, Spacing, Shadow
- Card radius: 20px.
- Input radius: 12px.
- Button radius: 12px.
- Base spacing scale: 8, 12, 16, 24, 32.
- Shadow: `0 16px 40px rgba(15, 23, 42, 0.10)`.

## Shared Layout Pattern

### Desktop (>= 992px)
- Container center, max-width 1100px.
- 2 columns:
  - Left: context panel (branding, help text, mini checklist).
  - Right: form panel (main action).

### Mobile (< 992px)
- Single column stack.
- Context panel juu, form chini.
- Buttons na inputs zikae full width.

## Card Size Specification

### Auth Container
- Max width: `1100px`
- Horizontal padding:
  - desktop: `24px`
  - mobile: `16px`

### Left Context Card (`.auth-panel`)
- Width (desktop): `45%`
- Min height (desktop): `560px`
- Padding: `32px`
- Radius: `20px`
- Position: `relative` (ili iwe rahisi ku-place big icon ndani yake)

### Right Form Card (`.auth-card`)
- Width (desktop): `55%`
- Max width ya form content ndani: `460px`
- Min height (desktop): `560px`
- Padding:
  - desktop: `40px`
  - tablet: `32px`
  - mobile: `24px`
- Radius: `20px`

### Mobile Card Rules (< 992px)
- Left na right cards zote ziwe `width: 100%`
- Min height: `auto`
- Spacing kati ya cards: `16px`

### Page-Specific Form Card Height Guide
- Login: `min-height 520px`
- Forgot password: `min-height 460px`
- Reset password: `min-height 500px`

Note:
- Tumia `min-height` badala ya fixed `height` ili content isikatike kwenye translation ndefu au error messages nyingi.
- Kwenye mfumo wowote mpya, ruhusu card ku-grow vertically kulingana na alerts na validation feedback.

## Shared Components

### 1) Auth Card
- White surface na soft shadow.
- Ndani iwe na:
  - heading
  - subtitle
  - alert area (optional)
  - form fields
  - primary CTA
  - secondary links

### 2) Input Group
- Label juu ya field.
- Optional leading icon ndani ya input.
- Error text chini (`.invalid-feedback`).
- Support text optional (`.form-help`).

### 3) Primary Button
- Full width.
- Height minimum 46px.
- States:
  - default
  - hover
  - disabled
  - loading (`spinner + "Please wait..."`)

### 4) Feedback Alert
- Error alert top of form kwa global errors.
- Success alert kwa hatua zilizokamilika (mfano reset link sent).

### 5) Password Visibility Toggle
- Eye icon button ndani ya password input.
- Keyboard accessible (`aria-label="Show password"` / `Hide password`).

### 6) Left Panel Big Icon (Hero/Watermark)
- Purpose: kuipa panel identity ya haraka ya page husika.
- Recommended icon container class: `.auth-hero-icon`.
- Placement: juu-right ya left panel (`position: absolute; top: 24px; right: 24px;`).
- Size:
  - desktop: `120px` to `150px`
  - tablet: `96px`
  - mobile: `80px`
- Style:
  - soft gradient background ya brand color
  - border-radius: `24px` au `50%` (kutegemea theme)
  - opacity: `0.14` hadi `0.22` kama watermark
  - icon foreground size: `48px` (desktop), `36px` (mobile)
- Interaction: icon isiwe clickable, iwe decorative tu (`aria-hidden="true"`).
- Safety: hakikisha icon hai-overlap heading text; acha clear space ya angalau `88px` kutoka top area ya content.

## Page Specs

## A) Login UI

### Purpose
Kuingiza credentials na kuingia salama.

### Fields
- `email` (required, email format)
- `password` (required)
- `remember_me` (optional checkbox)
- hidden `csrf_token`

### Actions
- Primary: `Login`
- Link 1: `Forgot password?` -> forgot password page
- Link 2: `Create account` (optional kulingana na system)

### UX Rules
- Invalid fields zipate border ya `--auth-danger`.
- Error message iwe specific:
  - "Enter a valid email address."
  - "Password is required."
- Submit button ifungwe wakati request inaendelea.
- Press `Enter` kwenye password field ifanye submit.
- Left panel big icon recommendation: `shield-lock` au `door-open`.

## B) Forgot Password UI

### Purpose
User aombe reset link kwa email.

### Fields
- `email` (required, email format)
- hidden `csrf_token`

### Actions
- Primary: `Send reset link`
- Secondary link: `Back to login`

### UX Rules
- Success message isiweke wazi kama email ipo au haipo (security).
- Copy recommendation:
  - "If an account with that email exists, we have sent password reset instructions."
- Rate-limit hint (optional):
  - "Please wait a moment before trying again."
- Left panel big icon recommendation: `envelope-open` au `key`.

## C) Reset Password UI

### Purpose
User aweke password mpya kwa token halali.

### Fields
- `new_password` (required, minimum 8)
- `confirm_password` (required, must match)
- hidden: `selector`, `token`, `csrf_token`

### Actions
- Primary: `Reset password`
- Secondary link: `Back to login`

### UX Rules
- Password strength indicator (weak/medium/strong) optional lakini inapendekezwa.
- Inline rules:
  - at least 8 characters
  - at least 1 letter
  - at least 1 number
- Error messages:
  - "Password must be at least 8 characters."
  - "Passwords do not match."
- Success state:
  - "Password updated successfully. You can now log in."
  - CTA: `Go to login`
- Left panel big icon recommendation: `lock-reset` au `shield-check`.

## Validation States (All Pages)
- `default`: normal border.
- `focus`: glow ya `--auth-focus`.
- `invalid`: red border + message.
- `valid` (optional): green border + small check icon.
- `disabled`: opacity ndogo + cursor not-allowed.

## Accessibility Checklist
- Kila input iwe na `<label for="">`.
- Error messages ziunganishwe na `aria-describedby`.
- Color contrast minimum 4.5:1 kwa body text.
- Tab order iwe natural kutoka juu kwenda chini.
- Buttons na links ziwe identifiable bila color pekee (underline au icon support).

## Reusability Contract (For Other Systems)
Ili kuitumia kwa system nyingine, preserve hizi contracts:
- Field names: `email`, `password`, `new_password`, `confirm_password`.
- Hidden params: `csrf_token`, `selector`, `token` (kwa reset page).
- Consistent endpoints mapping:
  - Login submit endpoint
  - Forgot-password submit endpoint
  - Reset-password submit endpoint
- Shared CSS class names:
  - `.auth-shell`
  - `.auth-panel`
  - `.auth-card`
  - `.auth-input`
  - `.btn-brand`
  - `.auth-alert`

## Suggested File Split (Future Projects)
- `assets/css/auth.css` -> all auth UI styles
- `assets/js/auth-shared.js` -> common form behavior
- `assets/js/login.js`
- `assets/js/forgot-password.js`
- `assets/js/reset-password.js`

## Quick Wireframe

```text
+-------------------------------------------------------------+
| Left panel (branding/help) | Right panel (form action)     |
| - Logo                      | [Title] [Subtitle]            |
| - 3 key tips                | [Alert area]                  |
| - Security note             | [Fields...]                   |
|                             | [Primary Button]              |
|                             | [Secondary Links]             |
+-------------------------------------------------------------+
```

## Final Note
Hii UI design inalenga consistency na portability. Ukiihamisha kwenye system nyingine, badili branding colors/text lakini hifadhi layout pattern, field contracts, na validation behavior ili user experience ibaki imara.
