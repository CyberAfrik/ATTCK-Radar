import { reactive, computed, watch } from 'vue';

const KEY = 'attck-radar-plan-v1';

export const TACTIC_FR = {
    reconnaissance: 'Reconnaissance', 'resource-development': 'Ressources', 'initial-access': 'Accès initial',
    execution: 'Exécution', persistence: 'Persistance', 'privilege-escalation': 'Élévation de privilèges',
    stealth: 'Furtivité', 'defense-impairment': 'Neutralisation des défenses', 'defense-evasion': 'Évasion',
    'credential-access': 'Accès aux identifiants', discovery: 'Découverte', 'lateral-movement': 'Mouvement latéral',
    collection: 'Collecte', 'command-and-control': 'Commande et contrôle', exfiltration: 'Exfiltration', impact: 'Impact',
};
export const LEVEL_FR = { critical: 'Critique', high: 'Élevée', medium: 'Moyenne', low: 'Faible', informational: 'Info' };

function load() {
    try {
        const raw = JSON.parse(localStorage.getItem(KEY) || 'null');
        if (raw && typeof raw === 'object') return raw;
    } catch (e) { /* stockage indisponible : on repart d'un plan vide */ }
    return null;
}
const saved = load();

export const store = reactive({
    catalog: null,
    error: null,
    states: (saved && saved.states) || {},
    playbooks: (saved && saved.playbooks) || [],
    language: (saved && saved.language) || 'splunk',
    notice: null,
});

watch(() => [store.states, store.playbooks, store.language], () => {
    try {
        localStorage.setItem(KEY, JSON.stringify({ states: store.states, playbooks: store.playbooks, language: store.language }));
    } catch (e) { /* ignoré */ }
}, { deep: true });

let loading = null;
export function loadCatalog() {
    if (store.catalog) return Promise.resolve(store.catalog);
    if (!loading) {
        loading = fetch('/api/catalog', { headers: { Accept: 'application/json' } })
            .then((r) => { if (!r.ok) throw new Error('Référentiel indisponible'); return r.json(); })
            .then((c) => {
                // Index dérivés, calculés une seule fois
                c.children = {};
                for (const [id, t] of Object.entries(c.techniques)) {
                    if (t.p) (c.children[t.p] = c.children[t.p] || []).push(id);
                }
                c.sourceById = Object.fromEntries(c.sources.map((s) => [s.id, s]));
                c.rootTechs = {};
                for (const [sid, list] of Object.entries(c.sourceTechs)) {
                    c.rootTechs[sid] = [...new Set(list.map((t) => (c.techniques[t] && c.techniques[t].p) || t))];
                }
                c.rootsAny = new Set(Object.values(c.rootTechs).flat());
                // Nettoie un plan enregistré avec un ancien référentiel
                for (const id of Object.keys(store.states)) if (!c.sourceById[id]) delete store.states[id];
                store.playbooks = store.playbooks.filter((p) => c.playbooks.some((x) => x.id === p));
                if (!c.languages.some((l) => l.id === store.language)) store.language = 'sigma';
                store.catalog = c;
                return c;
            })
            .catch((e) => { store.error = e.message; loading = null; throw e; });
    }
    return loading;
}

export function cycle(id) {
    const cur = store.states[id];
    if (!cur) store.states[id] = 'on';
    else if (cur === 'on') store.states[id] = 'planned';
    else delete store.states[id];
}
export function togglePlaybook(id) {
    const i = store.playbooks.indexOf(id);
    if (i >= 0) store.playbooks.splice(i, 1); else store.playbooks.push(id);
}
export function resetPlan() {
    for (const k of Object.keys(store.states)) delete store.states[k];
    store.playbooks.splice(0);
}

export const onIds = computed(() => Object.keys(store.states).filter((k) => store.states[k] === 'on'));
export const plannedIds = computed(() => Object.keys(store.states).filter((k) => store.states[k] === 'planned'));

