<script setup>
defineProps({ state: { type: String, default: '' } });
</script>

<template>
    <span class="state-icon" :class="'is-' + (state || 'off')" aria-hidden="true">
        <svg v-if="state === 'on'" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5l4.5 4.5L19 7.5" /></svg>
        <svg v-else-if="state === 'planned'" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="8" /><path d="M12 8v4l3 2" /></svg>
    </span>
</template>
