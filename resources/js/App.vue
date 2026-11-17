<script setup>
import { computed, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { store, loadCatalog } from './store';

const route = useRoute();
const step = computed(() => route.meta.step || 0);
const steps = [
    { n: 1, label: 'Mes sources', to: '/sources' },
    { n: 2, label: 'Mes priorités', to: '/priorites' },
    { n: 3, label: 'Mon plan', to: '/plan' },
];
onMounted(() => loadCatalog().catch(() => {}));
</script>

<template>
    <a class="skip" href="#contenu">Aller au contenu</a>
    <header class="topbar">
        <div class="wrap topbar-in">
            <RouterLink to="/" class="brand" aria-label="ATT&CK Radar, accueil">
                <svg aria-hidden="true" width="32" height="32" viewBox="0 0 36 36" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="18" cy="18" r="15" /><circle cx="18" cy="18" r="8" />
                    <path d="M18 18 L29 9" stroke-linecap="round" /><circle cx="18" cy="18" r="2" fill="currentColor" />
                </svg>
                <span><b>ATT&amp;CK Radar</b><small>par CyberAfrik</small></span>
            </RouterLink>
            <nav v-if="step > 0" aria-label="Étapes">
                <ol class="steps">
                    <li v-for="s in steps" :key="s.n">
                        <RouterLink :to="s.to" class="step" :class="{ current: s.n === step, done: s.n < step }" :aria-current="s.n === step ? 'step' : undefined">
                            <span class="mono">{{ s.n }}</span><span class="step-label">{{ s.label }}</span>
                        </RouterLink>
                    </li>
                </ol>
            </nav>
            <RouterLink v-else to="/sources" class="btn btn-primary">Commencer</RouterLink>
        </div>
    </header>

    <main id="contenu">
        <div v-if="store.error" class="wrap page"><p class="alert" role="alert">Le référentiel n'a pas pu être chargé. Rechargez la page dans un instant.</p></div>
        <div v-else-if="!store.catalog" class="wrap page loading" aria-live="polite">Chargement du référentiel…</div>
        <RouterView v-else />
    </main>

    <footer class="footer">
        <div class="wrap footer-in">
            <span>ATT&amp;CK Radar est un projet communautaire de <a href="https://cyber-afrik.com" rel="noopener">CyberAfrik SARL</a>. Taxonomie : MITRE ATT&amp;CK® Enterprise{{ store.catalog && store.catalog.pack ? "\u00a0" + store.catalog.pack.attack : "" }}. Règles : <a href="https://github.com/SigmaHQ/sigma" rel="noopener">SigmaHQ</a> (DRL 1.1), <a href="https://github.com/splunk/security_content" rel="noopener">Splunk Security Content</a> (Apache 2.0), <a href="https://github.com/Azure/Azure-Sentinel" rel="noopener">Microsoft Sentinel</a> (MIT).</span>
            <span v-if="store.catalog && store.catalog.pack" class="mono"><RouterLink to="/nouveautes">Nouveautés</RouterLink> · Référentiel {{ store.catalog.pack.version }} · {{ store.catalog.pack.rules }} règles</span>
        </div>
    </footer>
</template>
