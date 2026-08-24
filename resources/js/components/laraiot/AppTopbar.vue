<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import {
    Menu,
    Radio,
    RefreshCw,
    Wifi,
    WifiOff,
    Zap,
} from 'lucide-vue-next';

import { useLaraIoTPolling } from '../../composables/laraiot/useLaraIoTPolling.js';

const emit = defineEmits(['toggle-sidebar']);
const page = usePage();

const laraiot = computed(() => page.props.laraiot ?? {});

const mode = computed(() =>
    laraiot.value?.mode === 'websocket' ? 'websocket' : 'polling',
);

const mqttConnected = computed(() => {
    const value = laraiot.value?.mqtt?.connected;

    if (value === true || value === false) {
        return value;
    }

    return null;
});

const modeLabel = computed(() =>
    mode.value === 'websocket' ? 'WebSocket' : 'Polling',
);

const mqttLabel = computed(() => {
    const reportedLabel = laraiot.value?.mqtt?.label;

    if (
        typeof reportedLabel === 'string'
        && reportedLabel.trim() !== ''
    ) {
        return `MQTT ${reportedLabel.trim()}`;
    }

    if (mqttConnected.value === true) {
        return 'MQTT Connected';
    }

    if (mqttConnected.value === false) {
        return 'MQTT Disconnected';
    }

    return 'MQTT Unknown';
});

const mqttDetail = computed(() => {
    const detail = laraiot.value?.mqtt?.detail;

    return typeof detail === 'string' && detail.trim() !== ''
        ? detail.trim()
        : mqttLabel.value;
});

const baseUrl = computed(() => {
    const configured = laraiot.value?.baseUrl ?? '/laraiot';
    const normalized = `/${String(configured).trim().replace(/^\/+|\/+$/g, '')}`;

    return normalized === '/' ? '' : normalized;
});

const relativePath = computed(() => {
    const current = String(page.url ?? '').split('?')[0].replace(/\/+$/, '');
    const base = baseUrl.value.replace(/\/+$/, '');

    if (base !== '' && current.startsWith(base)) {
        return current.slice(base.length).replace(/^\/+/, '');
    }

    return current.replace(/^\/+/, '');
});

const pageRefreshesMqttHealth = computed(() =>
    relativePath.value === ''
    || /^devices\/logical\/\d+$/.test(relativePath.value),
);

useLaraIoTPolling(
    ['laraiot'],
    () => !pageRefreshesMqttHealth.value,
);

const pageTitle = computed(() => {
    const explicitTitle = laraiot.value?.pageTitle;

    if (typeof explicitTitle === 'string' && explicitTitle.trim() !== '') {
        return explicitTitle.trim();
    }

    const path = relativePath.value;

    if (path === '') return 'Dashboard';
    if (path.startsWith('devices/physical')) return 'Physical Devices';
    if (path.startsWith('devices/logical')) return 'Logical Devices';
    if (path.startsWith('logs')) return 'Logs';
    if (path.startsWith('settings/device-types')) return 'Device Types';
    if (path.startsWith('settings')) return 'Application Settings';

    return 'LaraIoT';
});
</script>

<template>
    <header
        class="sticky top-0 z-30 flex h-20 items-center border-b border-slate-200/80 bg-white/95 px-4 backdrop-blur sm:px-6 lg:px-8"
    >
        <button
            type="button"
            class="mr-3 flex size-10 shrink-0 items-center justify-center rounded-xl text-slate-600 transition-colors hover:bg-slate-100 hover:text-[#0B1735] lg:hidden"
            aria-label="Open navigation"
            @click="emit('toggle-sidebar')"
        >
            <Menu class="size-5" />
        </button>

        <div class="flex min-w-0 flex-1 items-center">
            <h1 class="truncate text-xl font-semibold tracking-tight text-[#0B1735]">
                {{ pageTitle }}
            </h1>
        </div>

        <div class="flex shrink-0 items-center gap-2 sm:gap-3">
            <div
                class="flex h-10 items-center gap-2 rounded-full border px-3 text-xs font-semibold"
                :class="
                    mode === 'websocket'
                        ? 'border-violet-200 bg-violet-50 text-[#7C3AED]'
                        : 'border-blue-200 bg-blue-50 text-[#2583FF]'
                "
                role="status"
            >
                <Zap
                    v-if="mode === 'websocket'"
                    class="size-4"
                    :stroke-width="2"
                />
                <RefreshCw
                    v-else
                    class="size-4"
                    :stroke-width="2"
                />
                <span class="hidden sm:inline">{{ modeLabel }}</span>
            </div>

            <div
                class="flex h-10 items-center gap-2 rounded-full border px-3 text-xs font-semibold"
                :title="mqttDetail"
                :class="{
                    'border-emerald-200 bg-emerald-50 text-[#059669]': mqttConnected === true,
                    'border-red-200 bg-red-50 text-[#DC2626]': mqttConnected === false,
                    'border-slate-200 bg-slate-50 text-slate-500': mqttConnected === null,
                }"
                role="status"
                :aria-label="mqttLabel"
            >
                <span class="relative flex size-2">
                    <span
                        v-if="mqttConnected === true"
                        class="absolute inline-flex size-full animate-ping rounded-full bg-emerald-400 opacity-60"
                    />
                    <span
                        class="relative inline-flex size-2 rounded-full"
                        :class="{
                            'bg-[#059669]': mqttConnected === true,
                            'bg-[#DC2626]': mqttConnected === false,
                            'bg-[#94A3B8]': mqttConnected === null,
                        }"
                    />
                </span>

                <Wifi
                    v-if="mqttConnected === true"
                    class="hidden size-4 sm:block"
                    :stroke-width="2"
                />
                <WifiOff
                    v-else-if="mqttConnected === false"
                    class="hidden size-4 sm:block"
                    :stroke-width="2"
                />
                <Radio
                    v-else
                    class="hidden size-4 sm:block"
                    :stroke-width="2"
                />

                <span class="hidden md:inline">{{ mqttLabel }}</span>
                <span class="md:hidden">MQTT</span>
            </div>
        </div>
    </header>
</template>
