<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from '@/components/ui/breadcrumb';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

type Props = {
    breadcrumbs: BreadcrumbItemType[];
};

defineProps<Props>();

/**
 * On phones only the parent and the current page fit in the top bar; the
 * earlier crumbs appear from the md breakpoint up.
 */
function hiddenOnPhones(index: number, length: number): boolean {
    return index < length - 2;
}
</script>

<template>
    <Breadcrumb class="min-w-0">
        <BreadcrumbList class="flex-nowrap">
            <template v-for="(item, index) in breadcrumbs" :key="index">
                <BreadcrumbItem
                    class="min-w-0"
                    :class="
                        hiddenOnPhones(index, breadcrumbs.length) &&
                        'hidden md:inline-flex'
                    "
                >
                    <template
                        v-if="index === breadcrumbs.length - 1 || !item.href"
                    >
                        <BreadcrumbPage class="truncate">{{
                            $t(item.title ?? '')
                        }}</BreadcrumbPage>
                    </template>
                    <template v-else>
                        <BreadcrumbLink as-child>
                            <Link
                                :href="item.href"
                                class="truncate whitespace-nowrap"
                                >{{ $t(item.title ?? '') }}</Link
                            >
                        </BreadcrumbLink>
                    </template>
                </BreadcrumbItem>
                <BreadcrumbSeparator
                    v-if="index !== breadcrumbs.length - 1"
                    :class="
                        hiddenOnPhones(index, breadcrumbs.length) &&
                        'hidden md:block'
                    "
                />
            </template>
        </BreadcrumbList>
    </Breadcrumb>
</template>
