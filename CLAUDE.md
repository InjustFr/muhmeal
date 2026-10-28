# muhmeal — project guide for Claude

Shared kitchen manager from the MossyTrunk ecosystem. Two people share one kitchen: they track what is in the fridge, freezer and pantry, see which recipes can be cooked right now, cook them so the stock goes down, log meals that have no recipe, keep a meal history and run a grocery cycle (prepare, shop, put away).
UI languages: **English and French**. URLs, code, commits: **English**.

**Business rules live in [`docs/business/`](docs/business/README.md)** — read the relevant page before touching a domain concept, and update it in the same commit when a rule changes.

Architecture and conventions mirror `~/Sites/mossyleaf-studio/mossydew` (read its `CLAUDE.md` when in doubt); deliberate differences are listed at the end of this file.

## Code style

- **Never write comments** — no docblocks, no inline `//`/`#`, no `<!-- -->`, no `{# #}`. Only type-only PHPDoc the type system needs (`@return list<Ingredient>`) is allowed.
- **SOLID in every layer**: one use case per handler, one route per controller, one exception per file; extend by adding a class (a controller, an exception subclass), not by growing `switch`/`instanceof` branches; Domain and Application own their ports.
- **Never commit the production host name**: the repository is public. Docs and examples say `<app host>` or `https://muhmeal.example.com`; the real one only lives in the server `.env`, the proxy configuration, the accounts server and the git-ignored `android/local.properties`. Before committing, `git grep` for the domain of `DEPLOY_HOST` (top of the `Makefile`, the only file that may name it): nothing else may match.

Generic Symfony conventions: see `AGENTS.md` (this file wins when they disagree).

## Stack

- Symfony 8.1, PHP 8.5 (FrankenPHP), Doctrine ORM 3, PostgreSQL 18, ULIDs.
- Vue 3 SPA (vue-router) mounted in one Twig shell, bundled by **Vite** through `pentatrion/vite-bundle` (`vite_entry_script_tags('app')`). The sign-in error page is a second entry (`auth`).
- Tests: PHPUnit 13 (unit + functional, DAMA rollback), Vitest (pure JS modules), Playwright (e2e).

## Running things (always in Docker)

```bash
make up              # php + database + node (Vite dev server :5176) + mock mossyleaf accounts (:8096) → http://localhost:8095
make db              # create + migrate dev DB
make fixtures        # demo accounts `demo` and `sam` (fixtures/Story)
make migration       # doctrine:migrations:diff after mapping changes
make test            # PHPUnit; make test-unit / test-functional
make test-js         # Vitest
make deptrac / make cs / make cs-fix / make phpstan   # keep all at 0
make audit           # composer audit + npm audit (high and above, production dependencies)
make e2e             # Playwright (desktop + Pixel 7) against php-e2e (APP_ENV=test)
make qa              # everything above, the gate before a commit
```

## Backend architecture (Onion) — `src/`

| Layer | Contains | May depend on |
|---|---|---|
| `Domain/` | Entities (rich, **no setters**), value objects, domain services, repository **interfaces**, exceptions | nothing |
| `Application/` | Use cases `<Context>/<UseCase>/{Command, Handler}`, views, read ports (`KitchenMembers`), ports (`CurrentUser`, `Transaction`, `SingleSignOn`) | Domain |
| `Infrastructure/` | Doctrine repositories and read ports, security adapters (mossyleaf accounts OIDC client) | Domain, Application |
| `Presentation/` | `Api/` JSON controllers + payload DTOs, `Web/` Twig shells, auth pages and security listeners | Application, Domain |

Contexts: `Identity` and `Pantry` (exist), `Cooking`, `Groceries` (added step by step; `Shared` holds `Day`, `OptionalText` and the exception bases).

- Doctrine mapping = attributes on Domain entities. Dates without time are `date_immutable` built through `Domain\Shared\Day` — never `new \DateTimeImmutable()` for a day. **The client sends the day it means** (its local date), so the server has no time zone.
- Invariants throw `DomainException` subclasses (`<Context>/Exception/<Violation>.php`) → 422 `problem+json` (404 for `NotFound`) through `DomainExceptionListener`, message translated from `translations/exceptions+intl-icu.<locale>.yaml`.
- Handlers are invokable, end with `Transaction::commit()`. No bus, no domain events.
- **One shared kitchen, no owner scoping**: every signed-in user sees and edits everything, so there is no `OwnerScope`. Writes that two phones may send at once are relative (`adjust by +1`, "cook this", "put away"), never a stale absolute value; only an explicit "set exact amount" sends an absolute one.
- The page session (`#app-session`: `userId`, `displayName`, `email`, `members: [{id, name}]`, `logoutToken`, `accountsUrl`) is rendered by `templates/app.html.twig`; there is no endpoint for the current user or the members.

## Accounts & security

