<script setup>
import { computed } from 'vue';
import { store } from '../store';

const c = computed(() => store.catalog);
const facts = computed(() => [
    { n: c.value.tactics.length, l: 'tactiques MITRE ATT&CK Enterprise' },
    { n: Object.keys(c.value.techniques).length, l: 'techniques et sous-techniques' },
    { n: c.value.sources.length, l: 'sources de logs référencées' },
    { n: c.value.pack ? c.value.pack.rules : 0, l: 'règles de détection' },
]);
// Aperçu décoratif : motif fixe, sans rapport avec un plan réel
const preview = computed(() => c.value.tactics.map((t, ci) => t.techniques.slice(0, 22).map((_, i) => {
    const r = ((ci * 31 + i * 17) % 10) / 10;
    return ci < 2 ? 'gap' : r < 0.45 ? 'on' : r < 0.62 ? 'planned' : 'gap';
})));
</script>

<template>
    <section class="wrap hero">
        <div class="hero-copy">
            <p class="eyebrow mono">GRATUIT · SANS COMPTE · OPEN SOURCE</p>
            <h1>Dites-nous ce que vous collectez. Repartez avec vos règles de détection.</h1>
            <p class="lead">ATT&amp;CK Radar relie vos sources de logs aux techniques MITRE ATT&amp;CK qu'elles permettent de détecter, et vous donne les règles prêtes à adapter dans votre SIEM.</p>
            <div class="row">
                <RouterLink to="/sources" class="btn btn-primary btn-lg">Construire mon plan
                    <svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6" /></svg>
                </RouterLink>
                <a href="#etapes" class="btn btn-ghost btn-lg">Comment ça marche</a>
            </div>
        </div>
        <div class="card hero-card" aria-hidden="true">
            <div class="between"><b class="display">Carte de couverture ATT&amp;CK</b><span class="mono muted small">aperçu</span></div>
            <div class="mini-map">
                <div v-for="(col, i) in preview" :key="i" class="mini-col">
                    <span v-for="(s, j) in col" :key="j" class="cell" :class="'s-' + s"></span>
                </div>
            </div>
            <div class="legend">
                <span><i class="sw s-on"></i>Détectable aujourd'hui</span>
                <span><i class="sw s-planned"></i>Avec une source prévue</span>
                <span><i class="sw s-gap"></i>Angle mort</span>
            </div>
        </div>
    </section>

    <section class="band">
        <div class="wrap facts">
            <div v-for="f in facts" :key="f.l"><b class="display">{{ f.n.toLocaleString('fr-FR') }}</b><span>{{ f.l }}</span></div>
        </div>
    </section>

    <section id="etapes" class="wrap section">
        <h2>Trois étapes, quelques minutes</h2>
        <div class="grid-3">
            <div class="card pad">
                <span class="mono accent">01</span><h3>Mes sources</h3>
                <p>Cochez ce que vous collectez déjà et ce que vous prévoyez d'ajouter : annuaire, pare-feu, DNS, EDR, messagerie, cloud.</p>
            </div>
            <div class="card pad">
                <span class="mono accent">02</span><h3>Mes priorités</h3>
                <p>Choisissez les scénarios qui vous inquiètent : rançongiciel, compromission Active Directory, fraude par messagerie, exfiltration.</p>
            </div>
            <div class="card pad">
                <span class="mono accent">03</span><h3>Mon plan</h3>
                <p>Consultez les règles applicables, votre couverture par tactique et les angles morts, avec la source qui comblerait chacun.</p>
            </div>
        </div>
    </section>

    <section class="band">
        <div class="wrap section split">
            <div>
                <h2>Chaque règle, dans le langage de votre SIEM</h2>
                <p class="muted">Les règles sont écrites en Sigma puis converties automatiquement. Vous copiez la requête et vous l'adaptez à vos noms de champs.</p>
                <div class="chips">
                    <span v-for="l in c.languages" :key="l.id" class="chip">{{ l.label }}</span>
                </div>
            </div>
            <div class="card pad">
                <h3>Recevez votre plan en Excel</h3>
                <p>Synthèse de couverture, plan de collecte, règles de détection et angles morts, dans un classeur à télécharger depuis un lien reçu par email. Aucun compte à créer.</p>
                <RouterLink to="/sources" class="btn btn-primary">Commencer mon plan</RouterLink>
            </div>
        </div>
    </section>
</template>
