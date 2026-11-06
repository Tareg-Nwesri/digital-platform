<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Languages } from '@lucide/vue';
import { computed } from 'vue';
import { DropdownMenuItem } from '@/components/ui/dropdown-menu';
import { update } from '@/routes/locale';

const page = usePage();
const target = computed(() => (page.props.locale === 'ar' ? 'en' : 'ar'));
</script>

<template>
    <DropdownMenuItem :as-child="true">
        <Link
            class="block w-full cursor-pointer"
            :href="update(target)"
            method="post"
            as="button"
            :lang="target"
            data-test="locale-switch"
        >
            <Languages class="me-2 h-4 w-4" />
            {{
                target === 'ar'
                    ? $t('Switch to Arabic')
                    : $t('Switch to English')
            }}
        </Link>
    </DropdownMenuItem>
</template>
