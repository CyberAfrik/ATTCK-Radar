<script setup>
import { computed, ref, watch, onMounted, nextTick } from 'vue';
import { store, coverage, onIds, plannedIds, api, LEVEL_FR } from '../store';
import RadarChart from '../components/RadarChart.vue';
import RulePanel from '../components/RulePanel.vue';
import ExportForm from '../components/ExportForm.vue';

const STATE_FR = { on: 'détectable', planned: 'avec une source prévue', gap: 'angle mort', none: 'aucune règle connue' };
const empty = computed(() => !onIds.value.length && !plannedIds.value.length);
const langLabel = computed(() => (store.catalog.languages.find((l) => l.id === store.language) || {}).label);

// --- Règles ---
const scope = ref('on');
const q = ref('');
const level = ref('');
const technique = ref('');
const items = ref([]);
const meta = ref({ total: 0, page: 1, pages: 1, levels: {} });
const busy = ref(false);
const failed = ref('');
const selected = ref(null);
let timer = null, seq = 0;

const scopeIds = computed(() => (scope.value === 'on' ? onIds.value : onIds.value.concat(plannedIds.value)));

async function fetchRules(page = 1) {
    const mine = ++seq;
    busy.value = true; failed.value = '';
    try {
        const data = await api('/rules/search', {
            sources: scopeIds.value, q: q.value || null, level: level.value || null, technique: technique.value || null, page,
        });
        if (mine !== seq) return;
        items.value = page === 1 ? data.items : items.value.concat(data.items);
        meta.value = data;
        if (page === 1 && window.matchMedia('(min-width: 1000px)').matches) {
            selected.value = data.items.length ? data.items[0].id : null;
        }
    } catch (e) {
        if (mine === seq) failed.value = e.message;
    } finally {
        if (mine === seq) busy.value = false;
    }
}
function refresh() { clearTimeout(timer); timer = setTimeout(() => fetchRules(1), 250); }
watch([scopeIds, q, level, technique], refresh, { deep: true });
onMounted(() => { if (!empty.value) fetchRules(1); });

const levelChips = computed(() => ['critical', 'high', 'medium', 'low'].filter((l) => meta.value.levels[l]).map((l) => ({ id: l, label: LEVEL_FR[l], n: meta.value.levels[l] })));
const allCount = computed(() => Object.values(meta.value.levels || {}).reduce((a, b) => a + b, 0));

async function pickTechnique(cell) {
    if (cell.state === 'none' || cell.state === 'gap') return;
    technique.value = cell.id;
    if (cell.state === 'planned') scope.value = 'all';
    await nextTick();
    document.getElementById('regles')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}
const techName = computed(() => (technique.value ? (store.catalog.techniques[technique.value] || {}).n : ''));
const sourceLabels = (ids) => ids.map((id) => (store.catalog.sourceById[id] || {}).label).filter(Boolean).slice(0, 2).join(', ');
const topGaps = computed(() => coverage.value.gaps.slice(0, 6));
</script>

