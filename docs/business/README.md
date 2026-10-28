# Business rules

| UI term | Code | Page |
|---|---|---|
| Account, sign-in, sign-out | `Domain\Identity\User`, `Application\Identity\SignIn\*`, `Infrastructure\Security\Accounts\*` | [accounts.md](accounts.md) |
| Kitchen, member | `Application\Identity\KitchenMembers` | [accounts.md](accounts.md) |
| Language | `Presentation\LocaleListener` | [accounts.md](accounts.md) |
| Security headers, crawlers, sign-in rate limit, same-origin writes | `Presentation\Web\{SecurityHeaders, Security\LoginRateLimit}`, `Presentation\Api\SameOriginGuard` | [accounts.md](accounts.md) |
| Ingredient, stock, place, unit, running low, out | `Domain\Pantry\{Ingredient, Location, Unit}`, `Application\Pantry\*` | [pantry.md](pantry.md) |
| Quantity, fraction, twelfths | `Domain\Pantry\Quantity`, `assets/vue/pantry/quantity.js` | [pantry.md](pantry.md) |
| Track by hand | `Domain\Pantry\Ingredient::covers` | [pantry.md](pantry.md) |

The Cooking and Groceries pages are added with their features.
