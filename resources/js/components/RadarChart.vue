<script setup>
import { computed, ref, watch, onMounted } from 'vue';

const props = defineProps({ tactics: { type: Array, required: true } });
const CX = 210, CY = 205, R = 140;

const target = computed(() => props.tactics.map((t) => ({
    on: t.total ? t.on / t.total : 0,
    plan: t.total ? (t.on + t.planned) / t.total : 0,
})));
const shown = ref(target.value.map(() => ({ on: 0, plan: 0 })));

function animate() {
    const reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (reduce) { shown.value = target.value.map((v) => ({ ...v })); return; }
    const from = shown.value.map((v) => ({ ...v }));
    const to = target.value;
    const start = performance.now();
    const tick = (now) => {
        const k = Math.min(1, (now - start) / 450);
        const e = 1 - Math.pow(1 - k, 3);
        shown.value = to.map((v, i) => ({
            on: (from[i] ? from[i].on : 0) + (v.on - (from[i] ? from[i].on : 0)) * e,
            plan: (from[i] ? from[i].plan : 0) + (v.plan - (from[i] ? from[i].plan : 0)) * e,
        }));
        if (k < 1) requestAnimationFrame(tick);
    };
    requestAnimationFrame(tick);
}
watch(target, animate);
onMounted(animate);

const n = computed(() => props.tactics.length);
const pt = (i, r) => {
    const a = (2 * Math.PI * i) / n.value - Math.PI / 2;
    return [CX + r * Math.cos(a), CY + r * Math.sin(a)];
};
const poly = (key) => shown.value.map((v, i) => pt(i, R * Math.max(v[key], 0.03)).map((x) => x.toFixed(1)).join(',')).join(' ');
const rings = computed(() => [0.25, 0.5, 0.75, 1].map((f) => props.tactics.map((_, i) => pt(i, R * f).map((x) => x.toFixed(1)).join(',')).join(' ')));
const labels = computed(() => props.tactics.map((t, i) => {
    const [x, y] = pt(i, R + 30);
    const words = t.name.split(' ');
    const short = t.name.length > 16 ? words[0] + (words[1] ? ' ' + words[1].slice(0, 3) + '.' : '') : t.name;
    return { x, y, text: short, anchor: Math.abs(x - CX) < 20 ? 'middle' : x > CX ? 'start' : 'end' };
}));
const summary = computed(() => props.tactics.map((t) => `${t.name} : ${t.on} sur ${t.total}`).join(', '));
</script>

<template>
    <svg class="radar" viewBox="-40 0 500 410" role="img" :aria-label="'Radar de couverture par tactique. ' + summary">
        <polygon v-for="(r, i) in rings" :key="i" :points="r" fill="none" stroke="var(--border)" />
        <polygon :points="poly('plan')" fill="rgba(245,165,36,0.12)" stroke="var(--amber)" stroke-width="1.5" stroke-dasharray="5 4" />
        <polygon :points="poly('on')" fill="rgba(0,212,255,0.22)" stroke="var(--accent)" stroke-width="2" />
        <text v-for="(l, i) in labels" :key="i" :x="l.x" :y="l.y" :text-anchor="l.anchor" dominant-baseline="middle" font-size="11" fill="var(--muted)">{{ l.text }}</text>
    </svg>
</template>
