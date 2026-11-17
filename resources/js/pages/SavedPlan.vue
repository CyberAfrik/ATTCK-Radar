<script setup>
import { onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { store, api } from '../store';

const route = useRoute();
const router = useRouter();
const error = ref(false);

onMounted(async () => {
    try {
        const plan = await api('/plan/' + encodeURIComponent(route.params.token));
        for (const k of Object.keys(store.states)) delete store.states[k];
        for (const [k, v] of Object.entries(plan.sources || {})) if (store.catalog.sourceById[k]) store.states[k] = v;
        store.playbooks.splice(0, store.playbooks.length, ...(plan.playbooks || []));
        if (store.catalog.languages.some((l) => l.id === plan.language)) store.language = plan.language;
        store.notice = plan.new_rules > 0
            ? `Plan du ${plan.created_at} rechargé. ${plan.new_rules} règles applicables ont été ajoutées ou modifiées depuis.`
            : `Plan du ${plan.created_at} rechargé.`;
        router.replace('/plan');
    } catch (e) { error.value = true; }
});
</script>

<template>
    <div class="wrap page narrow">
        <p v-if="error" class="alert" role="alert">Ce lien de plan n'est pas reconnu. <RouterLink to="/sources">Construire un nouveau plan</RouterLink></p>
        <p v-else class="muted" aria-live="polite">Chargement de votre plan…</p>
    </div>
</template>
