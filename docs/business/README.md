# Business rules

| UI term | Code | Page |
|---|---|---|
| Account, sign-in, sign-out | `Domain\Identity\User`, `Application\Identity\SignIn\*`, `Infrastructure\Security\Accounts\*` | [accounts.md](accounts.md) |
| Kitchen, member | `Application\Identity\KitchenMembers` | [accounts.md](accounts.md) |
| Language | `Presentation\LocaleListener` | [accounts.md](accounts.md) |
| Security headers, crawlers, sign-in rate limit, same-origin writes | `Presentation\Web\{SecurityHeaders, Security\LoginRateLimit}`, `Presentation\Api\SameOriginGuard` | [accounts.md](accounts.md) |
| Ingredient, stock, place, unit, running low, out | `Domain\Pantry\{Ingredient, Location, Unit}`, `Application\Pantry\*` | [pantry.md](pantry.md) |
| Quantity, fraction, twelfths | `Domain\Pantry\Quantity`, `assets/vue/pantry/quantity.js` | [pantry.md](pantry.md) |
| Track by hand | `Domain\Cooking\Planner` | [pantry.md](pantry.md) |
| Buy in (months), variant of | `Domain\Pantry\{Months, Ingredient::makeVariantOf}` | [pantry.md](pantry.md) |
| Recipe, line, some, makeable, ready after a prep, missing | `Domain\Cooking\{Recipe, RecipeLine, Need, Planner, Plan}`, `Application\Cooking\{WriteRecipe, EditRecipe, RemoveRecipe}` | [cooking.md](cooking.md) |
| Prep, output, yield, make | `Domain\Cooking\Recipe::make`, `Application\Cooking\MakePrep` | [cooking.md](cooking.md) |
| Meal, eater, cook it, log a meal | `Domain\Cooking\{Meal, MealLine, Portion}`, `Application\Cooking\LogMeal` | [cooking.md](cooking.md) |
| History, delete a meal, put ingredients back | `Application\Cooking\{MealHistory, RemoveMeal}` | [cooking.md](cooking.md) |
| Groceries, shopping trip, suggested, homemade | `Domain\Groceries\{GroceryList, GroceryItem, Suggestion}`, `Application\Groceries\*` | [groceries.md](groceries.md) |
| Prepare, shop, tick, put away, not bought | `Application\Groceries\{StartGroceryList, TickGroceryItem, PutAwayGroceries}`, `GroceriesPage`, `PutAwayPage` | [groceries.md](groceries.md) |
| Android app | `MainActivity`, `Hosts` | [apps.md](apps.md) |
