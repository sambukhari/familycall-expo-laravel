# Private TV camera access

Branch: `feature/tv-camera-agent` in both FamilyCall repositories. This adds an owner-only **TV cameras** section and a separate Android app, **FamilyCall Camera** (`com.familycall.camera`). The normal FamilyCall app remains installed for calls and chat.

## What it does

- Pair a camera with a one-time, 10-minute code. The device bearer is stored hashed on Laravel and encrypted with Android Keystore on the TV.
- Require the administrator password and an authenticator code before pairing, revoking, or viewing. Unlock lasts 30 minutes; **Lock access** ends this browser's stream immediately.
- Send video only through WebRTC. The viewer never requests the administrator's camera or microphone. There is no recording or public camera URL.
- Allow one viewer per camera, for at most 15 minutes. The browser sends a heartbeat; abandoned sessions expire after 30 seconds. The TV also closes capture if authenticated polling fails for 10 seconds or its server-issued lease expires.
- Release the camera when its linked FamilyCall TV account has a ringing/connected call. Polling and Android camera priority handle the handoff; verify the handoff on the actual TV.
- Log pairing, revocation and viewing start/end metadata. SDP and candidates are cleared when sessions end. Scheduled cleanup removes abandoned signaling and session records older than seven days.

Anyone with your administrator password **and** authenticator, or control of the server, can act as you. Keep both private. This feature does not share camera access with family members.

## Android background behavior

Open the companion on the TV, grant camera and notification permissions, and press **Enable remote access**. Its camera foreground service can continue after returning to the home screen. A persistent notification provides a Stop control; Android also shows its camera indicator while capturing.

Android does not guarantee an ordinary app will run forever. After a reboot, force-stop, permission removal, or OEM service termination, reopen it and enable access. The boot receiver only posts a reminder; it does not bypass Android's camera restrictions. The TV must remain powered, awake, online and attached to the USB camera. Deep standby may power off USB/network. A timed CPU wake lock is used only during viewing, not to turn on the screen.

The app uses external cameras exposed through Android Camera2. It cannot add missing USB drivers. Your previously detected Etron webcam exposed video interfaces only, so this companion intentionally requests no microphone permission.

## 1. Prepare the relay

The Laravel website handles authentication and signaling; it is not the media relay. Configure a separate **Coturn server using TURN REST shared-secret authentication**, or a compatible managed service. Do this on a VPS/provider that permits a long-running TURN service and its network ports. Shared PHP hosting alone is insufficient.

Use a current Coturn release and its documented configuration. Required settings include `use-auth-secret`, a unique `static-auth-secret` of at least 32 characters, and your relay's `realm`. Keep the secret server-side. Configure listener/relay addresses and firewall ports for your provider, disable anonymous relay access, retain loopback protection, deny private/internal peer networks, and set allocation/bandwidth limits. A TURN TLS listener requires a certificate trusted by Android and browsers for the relay hostname. The website's HTTPS certificate does not configure that listener.

Use actual working URLs, for example:

```dotenv
CAMERA_TURN_URLS="turn:turn.example.com:3478?transport=udp,turns:turn.example.com:5349?transport=tcp"
CAMERA_TURN_SECRET="YOUR_PRIVATE_COTURN_SHARED_SECRET"
```

These are placeholders. Put the same shared secret in Coturn and the backend `.env`. Never put it in JavaScript, the APK, Git, a screenshot, or an admin message. Laravel issues credentials valid for 20 minutes to each authorized session. Static TURN usernames/passwords and provider-specific token APIs are not supported by this adapter. Pairing works before relay setup; viewing deliberately stays disabled until relay configuration is present. Configuration presence alone does not prove relay connectivity.

## 2. Deploy Laravel on Hostinger

Back up the database through your hosting panel first. This migration is additive; it does not replace family accounts or calls. No Laravel Boost installation is needed.

```bash
cd ~/domains/tv.theartvalley.net/public_html
git status --short
```

If `composer.lock` still has your previous server-only repair, preserve it before switching:

```bash
git stash push -m "Composer lock before TV camera branch" -- composer.lock
```

Preserve any other local changes separately; do not reset them. Then:

```bash
git fetch origin
git switch feature/tv-camera-agent
git pull --ff-only origin feature/tv-camera-agent
composer validate --no-check-publish
composer install --no-dev --prefer-dist --optimize-autoloader --no-scripts
```

The branch includes a repaired Composer lock with Inertia. Use **PHP 8.4.1 or newer** for these locked dependencies. Your reported Hostinger PHP 8.4.27 meets that requirement; your earlier Mac PHP 8.3 does not. A pre-existing `google/auth` wildcard warning from validation is not a lock-file failure.

