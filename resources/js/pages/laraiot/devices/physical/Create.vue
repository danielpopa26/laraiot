<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Cpu, Save } from 'lucide-vue-next';

import LaraIoTLayout from '../../../../layouts/laraiot/LaraIoTLayout.vue';
import { useLaraIoTUrl } from '../../../../composables/laraiot/useLaraIoTUrl.js';

const { laraiotUrl } = useLaraIoTUrl();

const form = useForm({
    name: '',
    identifier: '',
    ip_address: '',
    mac_address: '',
    description: '',
    is_enabled: true,
});

const submit = () => {
    form.post(laraiotUrl('devices/physical'));
};
</script>

<template>
    <Head title="Add Physical Device" />

    <LaraIoTLayout>
        <div class="mx-auto max-w-4xl space-y-6">
            <div>
                <Link
                    :href="laraiotUrl('devices/physical')"
                    class="mb-4 inline-flex items-center gap-2 text-sm font-medium text-slate-500 hover:text-[#0B1735]"
                >
                    <ArrowLeft class="size-4" />
                    Physical Devices
                </Link>

                <div class="flex items-start gap-4">
                    <div class="flex size-11 items-center justify-center rounded-xl bg-[#2583FF] text-white">
                        <Cpu class="size-5" />
                    </div>
                    <div>
                        <h1 class="text-2xl font-semibold tracking-tight text-[#0B1735]">
                            Add Physical Device
                        </h1>
                        <p class="mt-1 text-sm text-slate-500">
                            Register a physical device in LaraIoT.
                        </p>
                    </div>
                </div>
            </div>

            <form class="space-y-6" @submit.prevent="submit">
                <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-200 px-6 py-4">
                        <h2 class="text-base font-semibold text-[#0B1735]">Device information</h2>
                    </div>
                    <div class="space-y-5 p-6">
                        <div>
                            <label class="block text-sm font-medium text-slate-700">Name <span class="text-red-500">*</span></label>
                            <input v-model="form.name" type="text" class="mt-2 block w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm" />
                            <p v-if="form.errors.name" class="mt-1.5 text-sm text-red-600">{{ form.errors.name }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700">Identifier <span class="text-red-500">*</span></label>
                            <input v-model="form.identifier" type="text" spellcheck="false" class="mt-2 block w-full rounded-lg border border-slate-300 px-3 py-2.5 font-mono text-sm" />
                            <p v-if="form.errors.identifier" class="mt-1.5 text-sm text-red-600">{{ form.errors.identifier }}</p>
                        </div>
                    </div>
                </section>

                <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-200 px-6 py-4">
                        <h2 class="text-base font-semibold text-[#0B1735]">Network information</h2>
                    </div>
                    <div class="grid gap-5 p-6 md:grid-cols-2">
                        <div>
                            <label class="block text-sm font-medium text-slate-700">IP Address</label>
                            <input v-model="form.ip_address" type="text" class="mt-2 block w-full rounded-lg border border-slate-300 px-3 py-2.5 font-mono text-sm" />
                            <p v-if="form.errors.ip_address" class="mt-1.5 text-sm text-red-600">{{ form.errors.ip_address }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700">MAC Address</label>
                            <input v-model="form.mac_address" type="text" class="mt-2 block w-full rounded-lg border border-slate-300 px-3 py-2.5 font-mono text-sm uppercase" />
                            <p v-if="form.errors.mac_address" class="mt-1.5 text-sm text-red-600">{{ form.errors.mac_address }}</p>
                        </div>
                    </div>
                </section>

                <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-200 px-6 py-4">
                        <h2 class="text-base font-semibold text-[#0B1735]">Description & status</h2>
                    </div>
                    <div class="space-y-5 p-6">
                        <textarea v-model="form.description" rows="4" class="block w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm" />
                        <label class="flex items-start gap-3 rounded-lg border border-slate-200 bg-slate-50 p-4">
                            <input v-model="form.is_enabled" type="checkbox" class="mt-0.5 size-4 rounded border-slate-300" />
                            <div>
                                <span class="text-sm font-medium text-slate-800">Enabled</span>
                                <p class="mt-1 text-xs text-slate-500">The device is available to LaraIoT.</p>
                            </div>
                        </label>
                    </div>
                </section>

                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <Link :href="laraiotUrl('devices/physical')" class="inline-flex justify-center rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-medium text-slate-700">
                        Cancel
                    </Link>
                    <button type="submit" :disabled="form.processing" class="inline-flex items-center justify-center gap-2 rounded-lg bg-[#2583FF] px-4 py-2.5 text-sm font-medium text-white disabled:opacity-60">
                        <Save class="size-4" />
                        {{ form.processing ? 'Saving...' : 'Save Device' }}
                    </button>
                </div>
            </form>
        </div>
    </LaraIoTLayout>
</template>
