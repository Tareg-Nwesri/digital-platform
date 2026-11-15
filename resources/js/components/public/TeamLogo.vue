<script setup lang="ts">
defineProps<{
    team: { name: string; logo: string | null };
    size?: 'lg';
}>();
</script>

<template>
    <span
        class="team-logo"
        :class="{ 'team-logo--lg': size === 'lg' }"
        aria-hidden="true"
    >
        <img
            v-if="team.logo"
            :src="team.logo"
            alt=""
            loading="lazy"
            decoding="async"
        />
        <template v-else>{{ team.name.charAt(0).toUpperCase() }}</template>
    </span>
</template>
