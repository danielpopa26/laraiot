<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, FlaskConical, Radio, Save } from 'lucide-vue-next';
import LaraIoTLayout from '../../../../../layouts/laraiot/LaraIoTLayout.vue';
import MqttTopicForm from '../../../../../components/laraiot/MqttTopicForm.vue';
import StatusBadge from '../../../../../components/laraiot/StatusBadge.vue';
import { useLaraIoTUrl } from '../../../../../composables/laraiot/useLaraIoTUrl.js';

const props = defineProps({
    logicalDevice: { type: Object, required: true },
    mqttTopic: { type: Object, required: true },
    stateTopics: { type: Array, default: () => [] },
});

const { laraiotUrl } = useLaraIoTUrl();

const form = useForm({
    purpose: props.mqttTopic.purpose ?? 'state',
    topic: props.mqttTopic.topic ?? '',
    payload_format: props.mqttTopic.payload_mapping?.format ?? 'raw',
    value_path: props.mqttTopic.payload_mapping?.value_path ?? '',
    value_map: Object.entries(props.mqttTopic.payload_mapping?.value_map ?? {}).map(([source, target]) => ({ source, target: String(target) })),
    command_on: props.mqttTopic.payload_mapping?.on ?? '',
    command_off: props.mqttTopic.payload_mapping?.off ?? '',
    qos: props.mqttTopic.qos ?? 0,
    retain: props.mqttTopic.retain ?? false,
    is_enabled: props.mqttTopic.is_enabled ?? true,
});

const validationStateTopicId = ref('');
const usableStateTopics = computed(() => props.stateTopics.filter((topic) =>
    topic.is_enabled === true && topic.validated_at !== null && topic.id !== props.mqttTopic.id
));

const canValidate = computed(() => {
    if (form.isDirty || !props.mqttTopic.is_enabled) return false;
    if (props.mqttTopic.purpose === 'state') return true;
    return validationStateTopicId.value !== '';
});

const submit = () => form.put(laraiotUrl(`devices/logical/${props.logicalDevice.id}/mqtt-topics/${props.mqttTopic.id}`));

const validateTopic = () => {
    if (!canValidate.value) return;
    router.post(
        laraiotUrl(`devices/logical/${props.logicalDevice.id}/mqtt-topics/${props.mqttTopic.id}/validate`),
        props.mqttTopic.purpose === 'command' ? { state_topic_id: validationStateTopicId.value } : {},
        { preserveScroll: true },
    );
};
</script>

<template>
<Head title="Edit MQTT Topic" />
<LaraIoTLayout><div class="mx-auto max-w-4xl space-y-6">
<Link :href="laraiotUrl(`devices/logical/${logicalDevice.id}`)" class="inline-flex items-center gap-2 text-sm text-slate-500"><ArrowLeft class="size-4" />{{ logicalDevice.name }}</Link>
<div class="flex items-start justify-between gap-4"><div class="flex gap-4"><div class="flex size-11 items-center justify-center rounded-xl bg-slate-900 text-white"><Radio class="size-5" /></div><div><h1 class="text-2xl font-semibold">Edit MQTT Topic</h1><code class="mt-1 block text-sm text-slate-500">{{ mqttTopic.topic }}</code></div></div><StatusBadge :label="mqttTopic.validated_at ? 'Validated' : 'Not validated'" :status="mqttTopic.validated_at ? 'success' : 'warning'" /></div>
<form class="space-y-6" @submit.prevent="submit"><MqttTopicForm :form="form" /><div class="flex justify-end gap-3"><Link :href="laraiotUrl(`devices/logical/${logicalDevice.id}`)" class="rounded-lg border px-4 py-2.5 text-sm">Cancel</Link><button :disabled="form.processing || !form.isDirty" class="inline-flex items-center gap-2 rounded-lg bg-slate-900 px-4 py-2.5 text-sm text-white"><Save class="size-4" />Save Changes</button></div></form>
<section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
<h2 class="font-semibold">Functional Validation</h2>
<div v-if="mqttTopic.purpose === 'command'" class="mt-4"><select v-model="validationStateTopicId" class="block w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm"><option value="">Select validated state topic</option><option v-for="topic in usableStateTopics" :key="topic.id" :value="topic.id">{{ topic.topic }}</option></select></div>
<button type="button" :disabled="!canValidate" class="mt-4 inline-flex items-center gap-2 rounded-lg border border-slate-300 px-4 py-2.5 text-sm disabled:opacity-50" @click="validateTopic"><FlaskConical class="size-4" />{{ mqttTopic.validated_at ? 'Validate Again' : 'Validate Topic' }}</button>
</section>
</div></LaraIoTLayout>
</template>
