# Custom login and logout

Open `/login` for the dedicated Admin/Principal/Teacher sign-in page. It has its own guest layout, split gradient welcome panel, school branding, email/password icons, password visibility toggle, Remember me, password recovery and responsive mobile form. It never renders the admin or teacher sidebar. Dashboard layout files were not edited; existing logout buttons and sign-in links now point to the canonical routes.

## Authentication behavior

- `GET /login` and password-recovery routes use `guest`. Already signed-in Admin/Principal users go to `/dashboard`; Teachers go to `/teacher/dashboard`.
- `POST /login` validates email/password/remember, throttles login attempts to five per minute per client IP, supports Laravel's remember cookie, regenerates the session ID and forgets `url.intended`. It always uses the user's role destination, including after an expired session or an old Attendance bookmark.
- `POST /logout` requires authentication and CSRF. It logs out, forgets `url.intended`, invalidates the session, regenerates the CSRF token, then redirects to the named `login` route with “Logged out successfully.” It never uses intended/back redirects. There is no GET logout route.
- All web responses passing through `NoCache` receive `private, no-store, no-cache, must-revalidate, max-age=0`, `Pragma: no-cache`, and `Expires: 0`. The panel and guest scripts reload restored browser back/forward snapshots so access is checked again.
- HTML requests with expired CSRF/session tokens redirect to `/login` with a session-expired message. JSON clients keep their normal 419 response.
- Legacy `GET /attendance/login` redirects to `/login`; legacy POST login/logout URLs call the same new actions for compatibility. No old Attendance login page or controller remains.
- Unknown or unauthorized account roles cannot sign in to either panel. Existing role middleware/policies still protect admin and teacher data.

## School branding

No `settings` or `school_settings` table exists in this database. `config/school.php` therefore provides school branding settings through the environment:

```dotenv
SCHOOL_NAME="Your School Name"
SCHOOL_LOGO=/storage/school/logo.png
```

Without these values the login uses the configured app name, or “School Management System” when APP_NAME is still Laravel, and the existing school icon. The logo must be a public asset URL. After editing environment settings run `php artisan config:clear`.

## Password recovery

`/forgot-password` uses Laravel's password broker. A reset link opens `/reset-password/{token}`, validates an eight-character confirmed password, rotates the remember token and sends the user back to `/login`. The response for an unknown email does not disclose whether an account exists. Reset requests are throttled. The new migration creates `password_reset_tokens` on clean installations and preserves this table when already present; rollback also preserves this shared authentication table.

The local `.env` currently has `MAIL_MAILER=log`, so reset emails are written to `storage/logs/laravel.log`; they are not delivered to an inbox. Configure SMTP/mail credentials for real delivery. Account passwords were not changed. The demo teacher account `teacher@test.com` was checked against `password` successfully in the local database.

## Commands

```powershell
php artisan migrate
php artisan config:clear
php artisan cache:clear
npm run build
php artisan test
php artisan serve
```

The migration and build were run in this workspace. No seeding is required for this login change. Existing Admin and Teacher accounts remain intact. If demo teacher data has never been seeded, use `php artisan db:seed --class=TeacherPanelSeeder` separately.

## Files created

| File | Purpose |
| --- | --- |
| `app/Http/Controllers/Auth/LoginController.php` | Canonical login/logout, throttling, remember-me and session cleanup. |
| `app/Http/Controllers/Auth/PasswordController.php` | Password reset request, link and password update flow. |
| `app/Http/Requests/LoginRequest.php` | Normalizes/validates email, password and remember-me. |
| `app/Services/LoginDestination.php` | One role-based destination used by login and guest middleware. |
| `app/Http/Middleware/NoCache.php` | Prevents caching web authentication and protected responses. |
| `config/school.php` | School name/logo configuration with fallbacks. |
| `database/migrations/2026_10_02_102000_create_password_reset_tokens.php` | Ensures password recovery storage without replacing an existing table. |
| `resources/views/layouts/guest.blade.php` | Independent split-screen guest layout with no sidebar. |
| `resources/views/auth/partials/logo.blade.php` | Custom logo or existing school-icon fallback. |
| `resources/views/auth/login.blade.php` | Login form, inline errors, show/hide password and remember-me. |
| `resources/views/auth/forgot-password.blade.php` | Email reset request form in the same guest style. |
| `resources/views/auth/reset-password.blade.php` | Confirmed new-password form. |
| `resources/css/auth.css` | Isolated gradient, form, mobile and autofill styles. |
| `resources/js/auth.js` | Accessible password toggle and browser-history revalidation. |
| `tests/Feature/AuthenticationTest.php` | Session cleanup, redirects, access, no-cache, throttling, reset and expiry tests. |
| `storage/app/verify-custom-login.mjs` | Local desktop/mobile browser and teacher login/logout/back-button verification. |
| `docs/custom-login.md` | Setup, file guide and manual testing instructions. |

