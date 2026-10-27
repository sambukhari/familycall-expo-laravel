# FamilyCall — Expo + Laravel starter MVP

Private one-to-one calling concept for an Android phone and Google TV, using one Expo React Native app and a Laravel REST signaling API.

**Development starter only.** This repository does not include compiled APKs or a complete Laravel framework project. Google TV camera/microphone support is unverified, and Expo Go cannot run native WebRTC.

## Structure
- `mobile/`: Expo TypeScript + React Native WebRTC caller and receiver UI.
- `backend/`: Laravel 11/12 overlay (controller, middleware, models, routes, migration).

## Backend installation
1. Create a fresh Laravel project with `composer create-project laravel/laravel familycall-api`.
2. Copy everything from this repository's `backend/` into the new Laravel project, preserving folders.
3. Put `FAMILY_CALL_SECRET=<random-long-value>` in Laravel's `.env`.
4. Add `'family_call_secret' => env('FAMILY_CALL_SECRET'),` to `config/services.php`.
5. In `bootstrap/app.php`, register `pair.secret` middleware alias as shown in `backend/bootstrap/app.php.example` and ensure API routes are registered via `withRouting(api: __DIR__.'/../routes/api.php', ...)`.
6. Configure the database and run `php artisan migrate`.
7. For local tests, run `php artisan serve --host=0.0.0.0 --port=8000`.

## Expo installation
1. `cd mobile && npm install`. If versions conflict, check Expo SDK compatibility and use `npx expo install --fix`.
2. Change `expo.extra.apiBaseUrl` in `mobile/app.json` to the reachable Laravel URL. Use HTTPS for deployment.
3. Use the same `FAMILY_CALL_SECRET` value in both apps' pairing-secret fields. Device IDs must be `tv` and `phone`.
4. `npx expo prebuild --platform android` then `npx expo run:android --device` for a native dev build. **Expo Go will not work.**
5. Install the generated Android application onto Google TV if the TV supports sideloading and native camera access.

## Known limitations
- Both apps must be in foreground; no push notifications / background ringing.
- No TURN server; STUN-only WebRTC may fail over cellular and NAT. Add TURN for internet calling.
- Call race protection, device credentials, retention, and replay protection need hardening.
- A long-lived shared secret typed in both clients is only for an MVP.
- Android TV launcher and D-pad remote focus may need native configuration and further tests.
- Haier TV voice-search remote microphone is not assumed accessible to other apps.
- Not tested against the specific Haier 65-inch TV or verified as production-ready.
