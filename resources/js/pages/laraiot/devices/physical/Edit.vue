<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Cpu, Save } from 'lucide-vue-next';

import LaraIoTLayout from '../../../../layouts/laraiot/LaraIoTLayout.vue';
import { useLaraIoTUrl } from '../../../../composables/laraiot/useLaraIoTUrl.js';

const props = defineProps({
    physicalDevice: { type: Object, required: true },
});

const { laraiotUrl } = useLaraIoTUrl();

const form = useForm({
    name: props.physicalDevice.name ?? '',
    identifier: props.physicalDevice.identifier ?? '',
    ip_address: props.physicalDevice.ip_address ?? '',
    mac_address: props.physicalDevice.mac_address ?? '',
    description: props.physicalDevice.description ?? '',
    is_enabled: props.physicalDevice.is_enabled ?? true,
});

const submit = () => {
    form.put(laraiotUrl(`devices/physical/${props.physicalDevice.id}`));
};
</script>

<template>
    <Head :title="`Edit ${physicalDevice.name}`" />

    <LaraIoTLayout>
        <div class="mx-auto max-w-4xl space-y-6">
            <div>
                <Link :href="laraiotUrl(`devices/physical/${physicalDevice.id}`)" class="mb-4 inline-flex items-center gap-2 text-sm font-medium text-slate-500 hover:text-slate-900">
                    <ArrowLeft class="size-4" />
                    {{ physicalDevice.name }}
                </Link>

                <div class="flex items-start gap-4">
                    <div class="flex size-11 items-center justify-center rounded-xl bg-slate-900 text-white">
                        <Cpu class="size-5" />
                    </div>
                    <div>
                        <h1 class="text-2xl font-semibold tracking-tight text-slate-950">Edit Physical Device</h1>
                        <p class="mt-1 text-sm text-slate-500">Update the physical device configuration.</p>
                    </div>
                </div>
            </div>

            <form class="space-y-6" @submit.prevent="submit">
                <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div class="space-y-5 p-6">
                        <div>
                            <label class="block text-sm font-medium text-slate-700">Name</label>
                            <input v-model="form.name" type="text" class="mt-2 block w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm" />
                            <p v-if="form.errors.name" class="mt-1.5 text-sm text-red-600">{{ form.errors.name }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700">Identifier</label>
                            <input v-model="form.identifier" type="text" class="mt-2 block w-full rounded-lg border border-slate-300 px-3 py-2.5 font-mono text-sm" />
                            <p v-if="form.errors.identifier" class="mt-1.5 text-sm text-red-600">{{ form.errors.identifier }}</p>
                        </div>
                        <div class="grid gap-5 md:grid-cols-2">
                            <div>
                                <label class="block text-sm font-medium text-slate-700">IP Address</label>
                                <input v-model="form.ip_address" type="text" class="mt-2 block w-full rounded-lg border border-slate-300 px-3 py-2.5 font-mono text-sm" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700">MAC Address</label>
                                <input v-model="form.mac_address" type="text" class="mt-2 block w-full rounded-lg border border-slate-300 px-3 py-2.5 font-mono text-sm" />
                            </div>
                        </div>
                        <textarea v-model="form.description" rows="4" class="block w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm" />
                        <label class="flex items-center gap-3 rounded-lg border border-slate-200 bg-slate-50 p-4">
                            <input v-model="form.is_enabled" type="checkbox" class="size-4 rounded border-slate-300" />
                            <span class="text-sm font-medium text-slate-800">Enabled</span>
                        </label>
                    </div>
                </section>

                <div class="flex justify-end gap-3">
                    <Link :href="laraiotUrl(`devices/physical/${physicalDevice.id}`)" class="rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-medium text-slate-700">Cancel</Link>
                    <button type="submit" :disabled="form.processing || !form.isDirty" class="inline-flex items-center gap-2 rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-medium text-white disabled:opacity-60">
                        <Save class="size-4" />
                        {{ form.processing ? 'Saving...' : 'Save Changes' }}
                    </button>
                </div>
            </form>
        </div>
    </LaraIoTLayout>
</template>
