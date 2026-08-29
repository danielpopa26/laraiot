<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import {
    Activity,
    Boxes,
    Cpu,
    Radio,
    WifiOff,
    Zap,
} from 'lucide-vue-next';

import LaraIoTLayout from '../../layouts/laraiot/LaraIoTLayout.vue';
import PhysicalDeviceCard from '../../components/laraiot/PhysicalDeviceCard.vue';
import StatCard from '../../components/laraiot/StatCard.vue';
import StatusBadge from '../../components/laraiot/StatusBadge.vue';
import { useLaraIoTUrl } from '../../composables/laraiot/useLaraIoTUrl.js';
import { useLaraIoTPolling } from '../../composables/laraiot/useLaraIoTPolling.js';

const props = defineProps({
    statistics: {
        type: Object,
        default: () => ({
            physicalDevices: 0,
            logicalDevices: 0,
            mqttTopics: 0,
        }),
    },
    recentActivity: {
        type: Array,
        default: () => [],
    },
    physicalDevices: {
        type: Array,
        default: () => [],
    },
    mqtt: {
        type: Object,
        default: () => ({
            connected: null,
        }),
    },
    mode: {
        type: String,
        default: 'polling',
    },
    requestedMode: {
        type: String,
        default: 'polling',
    },
    fallbackActive: {
        type: Boolean,
        default: false,
    },
    websocket: {
        type: Object,
        default: () => ({}),
    },
});

const { laraiotUrl } = useLaraIoTUrl();

useLaraIoTPolling([
    'statistics',
    'physicalDevices',
    'recentActivity',
    'mqtt',
    'websocket',
    'mode',
    'requestedMode',
    'fallbackActive',
    'laraiot',
]);

const websocketPresentation = computed(() => {
    if (props.websocket.configured !== true) {
        return {
            label: 'Not configured',
            description: 'Laravel Reverb support is not configured.',
            icon: Zap,
            tone: 'slate',
        };
    }

    if (props.websocket.live === true) {
        return {
            label: 'Live',
            description: 'Laravel Reverb is accepting WebSocket connections.',
            icon: Zap,
            tone: 'green',
        };
    }

    return {
        label: 'Offline',
        description: props.websocket.detail
            ?? 'Laravel Reverb is configured, but the server is not responding.',
        icon: WifiOff,
        tone: 'red',
    };
});

const mqttLabel = computed(() => {
    if (
        typeof props.mqtt.label === 'string'
        && props.mqtt.label.trim() !== ''
    ) {
        return props.mqtt.label.trim();
    }

    if (props.mqtt.connected === true) return 'Connected';
    if (props.mqtt.connected === false) return 'Disconnected';

    return 'Unknown';
});

const mqttTone = computed(() =>
    props.mqtt.connected === true
        ? 'green'
        : props.mqtt.connected === false
            ? 'red'
            : 'slate',
);

const mqttDescription = computed(() => {
    const topicCount = props.statistics.mqttTopics;
    const configuredTopics = `${topicCount} configured MQTT topic${topicCount === 1 ? '' : 's'}`;
    const detail = props.mqtt.detail;

    if (typeof detail !== 'string' || detail.trim() === '') {
        return configuredTopics;
    }

    return `${detail.trim()} ${configuredTopics}.`;
});

const activityStatus = (status) => {
    if (['success', 'warning', 'danger', 'info'].includes(status)) {
        return status;
    }

    return 'neutral';
};
</script>

