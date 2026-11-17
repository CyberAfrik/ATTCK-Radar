<script setup>
import { onMounted, ref } from 'vue';
import { api, LEVEL_FR } from '../store';

const packs = ref(null);
const error = ref(false);
const KINDS = [['added', 'Règles ajoutées'], ['modified', 'Règles modifiées'], ['removed', 'Règles retirées']];
onMounted(async () => {
    try { packs.value = (await api('/changelog')).packs; } catch (e) { error.value = true; }
});
const total = (p) => p.counts.added + p.counts.modified + p.counts.removed;
</script>

<template>
    <div class="wrap page narrow">
        <div class="page-head">
            <h1>Nouveautés du référentiel</h1>
            <p class="muted">Le référentiel est reconstruit chaque semaine à partir de MITRE ATT&amp;CK et des règles SigmaHQ. Cette page liste ce qui a changé à chaque mise à jour.</p>
        </div>
        <p v-if="error" class="alert" role="alert">Le journal n'a pas pu être chargé.</p>
        <p v-else-if="!packs" class="muted" aria-live="polite">Chargement…</p>
        <section v-for="(p, i) in packs || []" :key="p.version" class="card pad">
            <div class="between wrap-gap">
                <h2>Mise à jour du {{ p.date }}</h2>
                <span class="mono small muted">ATT&amp;CK {{ p.attack }} · paquet {{ p.version }}</span>
            </div>
            <p v-if="!total(p)" class="muted">{{ i === packs.length - 1 ? 'Version initiale du référentiel.' : 'Aucun changement de règle dans cette mise à jour.' }}</p>
            <template v-else>
                <div class="chips">
                    <span class="chip">{{ p.counts.added }} ajoutées</span>
                    <span class="chip">{{ p.counts.modified }} modifiées</span>
                    <span class="chip">{{ p.counts.removed }} retirées</span>
                </div>
                <details v-for="[kind, label] in KINDS" :key="kind" v-show="p[kind].length" class="changes">
                    <summary>{{ label }} ({{ p.counts[kind] }})</summary>
                    <ul>
                        <li v-for="r in p[kind]" :key="r.title">
                            <span class="level" :class="'lv-' + r.level">{{ LEVEL_FR[r.level] || r.level }}</span>
                            <span>{{ r.title }}</span>
                            <span class="mono small muted">{{ r.techniques.split(' ').slice(0, 3).join(' · ') }}</span>
                        </li>
                    </ul>
                    <p v-if="p.counts[kind] > p[kind].length" class="small muted">et {{ p.counts[kind] - p[kind].length }} autres.</p>
                </details>
            </template>
        </section>
        <div><RouterLink to="/sources" class="btn btn-primary">Construire mon plan</RouterLink></div>
    </div>
</template>
