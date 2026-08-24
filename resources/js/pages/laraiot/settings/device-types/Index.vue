<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { Pencil, Plus, Tags, Trash2 } from 'lucide-vue-next';
import LaraIoTLayout from '../../../../layouts/laraiot/LaraIoTLayout.vue';
import StatusBadge from '../../../../components/laraiot/StatusBadge.vue';
import { useLaraIoTUrl } from '../../../../composables/laraiot/useLaraIoTUrl.js';

defineProps({ deviceTypes: { type: Array, default: () => [] } });
const { laraiotUrl } = useLaraIoTUrl();

const toggleEnabled = (deviceType) => {
    router.put(laraiotUrl(`settings/device-types/${deviceType.id}`), {
        identifier: deviceType.identifier,
        name: deviceType.name,
        description: deviceType.description ?? '',
        is_enabled: !deviceType.is_enabled,
    }, { preserveScroll: true });
};

const deleteDeviceType = (deviceType) => {
    if ((deviceType.logical_devices_count ?? 0) > 0) return;
    if (!window.confirm(`Delete device type "${deviceType.name}"?`)) return;

    router.delete(
        laraiotUrl(`settings/device-types/${deviceType.id}`),
        { preserveScroll: true },
    );
};
</script>

<template>
<Head title="Device Types" />
<LaraIoTLayout>
<div class="space-y-6">
    <div class="flex justify-between gap-4">
        <div><h1 class="text-2xl font-semibold">Device Types</h1><p class="mt-1 text-sm text-slate-500">Manage logical device types.</p></div>
        <Link :href="laraiotUrl('settings/device-types/create')" class="inline-flex items-center gap-2 rounded-lg bg-[#2583FF] px-4 py-2.5 text-sm text-white"><Plus class="size-4" />Add Device Type</Link>
    </div>
    <div v-if="deviceTypes.length === 0" class="rounded-xl border border-dashed border-slate-300 bg-white px-6 py-14 text-center"><Tags class="mx-auto size-8 text-slate-400" /><p class="mt-3 text-sm text-slate-500">No device types.</p></div>
    <div v-else class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50"><tr><th class="px-5 py-3 text-left text-xs uppercase">Name</th><th class="px-5 py-3 text-left text-xs uppercase">Identifier</th><th class="px-5 py-3 text-left text-xs uppercase">Logical Devices</th><th class="px-5 py-3 text-left text-xs uppercase">Status</th><th class="px-5 py-3 text-right text-xs uppercase">Actions</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                <tr v-for="t in deviceTypes" :key="t.id">
                    <td class="px-5 py-4"><div class="font-medium">{{ t.name }}</div><div v-if="t.description" class="mt-1 text-xs text-slate-500">{{ t.description }}</div></td>
                    <td class="px-5 py-4"><code class="text-xs">{{ t.identifier }}</code></td>
                    <td class="px-5 py-4"><StatusBadge :label="`${t.logical_devices_count ?? 0} configured`" :status="(t.logical_devices_count ?? 0) ? 'info' : 'neutral'" /></td>
                    <td class="px-5 py-4"><StatusBadge :label="t.is_enabled ? 'Enabled' : 'Disabled'" :status="t.is_enabled ? 'success' : 'neutral'" /></td>
                    <td class="px-5 py-4 text-right"><div class="flex justify-end gap-2"><button class="rounded-lg border px-3 py-2 text-xs" @click="toggleEnabled(t)">{{ t.is_enabled ? 'Disable' : 'Enable' }}</button><Link :href="laraiotUrl(`settings/device-types/${t.id}/edit`)" class="flex size-9 items-center justify-center rounded-lg hover:bg-slate-100" aria-label="Edit device type"><Pencil class="size-4" /></Link><button type="button" :disabled="(t.logical_devices_count ?? 0) > 0" :title="(t.logical_devices_count ?? 0) > 0 ? 'Delete all associated logical devices before deleting this device type.' : 'Delete device type'" class="flex size-9 items-center justify-center rounded-lg text-red-600 hover:bg-red-50 disabled:cursor-not-allowed disabled:text-slate-300 disabled:hover:bg-transparent" aria-label="Delete device type" @click="deleteDeviceType(t)"><Trash2 class="size-4" /></button></div></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
</LaraIoTLayout>
</template>
