<script setup>
import { reactive } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Activity, CalendarDays, Filter, RotateCcw, Search } from 'lucide-vue-next';

import LaraIoTLayout from '../../../layouts/laraiot/LaraIoTLayout.vue';
import StatusBadge from '../../../components/laraiot/StatusBadge.vue';
import { useLaraIoTUrl } from '../../../composables/laraiot/useLaraIoTUrl.js';

const props = defineProps({
    logs: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    types: { type: Array, default: () => [] },
    logicalDevices: { type: Array, default: () => [] },
});

const { laraiotUrl } = useLaraIoTUrl();

const filterForm = reactive({
    search: props.filters.search ?? '',
    type: props.filters.type ?? '',
    logical_device_id: props.filters.logical_device_id ?? '',
    from: props.filters.from ?? '',
    to: props.filters.to ?? '',
});

const applyFilters = () => {
    router.get(laraiotUrl('logs'), {
        search: filterForm.search || undefined,
        type: filterForm.type || undefined,
        logical_device_id: filterForm.logical_device_id || undefined,
        from: filterForm.from || undefined,
        to: filterForm.to || undefined,
    }, { preserveState: true, preserveScroll: true, replace: true });
};

const resetFilters = () => {
    Object.assign(filterForm, { search: '', type: '', logical_device_id: '', from: '', to: '' });
    router.get(laraiotUrl('logs'), {}, { preserveState: true, preserveScroll: true, replace: true });
};

const statusForType = (type) => ({ command: 'info', state: 'success', system: 'neutral', error: 'danger' }[type] ?? 'neutral');
</script>

<template>
    <Head title="Logs" />
    <LaraIoTLayout>
        <div class="space-y-6">
            <div class="flex items-start gap-4">
                <div class="flex size-11 items-center justify-center rounded-xl bg-[#2583FF] text-white"><Activity class="size-5" /></div>
                <div><h1 class="text-2xl font-semibold tracking-tight text-[#0B1735]">Logs</h1><p class="mt-1 text-sm text-slate-500">Review LaraIoT activity events.</p></div>
            </div>

            <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <form class="grid gap-4 md:grid-cols-2 xl:grid-cols-5" @submit.prevent="applyFilters">
                    <div class="relative"><Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" /><input v-model="filterForm.search" class="w-full rounded-lg border border-slate-300 py-2.5 pl-9 pr-3 text-sm" placeholder="Title or description..." /></div>
                    <select v-model="filterForm.type" class="rounded-lg border border-slate-300 px-3 py-2.5 text-sm"><option value="">All types</option><option v-for="type in types" :key="type" :value="type">{{ type }}</option></select>
                    <select v-model="filterForm.logical_device_id" class="rounded-lg border border-slate-300 px-3 py-2.5 text-sm"><option value="">All devices</option><option v-for="d in logicalDevices" :key="d.id" :value="d.id">{{ d.name }}</option></select>
                    <div class="relative"><CalendarDays class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400" /><input v-model="filterForm.from" type="date" class="w-full rounded-lg border border-slate-300 py-2.5 pl-9 pr-3 text-sm" /></div>
                    <input v-model="filterForm.to" type="date" class="rounded-lg border border-slate-300 px-3 py-2.5 text-sm" />
                    <div class="flex gap-3 xl:col-span-5 xl:justify-end">
                        <button type="button" class="inline-flex items-center gap-2 rounded-lg border border-slate-300 px-4 py-2.5 text-sm" @click="resetFilters"><RotateCcw class="size-4" />Reset</button>
                        <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-[#2583FF] px-4 py-2.5 text-sm text-white"><Filter class="size-4" />Apply Filters</button>
                    </div>
                </form>
            </section>

            <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <div v-if="logs.data.length === 0" class="px-6 py-14 text-center text-sm text-slate-500">No log entries found.</div>
                <div v-else class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50"><tr><th class="px-5 py-3 text-left text-xs uppercase">Time</th><th class="px-5 py-3 text-left text-xs uppercase">Type</th><th class="px-5 py-3 text-left text-xs uppercase">Title</th><th class="px-5 py-3 text-left text-xs uppercase">Device</th><th class="px-5 py-3 text-left text-xs uppercase">Topic</th><th class="px-5 py-3 text-left text-xs uppercase">Description</th></tr></thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="log in logs.data" :key="log.id">
                                <td class="px-5 py-4 text-sm">{{ log.happened_at_formatted }}</td>
                                <td class="px-5 py-4"><StatusBadge :label="log.type" :status="statusForType(log.type)" /></td>
                                <td class="px-5 py-4 text-sm font-medium">{{ log.title }}</td>
                                <td class="px-5 py-4 text-sm">{{ log.logical_device?.name ?? '—' }}</td>
                                <td class="px-5 py-4"><code class="text-xs">{{ log.topic ?? '—' }}</code></td>
                                <td class="px-5 py-4 text-sm">{{ log.description ?? '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="logs.links?.length > 3" class="flex flex-wrap gap-1 border-t border-slate-200 px-5 py-4">
                    <template v-for="(link, index) in logs.links" :key="index">
                        <Link v-if="link.url" :href="link.url" preserve-scroll preserve-state class="rounded-lg border px-3 py-2 text-sm" :class="link.active ? 'bg-[#2583FF] text-white' : 'bg-white text-slate-600'" v-html="link.label" />
                        <span v-else class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-300" v-html="link.label" />
                    </template>
                </div>
            </section>
        </div>
    </LaraIoTLayout>
</template>
