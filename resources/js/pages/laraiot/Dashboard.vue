<script setup>
import { Head } from '@inertiajs/vue3';
import {
    Activity,
    Boxes,
    Cpu,
    Radio,
} from 'lucide-vue-next';

import LaraIoTLayout from '../../layouts/laraiot/LaraIoTLayout.vue';
import StatCard from '../../components/laraiot/StatCard.vue';
import StatusBadge from '../../components/laraiot/StatusBadge.vue';
import { useLaraIoTUrl } from '../../composables/laraiot/useLaraIoTUrl.js';

defineProps({
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
});

const { laraiotUrl } = useLaraIoTUrl();

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
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight text-slate-950">
                        Dashboard
                    </h1>
                    <p class="mt-1 text-sm text-slate-500">
                        Overview of the LaraIoT environment.
                    </p>
                </div>

                <StatusBadge
                    :label="mode === 'websocket' ? 'WebSocket' : 'Polling'"
                    status="info"
                    size="md"
                />
            </div>

            <div class="grid gap-4 md:grid-cols-3">
                <StatCard
                    title="Physical Devices"
                    :value="statistics.physicalDevices"
                    description="Registered physical equipment"
                    :icon="Cpu"
                    :href="laraiotUrl('devices/physical')"
                />

                <StatCard
                    title="Logical Devices"
                    :value="statistics.logicalDevices"
                    description="Configured logical devices"
                    :icon="Boxes"
                    :href="laraiotUrl('devices/logical')"
                />

                <StatCard
                    title="MQTT Topics"
                    :value="statistics.mqttTopics"
                    description="Configured MQTT topics"
                    :icon="Radio"
                />
            </div>

            <div class="grid gap-4 lg:grid-cols-3">
                <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-sm font-medium text-slate-500">MQTT</p>
                    <div class="mt-3">
                        <StatusBadge
                            :label="
                                mqtt.connected === true
                                    ? 'Connected'
                                    : mqtt.connected === false
                                        ? 'Disconnected'
                                        : 'Unknown'
                            "
                            :status="
                                mqtt.connected === true
                                    ? 'success'
                                    : mqtt.connected === false
                                        ? 'danger'
                                        : 'neutral'
                            "
                            size="md"
                        />
                    </div>
                </section>

                <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-sm font-medium text-slate-500">
                        Communication Mode
                    </p>
                    <p class="mt-3 text-lg font-semibold text-slate-900">
                        {{ mode === 'websocket' ? 'WebSocket' : 'Polling' }}
                    </p>
                </section>

                <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <p class="text-sm font-medium text-slate-500">
                        Logical Devices
                    </p>
                    <p class="mt-3 text-lg font-semibold text-slate-900">
                        {{ statistics.logicalDevices }}
                    </p>
                </section>
            </div>

            <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-6 py-4">
                    <div class="flex items-center gap-2">
                        <Activity class="size-4 text-slate-500" />
                        <h2 class="text-base font-semibold text-slate-950">
                            Recent Activity
                        </h2>
                    </div>
                </div>

                <div
                    v-if="recentActivity.length === 0"
                    class="px-6 py-12 text-center text-sm text-slate-500"
                >
                    No activity has been recorded yet.
                </div>

                <div v-else class="divide-y divide-slate-100">
                    <div
                        v-for="activity in recentActivity"
                        :key="activity.id"
                        class="flex flex-col gap-3 px-6 py-4 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-slate-900">
                                {{ activity.message }}
                            </p>
                            <p
                                v-if="activity.device"
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
