import '@fontsource/inter/400.css';
import '@fontsource/inter/500.css';
import '@fontsource/inter/600.css';
import '@fontsource/inter/700.css';
import '@fontsource/space-grotesk/500.css';
import '@fontsource/space-grotesk/700.css';
import '@fontsource/jetbrains-mono/400.css';
import '@fontsource/jetbrains-mono/500.css';
import { createApp } from 'vue';
import { createRouter, createWebHistory } from 'vue-router';
import App from './App.vue';
import Home from './pages/Home.vue';
import Sources from './pages/Sources.vue';
import Priorities from './pages/Priorities.vue';
import Plan from './pages/Plan.vue';
import SavedPlan from './pages/SavedPlan.vue';
import Changelog from './pages/Changelog.vue';

const router = createRouter({
    history: createWebHistory(),
    routes: [
        { path: '/', component: Home, meta: { step: 0, hit: 'accueil' } },
        { path: '/sources', component: Sources, meta: { step: 1, hit: 'sources' } },
        { path: '/priorites', component: Priorities, meta: { step: 2, hit: 'priorites' } },
        { path: '/plan', component: Plan, meta: { step: 3, hit: 'plan' } },
        { path: '/p/:token', component: SavedPlan, meta: { step: 3 } },
        { path: '/nouveautes', component: Changelog, meta: { step: 0, hit: 'nouveautes' } },
        { path: '/:rest(.*)*', redirect: '/' },
    ],
    scrollBehavior(to, from, saved) {
        if (saved) return saved;
        if (to.hash) return { el: to.hash, behavior: 'smooth', top: 80 };
        return { top: 0 };
    },
});

// Mesure d'audience anonyme : un compteur par étape, une fois par session de navigateur.
router.afterEach((to) => {
    const k = to.meta.hit;
    if (!k) return;
    try {
        if (sessionStorage.getItem('radar-hit-' + k)) return;
        sessionStorage.setItem('radar-hit-' + k, '1');
    } catch (e) { return; }
    fetch('/api/hit', { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json' }, body: JSON.stringify({ k }), keepalive: true }).catch(() => {});
});

createApp(App).use(router).mount('#app');
