<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { Clock3, Radio, RefreshCw, Save, Settings } from 'lucide-vue-next';

import LaraIoTLayout from '../../../layouts/laraiot/LaraIoTLayout.vue';
import StatusBadge from '../../../components/laraiot/StatusBadge.vue';
import { useLaraIoTUrl } from '../../../composables/laraiot/useLaraIoTUrl.js';

const props = defineProps({
    settings: { type: Object, required: true },
    timezones: { type: Array, default: () => [] },
    dateFormats: { type: Array, default: () => [] },
    timeFormats: { type: Array, default: () => [] },
    pollingIntervalLimits: { type: Object, default: () => ({ min: 1, max: 3600 }) },
});

const { laraiotUrl } = useLaraIoTUrl();

const form = useForm({
    application_mode: props.settings.application_mode ?? 'polling',
    polling_interval: props.settings.polling_interval ?? 10,
    timezone: props.settings.timezone ?? 'UTC',
    date_format: props.settings.date_format ?? 'd M Y',
    time_format: props.settings.time_format ?? 'H:i:s',
});

const submit = () => {
    form.put(laraiotUrl('settings'), { preserveScroll: true });
};
</script>

<template>
    <Head title="Application Settings" />
    <LaraIoTLayout>
        <div class="mx-auto max-w-4xl space-y-6">
            <div class="flex items-start gap-4">
                <div class="flex size-11 items-center justify-center rounded-xl bg-[#2583FF] text-white"><Settings class="size-5" /></div>
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight text-[#0B1735]">Application Settings</h1>
                    <p class="mt-1 text-sm text-slate-500">Configure communication mode, polling and date/time presentation.</p>
                </div>
            </div>

            <form class="space-y-6" @submit.prevent="submit">
                <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="grid gap-4 md:grid-cols-2">
                        <label class="flex cursor-pointer gap-4 rounded-xl border p-5" :class="form.application_mode === 'polling' ? 'border-slate-900 bg-slate-50' : 'border-slate-200'">
                            <input v-model="form.application_mode" type="radio" value="polling" class="sr-only" />
                            <RefreshCw class="size-5" />
                            <div><div class="flex gap-2"><span class="font-semibold">Polling</span><StatusBadge v-if="settings.application_mode === 'polling'" label="Current" status="success" /></div></div>
                        </label>
                        <label class="flex cursor-pointer gap-4 rounded-xl border p-5" :class="form.application_mode === 'websocket' ? 'border-slate-900 bg-slate-50' : 'border-slate-200'">
                            <input v-model="form.application_mode" type="radio" value="websocket" class="sr-only" />
                            <Radio class="size-5" />
                            <div><div class="flex gap-2"><span class="font-semibold">WebSocket</span><StatusBadge v-if="settings.application_mode === 'websocket'" label="Current" status="success" /></div></div>
                        </label>
                    </div>
                </section>

                <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <label class="block text-sm font-medium">Polling interval</label>
                    <input v-model.number="form.polling_interval" type="number" :min="pollingIntervalLimits.min" :max="pollingIntervalLimits.max" class="mt-2 block w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm" />
                </section>

                <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="mb-4 flex items-center gap-2"><Clock3 class="size-4" /><h2 class="font-semibold">Date & Time</h2></div>
                    <div class="grid gap-5 md:grid-cols-3">
                        <select v-model="form.timezone" class="rounded-lg border border-slate-300 px-3 py-2.5 text-sm"><option v-for="tz in timezones" :key="tz" :value="tz">{{ tz }}</option></select>
                        <select v-model="form.date_format" class="rounded-lg border border-slate-300 px-3 py-2.5 text-sm"><option v-for="o in dateFormats" :key="o.value" :value="o.value">{{ o.label }}</option></select>
                        <select v-model="form.time_format" class="rounded-lg border border-slate-300 px-3 py-2.5 text-sm"><option v-for="o in timeFormats" :key="o.value" :value="o.value">{{ o.label }}</option></select>
                    </div>
                </section>

                <div class="flex justify-end">
                    <button type="submit" :disabled="form.processing || !form.isDirty" class="inline-flex items-center gap-2 rounded-lg bg-[#2583FF] px-4 py-2.5 text-sm font-medium text-white disabled:opacity-60">
                        <Save class="size-4" /> {{ form.processing ? 'Saving...' : 'Save Settings' }}
                    </button>
                </div>
            </form>
        </div>
    </LaraIoTLayout>
</template>
