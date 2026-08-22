<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { Cpu, Eye, Pencil, Plus } from 'lucide-vue-next';

import LaraIoTLayout from '../../../../layouts/laraiot/LaraIoTLayout.vue';
import StatusBadge from '../../../../components/laraiot/StatusBadge.vue';
import { useLaraIoTUrl } from '../../../../composables/laraiot/useLaraIoTUrl.js';

defineProps({
    physicalDevices: {
        type: Array,
        default: () => [],
    },
});

const { laraiotUrl } = useLaraIoTUrl();
</script>

<template>
    <Head title="Physical Devices" />

    <LaraIoTLayout>
        <div class="space-y-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight text-slate-950">
                        Physical Devices
                    </h1>
                    <p class="mt-1 text-sm text-slate-500">
                        Manage physical equipment registered in LaraIoT.
                    </p>
                </div>

                <Link
                    :href="laraiotUrl('devices/physical/create')"
                    class="inline-flex items-center justify-center gap-2 rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-slate-800"
                >
                    <Plus class="size-4" />
                    Add Physical Device
                </Link>
            </div>

            <div
                v-if="physicalDevices.length === 0"
                class="rounded-xl border border-dashed border-slate-300 bg-white px-6 py-14 text-center"
            >
                <Cpu class="mx-auto size-8 text-slate-400" />
                <p class="mt-3 text-sm font-medium text-slate-700">
                    No physical devices
                </p>
            </div>

            <div
                v-else
                class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm"
            >
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Device</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Identifier</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">IP Address</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">MAC Address</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Logical Devices</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Status</th>
                                <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Actions</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-slate-100">
                            <tr
                                v-for="device in physicalDevices"
                                :key="device.id"
                                class="transition hover:bg-slate-50"
                            >
                                <td class="px-5 py-4">
                                    <Link
                                        :href="laraiotUrl(`devices/physical/${device.id}`)"
                                        class="font-medium text-slate-900 hover:text-slate-600"
                                    >
                                        {{ device.name }}
                                    </Link>

                                    <p
                                        v-if="device.description"
                                        class="mt-1 max-w-xs truncate text-xs text-slate-500"
                                    >
                                        {{ device.description }}
                                    </p>
                                </td>

                                <td class="px-5 py-4">
                                    <code class="text-xs text-slate-700">
                                        {{ device.identifier }}
                                    </code>
                                </td>

                                <td class="px-5 py-4 text-sm text-slate-600">
                                    {{ device.ip_address ?? '—' }}
                                </td>

                                <td class="px-5 py-4 text-sm text-slate-600">
                                    {{ device.mac_address ?? '—' }}
                                </td>

                                <td class="px-5 py-4">
                                    <StatusBadge
                                        :label="`${device.logical_devices_count ?? 0} configured`"
                                        :status="(device.logical_devices_count ?? 0) ? 'info' : 'neutral'"
                                    />
                                </td>

                                <td class="px-5 py-4">
                                    <StatusBadge
                                        :label="device.is_enabled ? 'Enabled' : 'Disabled'"
                                        :status="device.is_enabled ? 'success' : 'neutral'"
                                    />
                                </td>

                                <td class="px-5 py-4 text-right">
                                    <div class="flex justify-end gap-2">
                                        <Link
                                            :href="laraiotUrl(`devices/physical/${device.id}`)"
                                            class="flex size-9 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100"
                                        >
                                            <Eye class="size-4" />
                                        </Link>

                                        <Link
                                            :href="laraiotUrl(`devices/physical/${device.id}/edit`)"
                                            class="flex size-9 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100"
                                        >
                                            <Pencil class="size-4" />
                                        </Link>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </LaraIoTLayout>
</template>
