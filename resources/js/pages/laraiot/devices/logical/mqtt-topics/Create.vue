<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Radio, Save } from 'lucide-vue-next';
import LaraIoTLayout from '../../../../../layouts/laraiot/LaraIoTLayout.vue';
import MqttTopicForm from '../../../../../components/laraiot/MqttTopicForm.vue';
import { useLaraIoTUrl } from '../../../../../composables/laraiot/useLaraIoTUrl.js';

const props = defineProps({ logicalDevice: { type: Object, required: true } });
const { laraiotUrl } = useLaraIoTUrl();
const form = useForm({
    purpose: 'state', topic: '', payload_format: 'raw', value_path: '',
    value_map: [], command_on: '', command_off: '', qos: 0, retain: false, is_enabled: true,
});
const submit = () => form.post(laraiotUrl(`devices/logical/${props.logicalDevice.id}/mqtt-topics`));
</script>
<template>
<Head title="Add MQTT Topic" />
<LaraIoTLayout><div class="mx-auto max-w-4xl space-y-6">
<Link :href="laraiotUrl(`devices/logical/${logicalDevice.id}`)" class="inline-flex items-center gap-2 text-sm text-slate-500"><ArrowLeft class="size-4" />{{ logicalDevice.name }}</Link>
<div class="flex items-start gap-4"><div class="flex size-11 items-center justify-center rounded-xl bg-[#2583FF] text-white"><Radio class="size-5" /></div><div><h1 class="text-2xl font-semibold">Add MQTT Topic</h1><p class="mt-1 text-sm text-slate-500">Configure a state or command topic.</p></div></div>
<form class="space-y-6" @submit.prevent="submit"><MqttTopicForm :form="form" /><div class="flex justify-end gap-3"><Link :href="laraiotUrl(`devices/logical/${logicalDevice.id}`)" class="rounded-lg border px-4 py-2.5 text-sm">Cancel</Link><button :disabled="form.processing" class="inline-flex items-center gap-2 rounded-lg bg-[#2583FF] px-4 py-2.5 text-sm text-white"><Save class="size-4" />Save MQTT Topic</button></div></form>
</div></LaraIoTLayout>
</template>
