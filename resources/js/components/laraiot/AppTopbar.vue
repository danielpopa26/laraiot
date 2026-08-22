<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import {
    Activity,
    Menu,
    Radio,
    RefreshCw,
    Wifi,
    WifiOff,
} from 'lucide-vue-next';

const emit = defineEmits(['toggle-sidebar']);

const page = usePage();

/*
 * LaraIoT shared properties will eventually be supplied by the package
 * through Inertia::share().
 *
 * Expected structure:
 *
 * laraiot: {
 *     mode: 'polling' | 'websocket',
 *     mqtt: {
 *         connected: true | false | null,
 *     }
 * }
 */

const laraiot = computed(() => page.props.laraiot ?? {});

const mode = computed(() => {
    const value = laraiot.value?.mode;

    return value === 'websocket'
        ? 'websocket'
        : 'polling';
});

const mqttConnected = computed(() => {
    const value = laraiot.value?.mqtt?.connected;

    if (value === true) {
        return true;
    }

    if (value === false) {
        return false;
    }

    return null;
});

const modeLabel = computed(() => {
    return mode.value === 'websocket'
        ? 'WebSocket'
        : 'Polling';
});

const mqttLabel = computed(() => {
    if (mqttConnected.value === true) {
        return 'MQTT Connected';
    }

    if (mqttConnected.value === false) {
        return 'MQTT Disconnected';
    }

    return 'MQTT Unknown';
});
</script>

<template>
    <header
        class="sticky top-0 z-30 border-b border-slate-200 bg-white/95 backdrop-blur"
    >
        <div class="flex h-16 items-center gap-4 px-4 sm:px-6 lg:px-8">
            <!-- Mobile sidebar button -->
            <button
                type="button"
                class="flex size-10 shrink-0 items-center justify-center rounded-lg text-slate-600 transition hover:bg-slate-100 hover:text-slate-950 lg:hidden"
                aria-label="Open navigation"
                @click="emit('toggle-sidebar')"
            >
                <Menu class="size-5" />
            </button>

            <!-- Application context -->
            <div class="flex min-w-0 flex-1 items-center gap-3">
                <div
                    class="hidden size-9 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-700 sm:flex"
                >
                    <Activity class="size-5" />
                </div>

                <div class="min-w-0">
                    <div
                        class="truncate text-sm font-semibold text-slate-950"
                    >
                        LaraIoT
                    </div>

                    <div
                        class="hidden truncate text-xs text-slate-500 sm:block"
                    >
                        IoT monitoring and control
                    </div>
                </div>
            </div>

            <!-- System status -->
            <div class="flex shrink-0 items-center gap-2">
                <!-- MQTT status -->
                <div
                    class="flex items-center gap-2 rounded-lg border px-2.5 py-2 text-xs font-medium sm:px-3"
                    :class="{
                        'border-emerald-200 bg-emerald-50 text-emerald-700':
                            mqttConnected === true,

                        'border-red-200 bg-red-50 text-red-700':
                            mqttConnected === false,

                        'border-slate-200 bg-slate-50 text-slate-600':
                            mqttConnected === null,
                    }"
                >
                    <Wifi
                        v-if="mqttConnected === true"
                        class="size-4"
                    />

                    <WifiOff
                        v-else-if="mqttConnected === false"
                        class="size-4"
                    />

                    <Radio
                        v-else
                        class="size-4"
                    />

                    <span class="hidden md:inline">
                        {{ mqttLabel }}
                    </span>

                    <span class="md:hidden">
                        MQTT
                    </span>
                </div>

                <!-- Communication mode -->
                <div
                    class="flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-2 text-xs font-medium text-slate-700 sm:px-3"
                >
                    <Radio
                        v-if="mode === 'websocket'"
                        class="size-4"
                    />

                    <RefreshCw
                        v-else
                        class="size-4"
                    />

                    <span class="hidden sm:inline">
                        {{ modeLabel }}
                    </span>
                </div>
            </div>
        </div>
    </header>
</template>