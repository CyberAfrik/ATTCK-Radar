<script setup>
import { ref, watch, computed } from 'vue';
import { store, api, LEVEL_FR } from '../store';

const props = defineProps({ ruleId: { type: Number, default: null } });
const emit = defineEmits(['close']);
const rule = ref(null);
const error = ref(null);
const lang = ref(store.language);
const copied = ref(false);

watch(() => props.ruleId, async (id) => {
    rule.value = null; error.value = null; copied.value = false;
    if (!id) return;
    try { rule.value = await api('/rules/' + id); } catch (e) { error.value = e.message; }
}, { immediate: true });
watch(() => store.language, (l) => { lang.value = l; });

const langs = computed(() => store.catalog.languages);
const query = computed(() => (rule.value ? rule.value.queries[lang.value] : null));
const note = computed(() => (langs.value.find((l) => l.id === lang.value) || {}).note);
const techs = computed(() => (rule.value ? rule.value.techniques.map((id) => ({ id, name: (store.catalog.techniques[id] || {}).n || '' })) : []));
const sources = computed(() => (rule.value ? rule.value.sources.map((id) => store.catalog.sourceById[id]).filter(Boolean) : []));
const safeRefs = computed(() => (rule.value ? (rule.value.references || []).filter((u) => /^https?:\/\//i.test(u)).slice(0, 5) : []));

async function copy() {
    try {
        await navigator.clipboard.writeText(query.value || '');
        copied.value = true;
        setTimeout(() => { copied.value = false; }, 1800);
    } catch (e) { copied.value = false; }
}
function pick(id) { lang.value = id; store.language = id; }
</script>

<template>
    <article class="card rule-panel" aria-live="polite">
        <div v-if="!ruleId" class="pad muted">Sélectionnez une règle dans la liste pour voir son détail et sa requête.</div>
        <div v-else-if="error" class="pad"><p class="alert" role="alert">{{ error }}</p></div>
        <div v-else-if="!rule" class="pad muted">Chargement de la règle…</div>
        <template v-else>
            <div class="pad rule-head">
                <div class="between top">
                    <span class="mono accent small">{{ techs.map((t) => t.id).join(' · ') || 'Sans technique associée' }}</span>
                    <button type="button" class="icon-btn only-mobile" aria-label="Fermer le détail" @click="emit('close')">
                        <svg aria-hidden="true" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18" /></svg>
                    </button>
                </div>
                <h3>{{ rule.title }}</h3>
                <p class="muted pre">{{ rule.description }}</p>
                <dl class="facts-grid">
                    <div><dt>Sévérité</dt><dd><span class="level" :class="'lv-' + rule.level">{{ LEVEL_FR[rule.level] || rule.level }}</span></dd></div>
                    <div><dt>Source de logs requise</dt><dd>{{ sources.map((s) => s.label).join(' ou ') }}</dd></div>
                    <div v-if="techs.length"><dt>Techniques</dt><dd>{{ techs.map((t) => t.id + ' ' + t.name).join(', ') }}</dd></div>
                    <div v-if="rule.falsepositives && rule.falsepositives.length"><dt>Faux positifs connus</dt><dd>{{ rule.falsepositives.join(' ; ') }}</dd></div>
                </dl>
            </div>
            <div class="tabs" role="tablist" aria-label="Langage de la requête">
                <button v-for="l in langs" :key="l.id" type="button" role="tab" class="tab" :class="{ active: l.id === lang, missing: !rule.queries[l.id] }"
                    :aria-selected="l.id === lang" @click="pick(l.id)">{{ l.label }}</button>
                <button type="button" class="btn btn-ghost btn-sm copy" :disabled="!query" @click="copy">{{ copied ? 'Copié' : 'Copier' }}</button>
            </div>
            <pre v-if="query" class="code" tabindex="0">{{ query }}</pre>
            <div v-else class="code empty">Cette règle n'a pas pu être convertie dans ce langage. Utilisez la version Sigma comme base.</div>
            <div class="pad small muted rule-foot">
                <p v-if="note">{{ note }}</p>
                <p>Règle {{ rule.origin === 'sigmahq' ? 'SigmaHQ' : rule.origin }}<template v-if="rule.author"> par {{ rule.author }}</template>, licence {{ rule.license }}.
                    <a v-if="/^https:\/\/github\.com\//.test(rule.upstream_url)" :href="rule.upstream_url" target="_blank" rel="noopener noreferrer">Voir la règle d'origine</a></p>
                <p v-if="safeRefs.length">Références :
                    <template v-for="(u, i) in safeRefs" :key="u"><a :href="u" target="_blank" rel="noopener noreferrer nofollow">[{{ i + 1 }}]</a>{{ ' ' }}</template>
                </p>
            </div>
        </template>
    </article>
</template>
