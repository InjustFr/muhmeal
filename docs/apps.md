# Android app

The Android app shows the muhmeal website in a WebView. Rules: [business/apps.md](business/apps.md).

## Android (`android/`)

Kotlin, minSdk 26 (Android 8). Built on the Mac, outside Docker, with a JDK 17 to 26 whose `java` is on the PATH (Gradle 9.7 does not run on 27, which `brew install openjdk` installs): `brew install openjdk@21`, then `sudo ln -sfn "$(brew --prefix openjdk@21)/libexec/openjdk.jdk" /Library/Java/JavaVirtualMachines/openjdk-21.jdk`.

The repository is public and the site must not be discoverable, so the app address is never committed. Put it in `android/local.properties` (git-ignored); the build fails without it:

```properties
appUrl=https://<app host>
```

`sdk.dir` may live in the same file, otherwise `ANDROID_HOME` (set by the Makefile) is used.

```bash
make android-sdk     # once: Android command-line tools, platform 36, build and platform tools in ~/Library/Android/sdk
make apk             # signed release APK → android/app/build/outputs/apk/release/app-release.apk
make apk-install     # install or update it on the phone adb sees
make android-test    # JVM unit tests + lint
```

- **Signing key**: the first `make apk` creates `android/keystore.jks` and `android/keystore.properties` (git-ignored); it stops when only one of them is there. Generate them once, in the main checkout, never in a throwaway worktree. Back both up (a password manager): Android installs an update only when it is signed with the same key. Lost key = uninstall, install the new APK, sign in again.
- **Installing**: on the phone, turn on Developer options › USB debugging, plug it in and run `make apk-install`. Over Wi-Fi: Developer options › Wireless debugging, then `~/Library/Android/sdk/platform-tools/adb pair <ip:port>` and `adb connect <ip:port>` with the codes it shows. Without adb, copy the APK to the phone and open it, allowing the file manager to install unknown apps. Updates install over the app and keep the session.
- `versionCode` is the commit count, so commit before building an update.

## Limits of the WebView app

- **Passkeys** don't work in the app: sign in with the password. **Google sign-in** is refused by Google inside WebViews.
- Links to the site open in the browser, not in the app (no App Links): sign in there if asked.
- No widget, notification or offline mode: without a network the app retries until the site answers.
