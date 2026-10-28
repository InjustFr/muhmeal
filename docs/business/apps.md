# Android app

The Android app is installed outside Google Play. It shows the website itself, so every screen and rule of the site applies unchanged. Setup: [../apps.md](../apps.md).

- **AP1** The app shows the site in a WebView: people sign in with their mossyleaf account inside it, as in a browser (A1). It adds ` MuhmealApp/<version>` to the WebView's user agent. — `MainActivity`
- **AP2** The app address is never committed (A6): it comes from `appUrl` in the git-ignored `android/local.properties`, and the build fails without it. — `app/build.gradle.kts`
- **AP3** Only the site and the mossyleaf accounts host stay in the app; a link to any other site opens in the browser. — `Hosts`
- **AP4** Back follows the WebView history. Coming back from the accounts host to the site clears that history, so back never returns to the sign-in page. — `MainActivity`, `Hosts`
- **AP5** Cookies are flushed to disk when the app pauses, so the session survives the app being killed. — `MainActivity`
- **AP6** A page that fails to load (network error or HTTP 500 and above) is retried after 5 seconds and reloaded when the app returns to the foreground; a crashed render process restarts the screen. — `MainActivity`
- **AP7** The app holds nothing of its own: only the `INTERNET` permission, no backup, no stored token. — `AndroidManifest.xml`
- **AP8** The system bars are the website's cream background with dark icons, whatever the phone's theme. The launcher icon is the favicon's cooking pot on the moss accent. — `MainActivity`, `ic_launcher.xml`
