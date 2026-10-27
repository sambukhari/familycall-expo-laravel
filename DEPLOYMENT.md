# FamilyCall API deployment / verification

The Laravel application at repository root serves the signaling endpoints; `backend/` is an older reference overlay. The Expo app lives in `mobile/`.

## After pulling on your server

Set `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://tv.theartvalley.net`, and a **long random** `FAMILY_CALL_SECRET` in the server-only `.env` (not GitHub). Configure the real database and permissions.

Run:

```bash
composer install --no-dev --optimize-autoloader
php artisan optimize:clear
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan route:list --path=api
```

Make sure your webserver serves the Laravel `public/` folder.

## Verify

```bash
curl -i https://tv.theartvalley.net/up
curl -i -H 'Accept: application/json' https://tv.theartvalley.net/api/calls/active
```

The first should return HTTP 200 if Laravel is healthy. The second should return HTTP 403 when authentication is enforced. To test your real secret without writing it into shell history:

```bash
read -rs FAMILY_CALL_SECRET; echo
curl -i 'https://tv.theartvalley.net/api/calls/active' \
 -H 'Accept: application/json' -H 'X-Device-ID: tv' \
 -H "X-Pair-Secret: $FAMILY_CALL_SECRET"
unset FAMILY_CALL_SECRET
```

When the database is available and no call exists, expect HTTP 200 and `{"call":null}`.

## Expo

`mobile/app.json` targets `https://tv.theartvalley.net/api`. Rebuild the native app: Expo Go cannot run React Native WebRTC. Both apps need matching pairing credentials. This MVP only polls while open, and STUN-only WebRTC may fail across networks until TURN is configured.

**Security:** Repository visibility is currently public. Change it to private if intended. Never commit `.env`, API secrets, production logs, or tokens.
