# FamilyCall Control Center

Private **Laravel 13 + Inertia 2 + React 19** administration console. UI uses an original dark responsive dashboard built with free **Lucide** icons (MIT licensed).

## Features

- `/admin/login`: authenticated, rate-limited login; **only** the account whose email matches `FAMILY_ADMIN_EMAIL` may administer devices.
- `/admin`: overview, device presence, allow/block access, call history, admin and call lifecycle audit log.
- Devices: queue ping, inspect acknowledged vs pending status, send a test Expo push notification and see ticket acceptance.
- Messages: store up to 4000-character device-to-device texts; admin compose; private APIs for sending, polling and marking read.
- Privacy: no raw Expo tokens, shared pairing key, SDP, or ICE displayed in web admin.
- Blocking enforced by the existing `pair.secret` middleware across the existing call API.

## Production setup (run in the Laravel backend directory)

Make a backup of the production database and `.env` first. Verify PHP >= 8.3 and Node is installed (otherwise build frontend locally and deploy `public/build`).

```sh
git pull origin main
composer update inertiajs/inertia-laravel --with-all-dependencies --no-dev --optimize-autoloader
npm install
npm run build
```

Add the email of **your own admin account** to `.env` (do not put passwords in GitHub):

```dotenv
FAMILY_ADMIN_EMAIL=you@example.com
```

Keep `APP_KEY`, `FAMILY_CALL_SECRET` and `APP_ENV` as they already are. Ensure `APP_DEBUG=false` in production.

```sh
php artisan optimize:clear
php artisan migrate --force
php artisan familycall:admin-create
php artisan optimize
php artisan route:list --path=admin
```

Visit `https://tv.theartvalley.net/admin/login`. Enter the credentials you supplied interactively to `familycall:admin-create`. Never publish this admin panel without working HTTPS, an admin password, and CSRF/session protections.

**Lockfile note:** `composer.json` and `package.json` changed. Commit generated `composer.lock` and `package-lock.json` after validating a successful install in the deployment environment; future releases can then use reproducible `composer install` and `npm ci`.

## Device online definition

A device is shown **online** if it made an authenticated API request within the past **35 seconds**. The middleware writes heartbeat timestamps at most once per 20 seconds. It is *not* a WebSocket or continuous connection test.

## Client API endpoints

All require `X-Device-ID` and `X-Pair-Secret` and respect admin blocks.

| Method | Endpoint | Purpose |
| --- | --- | --- |
| POST | `/api/devices/heartbeat` | explicitly update last-seen |
| GET | `/api/devices/commands` | get queued ping and new_message commands |
| POST | `/api/devices/commands/{id}/ack` | confirm receipt |
| GET | `/api/messages?after=0` | retrieve chat messages involving the device |
| POST | `/api/messages` | create message; JSON `{"to":"tv","body":"Hello"}` |
| POST | `/api/messages/{id}/read` | confirm message read by its recipient |

**Important:** Current Android app versions do not yet poll these new command/message endpoints. Ping remains pending and saved chat messages will not appear in the mobile app until the mobile client is updated. Do not treat queued, Expo-accepted, or DB-saved as delivered/seen.

## Security limitations to consider before broader public exposure

- Device authentication is still based on the same preexisting shared family pairing secret. For truly independent device authorization, migrate to per-device enrollment credentials rather than reusing a single secret.
- `phone` and `tv` are the only device IDs allowed by the existing middleware. Expanding the roster requires an enrollment workflow and updated permissions, not just inserting records.
- Protect admin sign-in with 2FA and/or IP restrictions before exposing to multiple administrators.
- The dashboard audits call lifecycle and administrative actions, not raw Laravel logs or user media.
