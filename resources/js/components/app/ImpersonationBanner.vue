<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { leave } from '@/routes/impersonate';

const page = usePage();
const impersonating = computed(() => page.props.auth.impersonating);
</script>

<template>
    <div
        v-if="impersonating"
        role="status"
        class="sticky top-0 z-50 flex flex-wrap items-center justify-center gap-3 bg-amber-500 px-4 py-2 text-sm font-medium text-amber-950"
        data-test="impersonation-banner"
    >
        <span>
            {{
                $t('Acting as :name (signed in as :original)', {
                    name: page.props.auth.user?.name ?? '',
                    original: impersonating.originalName,
                })
            }}
        </span>
        <Link
            :href="leave()"
            method="post"
            as="button"
            class="rounded-md bg-amber-950 px-3 py-1 text-amber-50 hover:bg-amber-900"
        >
            {{ $t('Leave') }}
        </Link>
    </div>
</template>