<template>
    <Head title="Dashboard" />

    <LaraIoTLayout>
        <div class="space-y-6">
            <section>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-[#2583FF]">
                        System overview
                    </p>
                    <h2 class="mt-1.5 text-2xl font-semibold tracking-tight text-[#0B1735]">
                        Your IoT platform at a glance
                    </h2>
                    <p class="mt-1 text-sm text-slate-500">
                        Monitor devices, communication status and recent LaraIoT activity.
                    </p>
                </div>

            </section>

            <section
                class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4"
                aria-label="Platform summary"
            >
                <StatCard
                    title="Physical Devices"
                    :value="statistics.physicalDevices"
                    description="Registered physical equipment"
                    :icon="Cpu"
                    :href="laraiotUrl('devices/physical')"
                    tone="blue"
                />

                <StatCard
                    title="Logical Devices"
                    :value="statistics.logicalDevices"
                    description="Configured logical devices"
                    :icon="Boxes"
                    :href="laraiotUrl('devices/logical')"
                    tone="green"
                />

                <StatCard
                    title="MQTT Broker"
                    :value="mqttLabel"
                    :description="mqttDescription"
                    :icon="mqtt.connected === false ? WifiOff : Radio"
                    :tone="mqttTone"
                />

                <StatCard
                    title="WebSocket Server"
                    :value="websocketPresentation.label"
                    :description="websocketPresentation.description"
                    :icon="websocketPresentation.icon"
                    :tone="websocketPresentation.tone"
                />
            </section>

            <section aria-labelledby="equipment-overview-title">
                <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h2
                            id="equipment-overview-title"
                            class="text-lg font-semibold text-[#0B1735]"
                        >
                            Equipment Overview
                        </h2>
                        <p class="mt-1 text-sm text-slate-500">
                            Configuration progress, live values and available controls grouped by physical device.
                        </p>
                    </div>

                    <Link
                        :href="laraiotUrl('devices/physical/create')"
                        class="text-sm font-medium text-[#2583FF] hover:text-blue-700"
                    >
                        Add Physical Device
                    </Link>
                </div>

                <div
                    v-if="physicalDevices.length === 0"
                    class="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center"
                >
                    <Cpu class="mx-auto size-8 text-slate-300" />
                    <p class="mt-3 text-sm font-medium text-[#0B1735]">
                        No physical devices registered
                    </p>
                    <p class="mt-1 text-xs text-slate-500">
                        Add the first physical controller to begin configuring its logical devices.
                    </p>
                </div>

                <div v-else class="grid items-stretch gap-4 xl:grid-cols-2">
                    <PhysicalDeviceCard
                        v-for="physicalDevice in physicalDevices"
                        :key="physicalDevice.id"
                        :physical-device="physicalDevice"
                    />
                </div>
            </section>

            <section class="overflow-hidden rounded-2xl border border-slate-200/90 bg-white shadow-sm shadow-slate-900/[0.025]">
                <div class="border-b border-slate-100 px-5 py-4 sm:px-6">
                    <div class="flex items-center gap-2">
                        <Activity class="size-4 text-[#2583FF]" :stroke-width="1.9" />
                        <h2 class="text-base font-semibold text-[#0B1735]">
                            Recent Activity
                        </h2>
                    </div>
                </div>

                <div
                    v-if="recentActivity.length === 0"
                    class="flex min-h-44 flex-col items-center justify-center px-6 py-10 text-center"
                >
                    <span class="flex size-12 items-center justify-center rounded-2xl bg-blue-50 text-[#2583FF]">
                        <Activity class="size-6" :stroke-width="1.7" />
                    </span>
                    <p class="mt-4 text-sm font-medium text-[#0B1735]">
                        No activity has been recorded yet.
                    </p>
                    <p class="mt-1 text-xs text-slate-500">
                        Device state changes and commands will appear here.
                    </p>
                </div>

                <div v-else class="divide-y divide-slate-100">
                    <div
                        v-for="activity in recentActivity"
                        :key="activity.id"
                        class="flex flex-col gap-3 px-6 py-4 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-[#0B1735]">
                                {{ activity.message }}
                            </p>
                            <p
                                v-if="activity.description"
                                class="mt-1 text-xs text-slate-500"
                            >
                                {{ activity.description }}
                            </p>
                            <p
                                v-else-if="activity.device"
                                class="mt-1 text-xs text-slate-500"
                            >
                                {{ activity.device }}
                            </p>
                        </div>

                        <div class="flex shrink-0 items-center gap-3">
                            <StatusBadge
                                :label="activity.status ?? 'Activity'"
                                :status="activityStatus(activity.status)"
                            />
                            <span class="text-xs text-slate-400">
                                {{ activity.created_at }}
                            </span>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </LaraIoTLayout>
</template>
