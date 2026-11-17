<script setup>
import { ref } from 'vue';
import { store, api, coverage } from '../store';

const email = ref('');
const consent = ref(false);
const website = ref('');
const status = ref('idle');
const message = ref('');

async function submit() {
    if (status.value === 'sending') return;
    status.value = 'sending'; message.value = '';
    try {
        await api('/export', {
            email: email.value, consent: consent.value, website: website.value,
            sources: store.states, playbooks: store.playbooks, language: store.language,
        });
        status.value = 'done';
    } catch (e) {
        status.value = 'error'; message.value = e.message;
    }
}
</script>

<template>
    <section id="export" class="card export">
        <div>
            <h2>Recevoir ce plan en Excel</h2>
            <p class="muted">Le classeur contient la synthèse, le plan de collecte, les {{ coverage.rulesPlan }} règles au format choisi et la liste des angles morts. Vous recevez par email un lien pour le télécharger et pour rouvrir ce plan plus tard.</p>
        </div>
        <div v-if="status === 'done'" class="sent" role="status">
            <b>C'est parti.</b>
            <span>Un email avec le lien de téléchargement du classeur arrive à l'adresse {{ email }} dans quelques minutes. Pensez à vérifier le dossier des indésirables.</span>
        </div>
        <form v-else class="form" @submit.prevent="submit">
            <div class="field">
                <label for="exp-email">Adresse email</label>
                <input id="exp-email" v-model="email" type="email" class="input" required maxlength="190" autocomplete="email" placeholder="vous@organisation.com">
            </div>
            <div class="hp" aria-hidden="true">
                <label for="exp-website">Ne pas remplir</label>
                <input id="exp-website" v-model="website" type="text" tabindex="-1" autocomplete="off">
            </div>
            <label class="check"><input v-model="consent" type="checkbox"><span>J'accepte de recevoir les actualités de CyberAfrik (facultatif).</span></label>
            <p v-if="status === 'error'" class="alert" role="alert">{{ message }}</p>
            <button type="submit" class="btn btn-primary" :disabled="status === 'sending'">{{ status === 'sending' ? 'Envoi en cours…' : 'Recevoir le lien du classeur' }}</button>
            <p class="small muted">L'adresse sert à vous envoyer le lien. Elle est conservée avec votre plan pour que ce lien fonctionne, pendant deux ans au plus.</p>
        </form>
    </section>
</template>