For the Hostinger CLI restrictions already observed, use this command-local override. It enables `proc_open` for these Artisan commands while retaining the other previously listed restrictions:

```bash
fcphp() {
  php -d 'disable_functions=system,shell_exec,exec,passthru,mysql_list_dbs,ini_alter,dl,symlink,link,chgrp,leak,popen,apache_child_terminate,virtual,mb_send_mail' "$@"
}
fcphp artisan package:discover
fcphp artisan migrate --force
fcphp artisan optimize:clear
```

Add the relay values from step 1 to the server `.env`. Keep the existing `APP_KEY`; changing it would invalidate encrypted data. Ensure HTTPS, `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`, and the existing `FAMILY_ADMIN_EMAIL` are configured. Run `fcphp artisan optimize:clear` after editing the environment.

Schedule Laravel's `artisan schedule:run` once per minute with your hosting panel's cron facility if not already configured. Use the same PHP binary and CLI override if that cron environment disables `proc_open`. The schedule calls `camera:prune` in-process. A missed cron does not let the TV stream past its local lease; cron ensures abandoned signaling data is cleaned promptly.

## 3. Build and upload the admin frontend from the Mac

Use the backend clone that contains `package.json` and `artisan`. Your last reported clone was:

```bash
cd /Users/sambukhari/Desktop/Ali/familycall-expo-laravel/laravel-backend-giit
git status --short
git fetch origin
git switch feature/tv-camera-agent
git pull --ff-only origin feature/tv-camera-agent
npm ci
npm run build
scp -P 65002 -r public/build u220278738@72.61.121.23:domains/tv.theartvalley.net/public_html/public/
```

Use Node 22.12+ or a supported newer LTS. If your backend clone is the parent directory instead, change the `cd` accordingly. Frontend compilation needs Node, not the Mac's PHP. On Hostinger, run `fcphp artisan optimize:clear` again, then open `https://tv.theartvalley.net/admin/?tab=cameras`.

## 4. Build and install the separate camera app

In `sambukhari/tv-call`, check out the same branch and follow [`camera-agent/README.md`](https://github.com/sambukhari/tv-call/blob/feature/tv-camera-agent/camera-agent/README.md). The companion is a standalone native Gradle project. It does not use Expo Go or require rebuilding the normal FamilyCall app.

## 5. Pair and verify

1. In **Admin → TV cameras**, enter the admin password, add the displayed key to an authenticator app as a time-based account, then enter its six-digit code. Keep your device clock correct. Used codes cannot be replayed.
2. Create a camera entry and select the **TV's existing FamilyCall account**, not a phone account. Copy the one-time pairing code.
3. On the TV companion, enter your HTTPS site root and pairing code. Choose the external USB camera and enable remote access. Press Home on the TV remote.
4. In admin, select **View camera**. Initially test locally, then test from mobile data or another network. Video should appear; no audio is expected.
5. Stop viewing, close the tab, disconnect the network, revoke the camera, and start a FamilyCall call in separate tests. Confirm the camera indicator turns off and capture stops. Re-enable/re-pair only when the UI asks.
6. Test a TV restart: the app should require explicit enablement again. Do not expect silent automatic camera access after reboot.

No physical-TV or real-relay test can be inferred from a successful build. Check these cases on the Haier TV before relying on unattended access.

## Recovery and rollback

- Lost authenticator: from authenticated SSH, run `fcphp artisan camera:reset-authenticator YOUR_ADMIN_EMAIL`. Confirm the prompt. This ends viewing sessions and invalidates existing camera unlock grants; enroll again in admin. There is no password-only web reset.
- Revoked camera: its device bearer stops working. Create a new pairing code and pair the TV again.
- Missing picture: check the TV is enabled, camera permissions are allowed, no FamilyCall call is active, and the relay is reachable. Never post bearer tokens, pairing codes, SDP, candidates, or TURN credentials in logs.
- Rollback: stop remote access on the TV first. Switch both repositories back to `feature/family-accounts-calling` (the immediate previous system), rebuild/upload its admin assets and install dependencies from its matching lock. `main` remains the older backup. New camera tables can remain unused; do not run a general `migrate:rollback` against family data.

## Reference

- [Android camera foreground service requirements](https://developer.android.com/develop/background-work/services/fgs/service-types#camera)
- [Coturn configuration reference](https://github.com/coturn/coturn/blob/master/examples/etc/turnserver.conf)
- [WebRTC TURN connectivity](https://webrtc.org/getting-started/turn-server)