- **Sign-in = mossyleaf accounts** (Authentik at `accounts.mossyleaf.studio`, project `~/Sites/mossyleaf-studio/accounts`, shared with the other apps): no password, sign-up or invitation here. Accounts are invited in Authentik and need its `muhmeal` group. `/login` redirects there (OIDC code flow + PKCE, `OidcSingleSignOn`), `/login/check` (`AccountsAuthenticator`) exchanges the code, reads userinfo and runs `SignInHandler`: user found by `accountId` (OIDC `sub`), else a new user is created. Config: `ACCOUNTS_URL`, `OIDC_*` env vars.
- Dev and e2e use a mock OIDC server (`oidc` service, http://localhost:8096): type `demo` or `sam` (fixture accounts) or any name plus claims `{"email": "…", "name": "…"}` for a new account. PHPUnit uses `Tests\Support\FakeAccounts` (`https://accounts.test`).
- Session firewall with the `AccountsAuthenticator`, sessions in PostgreSQL (`PdoSessionHandler`), remember-me always on (so phones stay signed in), CSRF logout that also ends the mossyleaf session. The JSON API uses the session cookie; `SameOriginGuard` rejects cross-site writes.
- **Security headers** (`Presentation\Web\SecurityHeaders`, main requests only): a strict CSP (`default-src 'self'`, no inline script or style, no external host, `form-action` allows the accounts origin), HSTS over HTTPS, `nosniff`, `Referrer-Policy: same-origin`, `Permissions-Policy`, COOP/CORP `same-origin`, `X-Frame-Options: DENY`, `X-Robots-Tag: noindex, nofollow, noarchive`. The CSP is not sent in `dev` (Vite injects inline code). So the front end has **no inline `<script>` other than `type="application/json"` data blocks, no `style="…"` in markup or Twig, no `eval`/`new Function`, no external host**: Vue `:style` bindings (CSSOM) are fine, and Vite must not inline assets as `data:` fonts.
- **Not discoverable**: `public/robots.txt` disallows everything, `<meta name="robots" content="noindex, nofollow">` in `templates/base.html.twig`.
- **Rate limit**: `LoginRateLimit` limits `/login` and `/login/check` per address (`LOGIN_RATE_LIMIT`, 30 per 15 minutes) and answers 429. The e2e stack raises it.
- **Supply chain**: `make audit` runs `composer audit` and `npm audit --omit=dev --audit-level=high` in `make qa` and CI; `.github/dependabot.yml` bumps composer, npm (root and `e2e`), Docker, GitHub Actions and Gradle weekly.

## Frontend — `assets/`

- `app.js` mounts the SPA in `templates/app.html.twig` (`#app-session`, `#app-preload` filled by `AppShellController` through `ApiPreload`). A new page that loads data on mount adds its URL to `AppShellController::pageUrls()`, and shared data to `ALWAYS`.
- `vue/layouts/AppShell.vue` keeps the header (lowercase `muhmeal` wordmark in Fredoka, avatar menu) and the navigation mounted across routes: five tabs — **Cook** `/`, **Pantry** `/pantry`, **Recipes** `/recipes`, **Groceries** `/groceries`, **History** `/history` — as a bottom tab bar, a left rail from 48rem. Pages are thin orchestrators.
- Data goes through `composables/useApi.js` (`load` = stale-while-revalidate, writes send `X-Refresh`; sync across devices = refetch on focus and every minute while visible, `useSync`).
- Interactive widgets on **Reka UI**; icons **Phosphor** (`@phosphor-icons/vue`, `size` in rem): `duotone` for navigation and decoration, `bold` for actions, no emoji; no native select/checkbox/date inputs.
- CSS: BEM, `<style scoped>`, tokens in `assets/styles/tokens.css`, **rem only** (except inside SVG drawings). "Cozy farmhouse kitchen" look: a fixed palette (warm cream background, warm-white surfaces, warm brown ink, moss accent `#5b7f3a`, sunflower highlight, terracotta for danger and "out", mint / ice blue / wheat location tints `--location-*`) — never hardcode a colour outside `tokens.css`. Round shapes (`--radius` 1rem, `--radius-lg` 1.5rem on cards and sheets, pill chips), chunky buttons with a `--lip` that presses down on `:active`, touch targets of at least `--target` (2.75rem), soft warm shadows. Fonts: Fredoka (display) and Nunito (text), self-hosted via `@fontsource-variable`. No dark mode, no theme picker.
- **Never hardcode a user-visible string**: vue-i18n keys from `assets/vue/i18n/<locale>/<namespace>.json` (same keys in every locale; `messages.test.js` checks it). The language comes from the `locale` cookie, then the browser.
- Motion: one bold moment, the "Cook it" success (the pot bounces, the used stocks tick down, a CSS steam puff rises); `prefers-reduced-motion` disables it.
- Assets: Phosphor glyphs only, no images, no AI-generated art; every third-party asset is listed with its licence in `docs/assets.md` and on `/credits`.

## Android app — `android/`

A WebView shell of the production site: Kotlin, no Compose, no widget, `cloud.duprat.muhmeal`, the only logic worth a test is `Hosts` (in-app vs browser). Built on the Mac, not in Docker: `make android-sdk` once, then `make apk`, `make apk-install`, `make android-test` (needs JDK 17 to 26 on the PATH). The app URL comes from the git-ignored `android/local.properties` (`appUrl`) and the build fails without it; `android/keystore.*` is never committed (updates need the same key; `make apk` creates it once, in the main checkout only). Setup and limits: `docs/apps.md`, rules: `docs/business/apps.md`.

## Testing expectations

- PHPStan level 10 on `src`, `tests`, `fixtures`; ignores only in `phpstan.dist.neon`.
- Unit test every business rule; functional test every handler (KernelTestCase, real DB) and endpoint (WebTestCase); Playwright for user journeys.
- Every Playwright spec imports `test` and `expect` from `e2e/tests/support/test.js`, whose `page` fixture fails the test on any `securitypolicyviolation`. Sign in with `support/session.js` (`demo` or `sam`).

## Differences from MossyDew

- One shared kitchen instead of owner scoping; no `OwnerScope` and no `AtomicChange` (two requests on the same stock within milliseconds can still lose one change, accepted for two people).
- No time zone, theme, language or device-token settings, no widget, no iOS, no PWA or service worker.
- Security headers, the sign-in rate limit, `robots.txt` and dependency audits are new here.
- Phosphor icons, Fredoka and Nunito and a fixed palette instead of Lucide, Patua One and Inter and user themes.

## Git

One commit per feature, conventional messages. No co-author lines.
