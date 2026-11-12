# Private TV camera access

Branch: `feature/tv-camera-agent` in both repositories. The existing FamilyCall app handles family calls and chat; the separate FamilyCall Camera app handles owner camera viewing.

## Everyday use

1. Once on the TV: pair FamilyCall Camera, grant camera and notification permissions, choose the USB camera and press **Enable remote access**.
2. Press Home and use the TV normally. Keep it powered, awake and online.
3. Open https://tv.theartvalley.net/admin/?tab=cameras, sign in as the administrator, and unlock camera access with your administrator password.
4. Click **View camera**. No TV-side answer button is needed. Click **Stop viewing** when finished.

The TV retains its pairing. Do not generate another pairing code or rebuild the APK when updating only the backend. The already installed first version of Camera Agent understands the STUN configuration returned by this backend.

## Update an already deployed camera backend

No new migration or Composer dependency is introduced by the simplified access update.

On Hostinger:

```bash
cd ~/domains/tv.theartvalley.net/public_html
git status --short
git pull --ff-only origin feature/tv-camera-agent
php -d 'disable_functions=system,shell_exec,exec,passthru,mysql_list_dbs,ini_alter,dl,symlink,link,chgrp,leak,popen,apache_child_terminate,virtual,mb_send_mail' artisan optimize:clear
```

Preserve any local changes before pulling; do not reset them. Existing camera rows and TV credentials remain valid.

On the Mac, rebuild and upload the admin frontend:

```bash
cd /Users/sambukhari/Desktop/Ali/familycall-expo-laravel/laravel-backend-giit
git status --short
git pull --ff-only origin feature/tv-camera-agent
npm ci
npm run build
scp -P 65002 -r public/build u220278738@72.61.121.23:domains/tv.theartvalley.net/public_html/public/
```

Use the clone containing `artisan` and `package.json`; adjust the directory if your clone is elsewhere. Refresh the admin page after uploading the matching build. If `CAMERA_REQUIRE_AUTHENTICATOR=true` was explicitly added to the server environment, change it to `false` and clear the configuration cache for password-only access.

## Connections

Camera viewing now tries a normal WebRTC connection with the same Google STUN discovery endpoint as FamilyCall. TURN configuration is optional; its absence no longer blocks the View button or signaling.

A direct connection can still fail through restrictive routers, firewalls or carrier NAT. No code can guarantee that every pair of networks allows a direct connection. If needed, configure a compatible hosted TURN service or Coturn relay; Laravel remains on its existing hosting. This application does not require buying a VPS just to attempt viewing.

Optional server-only settings:

```dotenv
CAMERA_TURN_URLS="turn:YOUR_RELAY_HOST:3478?transport=udp,turns:YOUR_RELAY_HOST:5349?transport=tcp"
CAMERA_TURN_SECRET="YOUR_PRIVATE_RELAY_SHARED_SECRET"
```

Use working URLs and a matching Coturn REST-auth shared secret of at least 32 characters. These are placeholders, not a deployed relay. Static usernames/passwords and provider-specific token APIs need a different adapter. Never commit the shared secret or put it in the APK. The backend issues short-lived credentials; setting variables alone does not prove connectivity. Retain authenticated relay access, private/loopback peer protection and bandwidth limits. TURN TLS needs its own trusted relay certificate.

## Administrator access

Password-only camera unlock is the default. Authentication, administrator authorization, CSRF protection, password throttling, camera ownership, session expiry, revocation and audit events remain enforced. Unlock lasts 30 minutes; one viewer can watch a camera for up to 15 minutes. Lock access ends the current browser's stream.

To opt into password plus authenticator:

```dotenv
CAMERA_REQUIRE_AUTHENTICATOR=true
```

Clear configuration caches after changing it. Existing authenticator enrollment is preserved. When enabling this requirement, a previous password-only grant cannot satisfy it; unlock again with the authenticator. An administrator with your password can access your cameras in password-only mode.

SSH recovery: `php artisan camera:reset-authenticator YOUR_ADMIN_EMAIL` ends all viewing, removes that owner's grant identity and invalidates existing unlocks. Use the Hostinger PHP override above when necessary. Enroll again if authenticator mode is enabled.

## Pairing for a new TV

In Admin → TV cameras, unlock and create a camera entry linked to the TV's existing FamilyCall account. Enter the one-time code and `https://tv.theartvalley.net` in the TV companion. Codes last 10 minutes and can be used once. Camera device credentials remain hashed on Laravel and encrypted with Android Keystore on the TV.

For a first backend installation, deploy this branch with PHP 8.4.1+, run `composer install --no-dev --prefer-dist --optimize-autoloader --no-scripts`, `artisan package:discover`, `artisan migrate --force`, and `artisan optimize:clear` using the Hostinger PHP override. Keep the existing APP_KEY and family account data. Build/upload the admin assets as above. No Boost installation is needed.

## Background behavior and verification

The service is enabled while its TV activity is visible. It can wait in the background while watching TV, and opens the camera only during an authorized viewing session. A persistent notification and Android's camera indicator remain visible; local Stop and admin Revoke remain available. No home-router port forwarding is required.

Android/OEM firmware can stop services. After reboot, force-stop, or service termination, reopen FamilyCall Camera and enable access again. Deep standby may power off USB or networking. This is not guaranteed off/standby camera access. FamilyCall calls take camera priority. The companion sends video only; the detected Etron webcam did not expose a USB microphone.

Test View/Stop locally, then from mobile data. Test normal TV playback, network loss, closing the browser, admin Lock, Revoke and a FamilyCall call. Capture must stop on expired viewer/network leases. A successful build does not verify physical camera capture or connectivity on your actual networks.

Schedule `artisan schedule:run` once per minute for abandoned-session cleanup. Keep HTTPS, APP_DEBUG=false, SESSION_SECURE_COOKIE=true and FAMILY_ADMIN_EMAIL configured. Never post passwords, pairing codes, device tokens, authenticator secrets, SDP or TURN secrets.

Rollback: stop remote access first, switch to the previously deployed commit and rebuild its matching admin assets. Camera tables can remain unused; do not broadly roll back family migrations.

References: [Android camera service requirements](https://developer.android.com/develop/background-work/services/fgs/service-types#camera), [WebRTC TURN connectivity](https://webrtc.org/getting-started/turn-server), [Coturn configuration](https://github.com/coturn/coturn/blob/master/examples/etc/turnserver.conf).
