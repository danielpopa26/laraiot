<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Boxes, Save } from 'lucide-vue-next';

import LaraIoTLayout from '../../../../layouts/laraiot/LaraIoTLayout.vue';
import { useLaraIoTUrl } from '../../../../composables/laraiot/useLaraIoTUrl.js';

defineProps({
    physicalDevices: { type: Array, default: () => [] },
    deviceTypes: { type: Array, default: () => [] },
});

const { laraiotUrl } = useLaraIoTUrl();

const form = useForm({
    physical_device_id: '',
    device_type_id: '',
    identifier: '',
    name: '',
    unit: '',
    is_enabled: true,
});

const submit = () => {
    form.post(laraiotUrl('devices/logical'));
};
</script>

<template>
    <Head title="Add Logical Device" />
    <LaraIoTLayout>
        <div class="mx-auto max-w-4xl space-y-6">
            <Link :href="laraiotUrl('devices/logical')" class="inline-flex items-center gap-2 text-sm font-medium text-slate-500 hover:text-[#0B1735]">
                <ArrowLeft class="size-4" /> Logical Devices
            </Link>

            <div class="flex items-start gap-4">
                <div class="flex size-11 items-center justify-center rounded-xl bg-[#2583FF] text-white"><Boxes class="size-5" /></div>
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight text-[#0B1735]">Add Logical Device</h1>
                    <p class="mt-1 text-sm text-slate-500">Create a logical device and associate it with physical hardware.</p>
                </div>
            </div>

            <form class="space-y-6" @submit.prevent="submit">
                <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="space-y-5">
                        <div>
                            <label class="block text-sm font-medium text-slate-700">Physical Device</label>
                            <select v-model="form.physical_device_id" class="mt-2 block w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">
                                <option value="">Select a physical device</option>
                                <option v-for="device in physicalDevices" :key="device.id" :value="device.id">{{ device.name }} — {{ device.identifier }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700">Device Type</label>
                            <select v-model="form.device_type_id" class="mt-2 block w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">
                                <option value="">Select a device type</option>
                                <option v-for="type in deviceTypes" :key="type.id" :value="type.id">{{ type.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700">Name</label>
                            <input v-model="form.name" class="mt-2 block w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700">Identifier</label>
                            <input v-model="form.identifier" class="mt-2 block w-full rounded-lg border border-slate-300 px-3 py-2.5 font-mono text-sm" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700">Unit</label>
                            <input v-model="form.unit" class="mt-2 block w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm" />
                        </div>
                        <label class="flex items-center gap-3 rounded-lg border border-slate-200 bg-slate-50 p-4">
                            <input v-model="form.is_enabled" type="checkbox" class="size-4 rounded border-slate-300" />
                            <span class="text-sm font-medium text-slate-800">Enabled</span>
                        </label>
                    </div>
                </section>

                <div class="flex justify-end gap-3">
                    <Link :href="laraiotUrl('devices/logical')" class="rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-medium text-slate-700">Cancel</Link>
                    <button type="submit" :disabled="form.processing" class="inline-flex items-center gap-2 rounded-lg bg-[#2583FF] px-4 py-2.5 text-sm font-medium text-white disabled:opacity-60">
                        <Save class="size-4" /> {{ form.processing ? 'Saving...' : 'Save Logical Device' }}
                    </button>
                </div>
            </form>
        </div>
    </LaraIoTLayout>
</template>