<template>
    <div class="wrap page">
        <div v-if="empty" class="card pad center-box">
            <h1>Votre plan est vide</h1>
            <p class="muted">Indiquez au moins une source de logs pour voir les règles applicables et votre couverture.</p>
            <RouterLink to="/sources" class="btn btn-primary">Choisir mes sources</RouterLink>
        </div>

        <template v-else>
            <div class="between wrap-gap">
                <div class="page-head">
                    <h1>Votre plan de détection</h1>
                    <p class="muted">{{ onIds.length }} sources en place, {{ plannedIds.length }} prévues · requêtes au format {{ langLabel }}</p>
                </div>
                <a href="#export" class="btn btn-primary">
                    <svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 4v11M7 11l5 5 5-5M5 20h14" /></svg>
                    Recevoir en Excel
                </a>
            </div>
            <p v-if="store.notice" class="notice" role="status">{{ store.notice }}</p>

            <div class="kpi-row">
                <div class="card kpi-card"><b class="display accent">{{ coverage.pctOn }}%</b><span>des techniques détectables aujourd'hui ({{ coverage.nOn }} sur {{ coverage.total }})</span></div>
                <div class="card kpi-card"><b class="display amber">{{ coverage.pctPlan }}%</b><span>avec les sources prévues</span></div>
                <div class="card kpi-card"><b class="display">{{ coverage.rulesOn }}</b><span>règles applicables à vos sources</span></div>
                <div class="card kpi-card"><b class="display">{{ coverage.realGaps }}</b><span>{{ coverage.hasPriorities ? 'angles morts sur vos scénarios' : 'techniques sans source adaptée' }}</span></div>
            </div>

            <section class="card pad">
                <div class="between wrap-gap">
                    <h2>Carte de couverture ATT&amp;CK</h2>
                    <div class="legend">
                        <span><i class="sw s-on"></i>Détectable</span>
                        <span><i class="sw s-planned"></i>Avec source prévue</span>
                        <span><i class="sw s-gap"></i>Angle mort</span>
                        <span><i class="sw s-none"></i>Aucune règle connue</span>
                    </div>
                </div>
                <p class="small muted">Une case par technique. Cliquez sur une case colorée pour voir ses règles.</p>
                <div class="matrix-scroll">
                    <div class="matrix" :style="{ gridTemplateColumns: 'repeat(' + coverage.tactics.length + ', minmax(0, 1fr))' }">
                        <div v-for="t in coverage.tactics" :key="t.id" class="matrix-col">
                            <div class="matrix-head"><b>{{ t.name }}</b><span class="mono">{{ t.on }}/{{ t.total }}</span></div>
                            <button v-for="cell in t.cells" :key="cell.id" type="button" class="cell cell-btn" :class="'s-' + cell.state"
                                :title="cell.id + ' ' + cell.name + ' : ' + STATE_FR[cell.state]" :aria-label="cell.id + ' ' + cell.name + ', ' + STATE_FR[cell.state]"
                                @click="pickTechnique(cell)"></button>
                        </div>
                    </div>
                </div>
                <div class="matrix-list">
                    <details v-for="t in coverage.tactics" :key="t.id">
                        <summary><span>{{ t.name }}</span><span class="mono">{{ t.on }}/{{ t.total }}</span>
                            <span class="bar thin"><span class="bar-fill" :style="{ width: (t.total ? 100 * t.on / t.total : 0) + '%' }"></span></span></summary>
                        <ul>
                            <li v-for="cell in t.cells" :key="cell.id">
                                <button type="button" class="tech-line" @click="pickTechnique(cell)">
                                    <i class="sw" :class="'s-' + cell.state"></i><span class="mono small">{{ cell.id }}</span><span>{{ cell.name }}</span>
                                    <span class="small muted">{{ STATE_FR[cell.state] }}</span>
                                </button>
                            </li>
                        </ul>
                    </details>
                </div>
            </section>

            <div class="grid-2">
                <section class="card pad">
                    <h2>Radar par tactique</h2>
                    <div class="legend">
                        <span><i class="line-on"></i>Aujourd'hui</span>
                        <span><i class="line-plan"></i>Avec les sources prévues</span>
                    </div>
                    <RadarChart :tactics="coverage.tactics" />
                </section>

                <section class="card pad">
                    <h2>Angles morts prioritaires</h2>
                    <p class="small muted">{{ coverage.hasPriorities ? 'Techniques de vos scénarios que vos sources en place ne permettent pas de voir.' : 'Techniques que vos sources en place ne permettent pas de voir. Choisissez des scénarios pour les classer.' }}</p>
                    <p v-if="!topGaps.length" class="muted">Aucun angle mort sur cette sélection.</p>
                    <ul class="gap-list">
                        <li v-for="g in topGaps" :key="g.id" class="gap">
                            <div><b>{{ g.name }}</b><span class="mono small muted">{{ g.id }}<template v-if="g.scenarios.length"> · {{ g.scenarios[0] }}</template></span></div>
                            <span v-if="g.state === 'planned'" class="pill pill-amber">Couverte par une source prévue</span>
                            <span v-else-if="g.providers.length" class="pill pill-amber">Ajouter : {{ g.providers.slice(0, 2).map((p) => p.label).join(' ou ') }}</span>
                            <span v-else class="pill">Aucune règle publique connue</span>
                        </li>
                    </ul>
                    <p v-if="coverage.gaps.length > topGaps.length" class="small muted">{{ coverage.gaps.length - topGaps.length }} autres dans l'export Excel.</p>
                    <RouterLink v-if="!coverage.hasPriorities" to="/priorites" class="small">Choisir mes scénarios</RouterLink>
                </section>
            </div>

            <section id="regles" class="rules">
                <div class="between wrap-gap">
                    <h2>Règles de détection</h2>
                    <div class="seg" role="group" aria-label="Périmètre des règles">
                        <button type="button" :class="{ active: scope === 'on' }" :aria-pressed="scope === 'on'" @click="scope = 'on'">Aujourd'hui</button>
                        <button type="button" :class="{ active: scope === 'all' }" :aria-pressed="scope === 'all'" @click="scope = 'all'">Avec sources prévues</button>
                    </div>
                </div>
                <div class="toolbar">
                    <label class="sr-only" for="rule-search">Rechercher une règle</label>
                    <input id="rule-search" v-model="q" type="search" class="input grow" placeholder="Rechercher une règle ou un identifiant (ex. kerberos, T1059)">
                    <div class="chips">
                        <button type="button" class="chip chip-btn" :class="{ active: !level }" @click="level = ''">Toutes · {{ allCount }}</button>
                        <button v-for="l in levelChips" :key="l.id" type="button" class="chip chip-btn" :class="{ active: level === l.id }" @click="level = l.id">{{ l.label }} · {{ l.n }}</button>
                        <button v-if="technique" type="button" class="chip chip-btn active" @click="technique = ''">{{ technique }} {{ techName }} ✕</button>
                    </div>
                </div>
                <p v-if="failed" class="alert" role="alert">{{ failed }}</p>

                <div class="rules-layout">
                    <div class="rule-list" :aria-busy="busy">
                        <p v-if="!busy && !items.length" class="muted">Aucune règle ne correspond à ces critères.</p>
                        <button v-for="r in items" :key="r.id" type="button" class="rule-item" :class="{ active: r.id === selected }" @click="selected = r.id">
                            <span class="between top"><b>{{ r.title }}</b><span class="level" :class="'lv-' + r.level">{{ LEVEL_FR[r.level] }}</span></span>
                            <span class="mono small muted">{{ r.techniques.slice(0, 3).join(' · ') || 'Sans technique' }} · {{ sourceLabels(r.sources) }}</span>
                        </button>
                        <button v-if="meta.page < meta.pages" type="button" class="btn btn-ghost btn-block" :disabled="busy" @click="fetchRules(meta.page + 1)">
                            {{ busy ? 'Chargement…' : 'Afficher plus de règles (' + (meta.total - items.length) + ' restantes)' }}
                        </button>
                    </div>
                    <div class="rule-detail" :class="{ open: selected }">
                        <RulePanel :rule-id="selected" @close="selected = null" />
                    </div>
                </div>
            </section>

            <ExportForm />
        </template>
    </div>
</template>