## Files changed or removed

| File | Purpose |
| --- | --- |
| `bootstrap/app.php` | Registers no-cache middleware, canonical guest/role redirects and expiry feedback. |
| `routes/web.php` | Adds canonical auth/password routes and preserves legacy URL redirects/actions. |
| `resources/views/components/topbar.blade.php` | Admin logout/sign-in links use POST `/logout` and `/login`. |
| `resources/views/teacher/partials/topbar.blade.php` | Teacher sign-out uses the same POST route. |
| `resources/views/attendance/partials/header.blade.php` | Attendance sign-out uses the canonical POST route. |
| `resources/views/attendance/partials/profile.blade.php` | Sign-in link uses `/login`. |
| `app/Http/Middleware/AttendanceAuthenticated.php` | Guest fallback points to canonical login. |
| `app/Http/Middleware/FeesAuthenticated.php` | Guest fallback points to canonical login. |
| `app/Http/Controllers/StudentAttendanceController.php` | Guest fallback points to canonical login. |
| `app/Console/Commands/CreateAttendanceAdmin.php` | New-account instructions point to `/login`. |
| `resources/js/app.js` | Revalidates protected panels restored through browser history. |
| `vite.config.js` | Builds isolated authentication CSS/JS entry points. |
| `tests/Feature/AttendanceModuleTest.php` | Tests canonical login/logout names and guest destinations. |
| `tests/Feature/DashboardTest.php` | Tests canonical login destination for guest event access. |
| `tests/Feature/FeeSetupTest.php` | Tests canonical guest redirect. |
| `tests/Feature/ExampleTest.php` | Tests canonical guest redirect. |
| `public/build/manifest.json`, `public/build/assets/*` | Compiled authentication assets and history-revalidation script. |
| `app/Http/Controllers/AttendanceLoginController.php` | Removed obsolete Attendance-specific authentication controller. |
| `app/Http/Requests/AttendanceLoginRequest.php` | Removed obsolete request, replaced by LoginRequest. |
| `resources/views/attendance/login.blade.php` | Removed the old sidebar-based login page. |

## Manual tests

1. Open `/login` as a guest: see the split welcome/login screen with no sidebar. Resize to 390px/320px: the left panel hides, inputs fit and there is no horizontal overflow. Toggle the password eye and verify show/hide and keyboard focus.
2. Submit invalid credentials: inline email error appears, email remains filled, password is not retained. Five failed attempts within a minute block subsequent attempts until the limit expires.
3. Log in as Admin with existing credentials: go to `/dashboard`. Visit Attendance, click Logout in the topbar, confirm `/login` and “Logged out successfully.” Press Back: a protected panel must not reappear. Log in as Admin again: go to `/dashboard`, not Attendance.
4. Log out, sign in as `teacher@test.com` / `password`: go to `/teacher/dashboard`. Sign out: return to `/login`. The separate teacher dashboard/sidebar remains unchanged.
5. While signed in as either role, visit `/login`: return to that role's dashboard. As a guest visit `/dashboard`, `/students`, `/attendance/students`, or `/teacher/dashboard`: redirect to `/login`.
6. Check Remember me, sign in, and inspect the browser remember cookie. Logout must clear that authentication cookie and invalidate the session.
7. Open Forgot password, enter an existing account email, inspect the configured mailbox (or local Laravel log). Follow the reset link, set a new confirmed password, then sign in with it. A used token cannot be used again.
8. To simulate session expiry, open the login form in one tab, clear the browser session cookie or log out in another tab, and submit the stale form. Expect a login page with the session-expired message.

Automated checks: `php artisan test --filter=AuthenticationTest`, or `php artisan test` for all modules.