function union(map, ids) {
    const out = new Set();
    for (const id of ids) for (const v of map[id] || []) out.add(v);
    return out;
}

/** Tout le calcul de couverture, recalculé à chaque changement d'état d'une source. */
export const coverage = computed(() => {
    const c = store.catalog;
    if (!c) return null;
    const on = onIds.value;
    const all = on.concat(plannedIds.value);
    const rootsOn = union(c.rootTechs, on);
    const rootsPlan = union(c.rootTechs, all);
    const stateOf = (r) => (rootsOn.has(r) ? 'on' : rootsPlan.has(r) ? 'planned' : c.rootsAny.has(r) ? 'gap' : 'none');

    const seen = new Map();
    const tactics = c.tactics.map((t) => {
        const cells = t.techniques.map((id) => {
            const state = stateOf(id);
            if (!seen.has(id)) seen.set(id, state);
            return { id, name: c.techniques[id].n, state };
        });
        const n = (s) => cells.filter((x) => x.state === s).length;
        return {
            id: t.id, name: TACTIC_FR[t.shortname] || t.name, total: cells.length, cells,
            on: n('on'), planned: n('planned'), gap: n('gap'), none: n('none'),
        };
    });
    let total = 0, nOn = 0, nPlan = 0;
    for (const s of seen.values()) { total++; if (s === 'on') nOn++; if (s === 'planned') nPlan++; }
    const pct = (n) => (total ? Math.round((100 * n) / total) : 0);

    // Techniques prioritaires : celles des scénarios choisis
    const prio = new Map();
    for (const pb of c.playbooks) {
        if (!store.playbooks.includes(pb.id)) continue;
        for (const t of pb.techniques) {
            const root = (c.techniques[t] && c.techniques[t].p) || t;
            if (!prio.has(root)) prio.set(root, []);
            prio.get(root).push(pb.label);
        }
    }
    const providers = (root) => c.sources
        .filter((s) => (c.rootTechs[s.id] || []).includes(root))
        .sort((a, b) => Number(a.auto) - Number(b.auto) || b.rules - a.rules);
    const candidates = prio.size ? [...prio.keys()] : [...c.rootsAny];
    const gaps = candidates.filter((r) => c.techniques[r] && !rootsOn.has(r)).map((r) => ({
        id: r, name: c.techniques[r].n, state: stateOf(r), scenarios: prio.get(r) || [],
        providers: providers(r).slice(0, 3),
    })).sort((a, b) => (b.scenarios.length - a.scenarios.length) || a.id.localeCompare(b.id));

    // Source la plus rentable à ajouter : nouvelles techniques gagnées, les prioritaires comptant triple
    let best = null;
    for (const s of c.sources) {
        if (store.states[s.id] || s.auto) continue;
        let gain = 0, score = 0;
        for (const r of c.rootTechs[s.id] || []) {
            if (!rootsOn.has(r)) { gain++; score += prio.has(r) ? 3 : 1; }
        }
        if (gain && (!best || score > best.score)) best = { source: s, gain, score };
    }

    return {
        tactics, total, nOn, nPlan, pctOn: pct(nOn), pctPlan: pct(nOn + nPlan),
        rulesOn: union(c.sourceRules, on).size, rulesPlan: union(c.sourceRules, all).size,
        gaps, realGaps: gaps.filter((g) => g.state !== 'planned').length, best, hasPriorities: prio.size > 0,
    };
});

export async function api(path, body) {
    const res = await fetch('/api' + path, body === undefined
        ? { headers: { Accept: 'application/json' } }
        : { method: 'POST', headers: { Accept: 'application/json', 'Content-Type': 'application/json' }, body: JSON.stringify(body) });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) {
        const first = data.errors ? Object.values(data.errors)[0][0] : null;
        throw new Error(first || data.message || 'Une erreur est survenue.');
    }
    return data;
}
