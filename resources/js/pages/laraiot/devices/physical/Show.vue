<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Boxes, Cpu, EthernetPort, FileText, Fingerprint, Network, Pencil, Trash2 } from 'lucide-vue-next';

import LaraIoTLayout from '../../../../layouts/laraiot/LaraIoTLayout.vue';
import StatusBadge from '../../../../components/laraiot/StatusBadge.vue';
import { useLaraIoTUrl } from '../../../../composables/laraiot/useLaraIoTUrl.js';

const props = defineProps({
    physicalDevice: { type: Object, required: true },
    logicalDevices: { type: Array, default: () => [] },
});

const { laraiotUrl } = useLaraIoTUrl();
const deleteForm = useForm({});

const deleteDevice = () => {
    if (!window.confirm('Are you sure you want to delete this physical device?')) {
        return;
    }

    deleteForm.delete(
        laraiotUrl(`devices/physical/${props.physicalDevice.id}`),
    );
};
</script>

<template>
    <Head :title="physicalDevice.name" />

    <LaraIoTLayout>
        <div class="space-y-6">
            <Link
                :href="laraiotUrl('devices/physical')"
                class="inline-flex items-center gap-2 text-sm font-medium text-slate-500 hover:text-[#0B1735]"
            >
                <ArrowLeft class="size-4" />
                Physical Devices
            </Link>

            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div class="flex items-start gap-4">
                    <div class="flex size-12 items-center justify-center rounded-xl bg-[#2583FF] text-white">
                        <Cpu class="size-6" />
                    </div>

                    <div>
                        <h1 class="text-2xl font-semibold tracking-tight text-[#0B1735]">
                            {{ physicalDevice.name }}
                        </h1>

                        <div class="mt-2 flex items-center gap-2 text-sm text-slate-500">
                            <Fingerprint class="size-4" />
                            <code>{{ physicalDevice.identifier }}</code>
                        </div>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <StatusBadge
                        :label="physicalDevice.is_enabled ? 'Enabled' : 'Disabled'"
                        :status="physicalDevice.is_enabled ? 'success' : 'neutral'"
                        size="md"
                    />

                    <Link
                        :href="laraiotUrl(`devices/physical/${physicalDevice.id}/edit`)"
                        class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50"
                    >
                        <Pencil class="size-4" />
                        Edit Device
                    </Link>
                </div>
            </div>

            <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="grid divide-y divide-slate-100 sm:grid-cols-2 sm:divide-x sm:divide-y-0 lg:grid-cols-4">
                    <div class="p-5">
                        <div class="flex items-center gap-2 text-xs font-medium uppercase tracking-wider text-slate-400">
                            <Fingerprint class="size-4" />
                            Identifier
                        </div>
                        <code class="mt-3 block break-all text-sm text-slate-800">
                            {{ physicalDevice.identifier }}
                        </code>
                    </div>

                    <div class="p-5">
                        <div class="flex items-center gap-2 text-xs font-medium uppercase tracking-wider text-slate-400">
                            <Network class="size-4" />
                            IP Address
                        </div>
                        <p class="mt-3 font-mono text-sm text-slate-800">
                            {{ physicalDevice.ip_address || '—' }}
                        </p>
                    </div>

                    <div class="p-5">
                        <div class="flex items-center gap-2 text-xs font-medium uppercase tracking-wider text-slate-400">
                            <EthernetPort class="size-4" />
                            MAC Address
                        </div>
                        <p class="mt-3 font-mono text-sm text-slate-800">
                            {{ physicalDevice.mac_address || '—' }}
                        </p>
                    </div>

                    <div class="p-5">
                        <div class="flex items-center gap-2 text-xs font-medium uppercase tracking-wider text-slate-400">
                            <Boxes class="size-4" />
                            Logical Devices
                        </div>
                        <p class="mt-3 text-2xl font-semibold text-[#0B1735]">
                            {{ logicalDevices.length }}
                        </p>
                    </div>
                </div>
            </section>

            <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-6 py-4">
                    <div class="flex items-center gap-2">
                        <FileText class="size-4 text-slate-500" />
                        <h2 class="text-base font-semibold text-[#0B1735]">
                            Description
                        </h2>
                    </div>
                </div>

                <div class="p-6">
                    <p
                        v-if="physicalDevice.description"
                        class="whitespace-pre-line text-sm leading-6 text-slate-700"
                    >
                        {{ physicalDevice.description }}
                    </p>
                    <p v-else class="text-sm text-slate-400">
                        No description.
                    </p>
                </div>
            </section>

            <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-6 py-4">
                    <h2 class="text-base font-semibold text-[#0B1735]">
                        Logical Devices
                    </h2>
                </div>

                <div
                    v-if="logicalDevices.length === 0"
                    class="px-6 py-10 text-center text-sm text-slate-500"
                >
                    No logical devices are associated with this physical device.
                </div>

                <div v-else class="divide-y divide-slate-100">
                    <Link
                        v-for="logicalDevice in logicalDevices"
                        :key="logicalDevice.id"
                        :href="laraiotUrl(`devices/logical/${logicalDevice.id}`)"
                        class="flex items-center justify-between gap-4 px-6 py-4 hover:bg-slate-50"
                    >
                        <div>
                            <p class="text-sm font-medium text-[#0B1735]">
                                {{ logicalDevice.name }}
                            </p>
                            <code class="mt-1 block text-xs text-slate-500">
                                {{ logicalDevice.identifier }}
                            </code>
                        </div>

                        <StatusBadge
                            :label="logicalDevice.is_enabled ? 'Enabled' : 'Disabled'"
                            :status="logicalDevice.is_enabled ? 'success' : 'neutral'"
                        />
                    </Link>
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
                            Delete Physical Device
                        </p>
                        <p class="mt-1 text-sm text-slate-500">
                            This action cannot be undone.
                        </p>
                    </div>

                    <button
                        type="button"
                        :disabled="deleteForm.processing"
                        class="inline-flex items-center gap-2 rounded-lg border border-red-300 px-4 py-2.5 text-sm font-medium text-red-700 hover:bg-red-50 disabled:opacity-60"
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
