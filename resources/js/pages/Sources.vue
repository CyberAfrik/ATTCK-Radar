<script setup>
import { computed, ref, reactive } from 'vue';
import { store, cycle, coverage, onIds, plannedIds, resetPlan } from '../store';
import StateIcon from '../components/StateIcon.vue';

const q = ref('');
const open = reactive({});
const STATE = { on: 'En place', planned: 'Prévue' };

const groups = computed(() => {
    const needle = q.value.trim().toLowerCase();
    const match = (s) => !needle || (s.label + ' ' + s.description + ' ' + s.products.join(' ')).toLowerCase().includes(needle);
    return store.catalog.families.map((f) => {
        const all = store.catalog.sources.filter((s) => s.family === f.id && match(s));
        // Les sources secondaires restent repliées, sauf si elles sont sélectionnées ou recherchées
        const main = all.filter((s) => !s.auto || store.states[s.id] || needle);
        const more = all.filter((s) => s.auto && !store.states[s.id] && !needle);
        return { ...f, main, more };
    }).filter((g) => g.main.length || g.more.length);
});
const stateLabel = (s) => STATE[store.states[s.id]] || 'Absente';
</script>

<template>
    <div class="wrap page with-aside">
        <div class="main-col">
            <div class="page-head">
                <h1>Que collectez-vous aujourd'hui ?</h1>
                <p class="muted">Cliquez sur une source pour changer son état : absente, en place, puis prévue. Le résultat se met à jour en direct.</p>
            </div>

            <div class="toolbar">
                <label class="sr-only" for="src-search">Rechercher une source</label>
                <input id="src-search" v-model="q" type="search" class="input grow" placeholder="Rechercher une source (ex. DNS, Fortigate, M365)">
                <div class="legend">
                    <span><i class="sw s-on"></i>En place</span>
                    <span><i class="sw sw-planned"></i>Prévue</span>
                    <span><i class="sw sw-off"></i>Absente</span>
                </div>
            </div>

            <p v-if="!groups.length" class="muted">Aucune source ne correspond à « {{ q }} ».</p>

            <section v-for="g in groups" :key="g.id" class="family">
                <h2 class="family-title">{{ g.label }}</h2>
                <div class="source-grid">
                    <button v-for="s in g.main" :key="s.id" type="button" class="source" :class="'is-' + (store.states[s.id] || 'off')"
                        :aria-label="s.label + ', état : ' + stateLabel(s) + '. Cliquer pour changer.'" @click="cycle(s.id)">
                        <StateIcon :state="store.states[s.id]" />
                        <span class="source-text">
                            <b>{{ s.label }}</b>
                            <span class="small muted">{{ stateLabel(s) }} · {{ s.rules }} règles · {{ s.techniques }} techniques</span>
                            <span v-if="s.description" class="small desc">{{ s.description }}</span>
                        </span>
                    </button>
                </div>
                <template v-if="g.more.length">
                    <button type="button" class="link-btn" :aria-expanded="!!open[g.id]" @click="open[g.id] = !open[g.id]">
                        {{ open[g.id] ? 'Masquer' : 'Afficher' }} {{ g.more.length }} autres sources
                    </button>
                    <div v-if="open[g.id]" class="source-grid">
                        <button v-for="s in g.more" :key="s.id" type="button" class="source is-off compact"
                            :aria-label="s.label + ', absente. Cliquer pour changer.'" @click="cycle(s.id)">
                            <StateIcon />
                            <span class="source-text"><b>{{ s.label }}</b><span class="small muted">{{ s.rules }} règles</span></span>
                        </button>
                    </div>
                </template>
            </section>
        </div>

        <aside class="aside card" aria-label="Synthèse en direct">
            <h2 class="aside-title">Votre couverture</h2>
            <div class="meter">
                <div class="between"><span class="muted">Aujourd'hui</span><b class="display big accent">{{ coverage.pctOn }}%</b></div>
                <div class="bar"><div class="bar-fill" :style="{ width: coverage.pctOn + '%' }"></div></div>
            </div>
            <div class="meter">
                <div class="between"><span class="muted">Avec les sources prévues</span><b class="display mid amber">{{ coverage.pctPlan }}%</b></div>
                <div class="bar"><div class="bar-fill amber-bg" :style="{ width: coverage.pctPlan + '%' }"></div></div>
            </div>
            <div class="kpis-2">
                <div class="kpi"><b class="display">{{ coverage.rulesOn }}</b><span>règles applicables</span></div>
                <div class="kpi"><b class="display">{{ onIds.length }}<small v-if="plannedIds.length"> +{{ plannedIds.length }}</small></b><span>sources en place</span></div>
            </div>
            <div v-if="coverage.best" class="suggest">
                <span class="tag amber">Source la plus rentable à ajouter</span>
                <b>{{ coverage.best.source.label }}</b>
                <span class="small muted">{{ coverage.best.gain }} techniques supplémentaires détectables</span>
            </div>
            <div class="field">
                <label for="siem">Mon SIEM</label>
                <select id="siem" v-model="store.language" class="input">
                    <option v-for="l in store.catalog.languages" :key="l.id" :value="l.id">{{ l.label }}</option>
                </select>
            </div>
            <RouterLink to="/priorites" class="btn btn-primary btn-block">Continuer
                <svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6" /></svg>
            </RouterLink>
            <button v-if="onIds.length || plannedIds.length" type="button" class="link-btn center" @click="resetPlan">Tout effacer</button>
        </aside>
    </div>

    <div class="mobile-bar">
        <div class="between">
            <span><b class="display mid accent">{{ coverage.pctOn }}%</b> <span class="small muted">de couverture</span></span>
            <span class="small muted">{{ coverage.rulesOn }} règles</span>
        </div>
        <RouterLink to="/priorites" class="btn btn-primary btn-block">Continuer</RouterLink>
    </div>
</template>
