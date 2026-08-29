<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Boxes, Cpu, Fingerprint, Gauge, Pencil, Plus, Power, Radio, Tag, Trash2 } from 'lucide-vue-next';

import LaraIoTLayout from '../../../../layouts/laraiot/LaraIoTLayout.vue';
import LogicalDeviceControl from '../../../../components/laraiot/LogicalDeviceControl.vue';
import StatusBadge from '../../../../components/laraiot/StatusBadge.vue';
import { useLaraIoTUrl } from '../../../../composables/laraiot/useLaraIoTUrl.js';
import { useLaraIoTPolling } from '../../../../composables/laraiot/useLaraIoTPolling.js';
import { onLaraIoTStateUpdate } from '../../../../composables/laraiot/useLaraIoTWebSocketHealth.js';

const props = defineProps({
    logicalDevice: { type: Object, required: true },
    mqttTopics: { type: Array, default: () => [] },
    deviceOverview: { type: Object, required: true },
});

const { laraiotUrl } = useLaraIoTUrl();

const liveLastValue = ref(props.logicalDevice.last_value);

watch(
    () => props.logicalDevice.last_value,
    (value) => {
        liveLastValue.value = value;
    },
);

const unsubscribeStateUpdates = onLaraIoTStateUpdate((event) => {
    if (
        Number(event.logical_device_id)
        !== Number(props.logicalDevice.id)
    ) {
        return;
    }

    liveLastValue.value = event.value;
});

useLaraIoTPolling([
    'logicalDevice',
    'mqttTopics',
    'deviceOverview',
    'laraiot',
]);

const topics = computed(() =>
    props.mqttTopics.length > 0
        ? props.mqttTopics
        : (props.logicalDevice.mqtt_topics ?? []),
);

const formattedLastValue = computed(() => {
    const value = liveLastValue.value;

    if (value === null || value === undefined || value === '') {
        return '—';
    }

    const formatted = typeof value === 'boolean'
        ? (value ? 'TRUE' : 'FALSE')
        : typeof value === 'object'
            ? JSON.stringify(value)
            : String(value);

    return formatted;
});

const deleteForm = useForm({});
const canDelete = computed(() => topics.value.length === 0);

const deleteDevice = () => {
    if (!canDelete.value) return;

    if (window.confirm('Are you sure you want to delete this logical device?')) {
        deleteForm.delete(
            laraiotUrl(`devices/logical/${props.logicalDevice.id}`),
        );
    }
};

const deleteTopic = (topic) => {
    if (window.confirm(`Delete MQTT topic "${topic.topic}"?`)) {
        router.delete(
            laraiotUrl(
                `devices/logical/${props.logicalDevice.id}/mqtt-topics/${topic.id}`,
            ),
            { preserveScroll: true },
        );
    }
};

const payloadSummary = (topic) => {
    const mapping = topic.payload_mapping ?? {};

    if (topic.purpose === 'command') {
        return `on=${JSON.stringify(mapping.on ?? '')}, off=${JSON.stringify(mapping.off ?? '')}`;
    }

    if ((mapping.format ?? 'raw') === 'json' && mapping.value_path) {
        return `json: ${mapping.value_path}`;
    }

    return mapping.format ?? 'raw';
};

onBeforeUnmount(unsubscribeStateUpdates);
</script>

