import { createRouter, createWebHistory } from 'vue-router';
import CookPage from './vue/pages/CookPage.vue';
import CreditsPage from './vue/pages/CreditsPage.vue';
import GroceriesPage from './vue/pages/GroceriesPage.vue';
import HistoryPage from './vue/pages/HistoryPage.vue';
import NotFoundPage from './vue/pages/NotFoundPage.vue';
import PantryPage from './vue/pages/PantryPage.vue';
import PutAwayPage from './vue/pages/PutAwayPage.vue';
import RecipeEditPage from './vue/pages/RecipeEditPage.vue';
import RecipePage from './vue/pages/RecipePage.vue';
import RecipesPage from './vue/pages/RecipesPage.vue';

export const router = createRouter({
    history: createWebHistory(),
    routes: [
        { path: '/', name: 'cook', component: CookPage },
        { path: '/pantry', name: 'pantry', component: PantryPage },
        { path: '/recipes', name: 'recipes', component: RecipesPage },
        { path: '/recipes/new', name: 'recipe-new', component: RecipeEditPage },
        { path: '/recipes/:id', name: 'recipe', component: RecipePage },
        { path: '/recipes/:id/edit', name: 'recipe-edit', component: RecipeEditPage },
        { path: '/groceries', name: 'groceries', component: GroceriesPage },
        { path: '/groceries/put-away', name: 'put-away', component: PutAwayPage },
        { path: '/history', name: 'history', component: HistoryPage },
        { path: '/credits', name: 'credits', component: CreditsPage },
        { path: '/:path(.*)*', name: 'not-found', component: NotFoundPage },
    ],
    scrollBehavior: () => ({ top: 0 }),
});
