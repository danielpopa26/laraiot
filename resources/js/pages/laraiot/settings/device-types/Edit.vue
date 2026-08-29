<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Save, Tags } from 'lucide-vue-next';
import LaraIoTLayout from '../../../../layouts/laraiot/LaraIoTLayout.vue';
import { useLaraIoTUrl } from '../../../../composables/laraiot/useLaraIoTUrl.js';

const props = defineProps({ deviceType: { type: Object, required: true } });
const { laraiotUrl } = useLaraIoTUrl();
const form = useForm({
    identifier: props.deviceType.identifier ?? '',
    name: props.deviceType.name ?? '',
    description: props.deviceType.description ?? '',
    is_enabled: props.deviceType.is_enabled ?? true,
});
const submit = () => form.put(laraiotUrl(`settings/device-types/${props.deviceType.id}`));
</script>
<template>
<Head :title="`Edit ${deviceType.name}`" />
<LaraIoTLayout><div class="mx-auto max-w-4xl space-y-6">
<Link :href="laraiotUrl('settings/device-types')" class="inline-flex items-center gap-2 text-sm text-slate-500"><ArrowLeft class="size-4" />Device Types</Link>
<div class="flex items-start gap-4"><div class="flex size-11 items-center justify-center rounded-xl bg-[#2583FF] text-white"><Tags class="size-5" /></div><div><h1 class="text-2xl font-semibold">Edit Device Type</h1><p class="mt-1 text-sm text-slate-500">Update the device type configuration.</p></div></div>
<form class="space-y-6" @submit.prevent="submit">
<section class="space-y-5 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
<div><label class="text-sm font-medium">Name</label><input v-model="form.name" class="mt-2 block w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm" /><p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p></div>
<div><label class="text-sm font-medium">Identifier</label><input v-model="form.identifier" spellcheck="false" class="mt-2 block w-full rounded-lg border border-slate-300 px-3 py-2.5 font-mono text-sm" /><p class="mt-1.5 text-xs text-slate-500">Stable technical identifier. Use lowercase letters, numbers, hyphens or underscores.</p><p v-if="form.errors.identifier" class="mt-1 text-sm text-red-600">{{ form.errors.identifier }}</p></div>
<textarea v-model="form.description" rows="4" class="block w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm" />
<label class="flex items-center gap-3"><input v-model="form.is_enabled" type="checkbox" class="size-4" />Enabled</label>
</section>
<div class="flex justify-end gap-3"><Link :href="laraiotUrl('settings/device-types')" class="rounded-lg border px-4 py-2.5 text-sm">Cancel</Link><button :disabled="form.processing || !form.isDirty" class="inline-flex items-center gap-2 rounded-lg bg-[#2583FF] px-4 py-2.5 text-sm text-white"><Save class="size-4" />Save Changes</button></div>
</form>
</div></LaraIoTLayout>
</template>