<template>
    <Head :title="logicalDevice.name" />

    <LaraIoTLayout>
        <div class="space-y-6">
            <Link
                :href="laraiotUrl('devices/logical')"
                class="inline-flex items-center gap-2 text-sm font-medium text-slate-500 hover:text-[#0B1735]"
            >
                <ArrowLeft class="size-4" />
                Logical Devices
            </Link>

            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div class="flex items-start gap-4">
                    <div class="flex size-12 items-center justify-center rounded-xl bg-[#2583FF] text-white">
                        <Boxes class="size-6" />
                    </div>

                    <div>
                        <h1 class="text-2xl font-semibold tracking-tight text-[#0B1735]">
                            {{ logicalDevice.name }}
                        </h1>

                        <div class="mt-2 flex items-center gap-2 text-sm text-slate-500">
                            <Fingerprint class="size-4" />
                            <code>{{ logicalDevice.identifier }}</code>
                        </div>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <StatusBadge
                        :label="logicalDevice.is_enabled ? 'Enabled' : 'Disabled'"
                        :status="logicalDevice.is_enabled ? 'success' : 'neutral'"
                        size="md"
                    />

                    <Link
                        :href="laraiotUrl(`devices/logical/${logicalDevice.id}/edit`)"
                        class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50"
                    >
                        <Pencil class="size-4" />
                        Edit Device
                    </Link>
                </div>
            </div>

            <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="grid divide-y divide-slate-100 sm:grid-cols-2 sm:divide-x sm:divide-y-0 xl:grid-cols-6">
                    <div class="p-5">
                        <div class="flex items-center gap-2 text-xs font-medium uppercase tracking-wider text-slate-400">
                            <Fingerprint class="size-4" />
                            Identifier
                        </div>
                        <code class="mt-3 block break-all text-sm text-slate-800">
                            {{ logicalDevice.identifier }}
                        </code>
                    </div>

                    <div class="p-5">
                        <div class="flex items-center gap-2 text-xs font-medium uppercase tracking-wider text-slate-400">
                            <Cpu class="size-4" />
                            Physical Device
                        </div>

                        <Link
                            v-if="logicalDevice.physical_device"
                            :href="laraiotUrl(`devices/physical/${logicalDevice.physical_device.id}`)"
                            class="mt-3 block text-sm font-medium text-slate-800 hover:text-[#0B1735]"
                        >
                            {{ logicalDevice.physical_device.name }}
                        </Link>
                        <span v-else class="mt-3 block text-sm text-slate-400">
                            —
                        </span>
                    </div>

                    <div class="p-5">
                        <div class="flex items-center gap-2 text-xs font-medium uppercase tracking-wider text-slate-400">
                            <Tag class="size-4" />
                            Device Type
                        </div>
                        <p class="mt-3 text-sm font-medium text-slate-800">
                            {{ logicalDevice.device_type?.name ?? '—' }}
                        </p>
                    </div>

                    <div class="p-5">
                        <div class="flex items-center gap-2 text-xs font-medium uppercase tracking-wider text-slate-400">
                            <Gauge class="size-4" />
                            Current Value
                        </div>
                        <p class="mt-3 text-2xl font-semibold text-[#0B1735]">
                            {{ formattedLastValue }}
                        </p>
                    </div>

                    <div class="p-5">
                        <div class="text-xs font-medium uppercase tracking-wider text-slate-400">
                            Unit
                        </div>
                        <p class="mt-3 text-sm font-medium text-slate-800">
                            {{ logicalDevice.unit ?? '—' }}
                        </p>
                    </div>

                    <div class="p-5">
                        <div class="flex items-center gap-2 text-xs font-medium uppercase tracking-wider text-slate-400">
                            <Radio class="size-4" />
                            MQTT Topics
                        </div>
                        <p class="mt-3 text-2xl font-semibold text-[#0B1735]">
                            {{ topics.length }}
                        </p>
                    </div>
                </div>
            </section>

            <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center gap-3 border-b border-slate-200 px-6 py-4">
                    <span class="flex size-9 items-center justify-center rounded-lg bg-blue-50 text-[#2583FF]">
                        <Power class="size-4" />
                    </span>
                    <div>
                        <h2 class="text-base font-semibold text-[#0B1735]">
                            Device Operation
                        </h2>
                        <p class="mt-0.5 text-sm text-slate-500">
                            Current MQTT value, configuration readiness and command control.
                        </p>
                    </div>
                </div>

                <div class="p-4 sm:p-6">
                    <LogicalDeviceControl
                        :device="deviceOverview"
                        :reload-props="['logicalDevice', 'mqttTopics', 'deviceOverview']"
                        :show-configuration-link="false"
                    />
                </div>
            </section>

            <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="flex flex-col gap-3 border-b border-slate-200 px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-base font-semibold text-[#0B1735]">
                            MQTT Topics
                        </h2>
                        <p class="mt-1 text-sm text-slate-500">
                            State and command topics for this logical device.
                        </p>
                    </div>

                    <Link
                        :href="laraiotUrl(`devices/logical/${logicalDevice.id}/mqtt-topics/create`)"
                        class="inline-flex items-center gap-2 rounded-lg bg-[#2583FF] px-3.5 py-2 text-sm font-medium text-white"
                    >
                        <Plus class="size-4" />
                        Add MQTT Topic
                    </Link>
                </div>

                <div
                    v-if="topics.length === 0"
                    class="px-6 py-12 text-center text-sm text-slate-500"
                >
                    No MQTT topics.
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Purpose</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Topic</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Payload</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">QoS</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Enabled</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Validation</th>
                                <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Actions</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-slate-100">
                            <tr
                                v-for="topic in topics"
                                :key="topic.id"
                            >
                                <td class="px-5 py-4">
                                    <StatusBadge
                                        :label="topic.purpose"
                                        :status="topic.purpose === 'state' ? 'info' : 'neutral'"
                                    />
                                </td>

                                <td class="px-5 py-4">
                                    <code class="break-all text-xs text-slate-700">
                                        {{ topic.topic }}
                                    </code>
                                </td>

                                <td class="px-5 py-4 text-xs text-slate-600">
                                    {{ payloadSummary(topic) }}
                                </td>

                                <td class="px-5 py-4 text-sm text-slate-600">
                                    {{ topic.qos }}
                                </td>

                                <td class="px-5 py-4">
                                    <StatusBadge
                                        :label="topic.is_enabled ? 'Enabled' : 'Disabled'"
                                        :status="topic.is_enabled ? 'success' : 'neutral'"
                                    />
                                </td>

                                <td class="px-5 py-4">
                                    <StatusBadge
                                        :label="topic.validated_at ? 'Validated' : 'Not validated'"
                                        :status="topic.validated_at ? 'success' : 'warning'"
                                    />
                                </td>

                                <td class="px-5 py-4 text-right">
                                    <div class="flex justify-end gap-2">
                                        <Link
                                            :href="laraiotUrl(`devices/logical/${logicalDevice.id}/mqtt-topics/${topic.id}/edit`)"
                                            class="flex size-9 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100"
                                        >
                                            <Pencil class="size-4" />
                                        </Link>

                                        <button
                                            type="button"
                                            class="flex size-9 items-center justify-center rounded-lg text-red-600 hover:bg-red-50"
                                            @click="deleteTopic(topic)"
                                        >
                                            <Trash2 class="size-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="rounded-xl border border-red-200 bg-white shadow-sm">
                <div class="border-b border-red-100 px-6 py-4">
                    <h2 class="text-base font-semibold text-red-700">
                        Danger Zone
                    </h2>
                </div>

                <div class="flex items-center justify-between gap-4 p-6">
                    <div>
                        <p class="text-sm font-medium text-[#0B1735]">
                            Delete Logical Device
                        </p>
                        <p class="mt-1 text-sm text-slate-500">
                            <template v-if="canDelete">
                                This action cannot be undone.
                            </template>
                            <template v-else>
                                To delete this logical device, first delete all associated MQTT topics.
                            </template>
                        </p>
                        <p v-if="deleteForm.errors.delete" class="mt-2 text-sm text-red-600">{{ deleteForm.errors.delete }}</p>
                    </div>

                    <button
                        type="button"
                        :disabled="!canDelete || deleteForm.processing"
                        class="inline-flex items-center gap-2 rounded-lg border border-red-300 px-4 py-2.5 text-sm font-medium text-red-700 hover:bg-red-50 disabled:cursor-not-allowed disabled:border-slate-200 disabled:text-slate-400 disabled:hover:bg-transparent"
                        @click="deleteDevice"
                    >
                        <Trash2 class="size-4" />
                        Delete Device
                    </button>
                </div>
            </section>
        </div>
    </LaraIoTLayout>
</template>
