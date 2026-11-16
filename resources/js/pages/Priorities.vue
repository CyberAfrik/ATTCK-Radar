<script setup>
import { computed } from 'vue';
import { store, togglePlaybook, coverage } from '../store';
import StateIcon from '../components/StateIcon.vue';

const cards = computed(() => {
    const states = new Map();
    for (const t of coverage.value.tactics) for (const cell of t.cells) states.set(cell.id, cell.state);
    return store.catalog.playbooks.map((pb) => {
        const roots = [...new Set(pb.techniques.map((t) => (store.catalog.techniques[t] && store.catalog.techniques[t].p) || t))];
        const on = roots.filter((r) => states.get(r) === 'on').length;
        return { ...pb, total: roots.length, on, pct: roots.length ? Math.round((100 * on) / roots.length) : 0, selected: store.playbooks.includes(pb.id) };
    });
});
</script>

<template>
    <div class="wrap page narrow">
        <div class="page-head">
            <h1>Quels scénarios voulez-vous couvrir en priorité ?</h1>
            <p class="muted">Cette étape est facultative. Les scénarios choisis servent à classer vos angles morts : on vous montre d'abord ce qui manque pour détecter ces attaques.</p>
        </div>

        <div class="playbook-grid">
            <button v-for="pb in cards" :key="pb.id" type="button" class="source playbook" :class="pb.selected ? 'is-on' : 'is-off'"
                :aria-pressed="pb.selected" @click="togglePlaybook(pb.id)">
                <StateIcon :state="pb.selected ? 'on' : ''" />
                <span class="source-text">
                    <b>{{ pb.label }}</b>
                    <span class="small desc">{{ pb.description }}</span>
                    <span class="small muted">{{ pb.on }} techniques détectables sur {{ pb.total }} avec vos sources actuelles</span>
                    <span class="bar thin"><span class="bar-fill" :style="{ width: pb.pct + '%' }"></span></span>
                </span>
            </button>
        </div>

        <div class="row end">
            <RouterLink to="/sources" class="btn btn-ghost">Retour aux sources</RouterLink>
            <RouterLink to="/plan" class="btn btn-primary">{{ store.playbooks.length ? 'Voir mon plan' : 'Passer cette étape' }}
                <svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6" /></svg>
            </RouterLink>
        </div>
    </div>
</template>
