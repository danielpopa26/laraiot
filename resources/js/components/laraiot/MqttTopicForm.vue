<script setup>
import {
    Plus,
    Trash2,
} from 'lucide-vue-next';

const props = defineProps({
    form: {
        type: Object,
        required: true,
    },
});

const addValueMapEntry = () => {
    props.form.value_map.push({
        source: '',
        target: '',
    });
};

const removeValueMapEntry = (index) => {
    props.form.value_map.splice(index, 1);
};
</script>

<template>
    <div class="space-y-6">
        <section
            class="rounded-xl border border-slate-200 bg-white shadow-sm"
        >
            <div class="border-b border-slate-200 px-6 py-4">
                <h2 class="text-base font-semibold text-[#0B1735]">
                    Topic information
                </h2>

                <p class="mt-1 text-sm text-slate-500">
                    Configure the MQTT topic and its purpose.
                </p>
            </div>

            <div class="space-y-5 p-6">
                <div>
                    <label
                        for="purpose"
                        class="block text-sm font-medium text-slate-700"
                    >
                        Purpose
                        <span class="text-red-500">*</span>
                    </label>

                    <select
                        id="purpose"
                        v-model="form.purpose"
                        class="mt-2 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-[#0B1735] shadow-sm outline-none transition focus:border-[#2583FF] focus:ring-2 focus:ring-blue-100"
                    >
                        <option value="state">
                            State
                        </option>

                        <option value="command">
                            Command
                        </option>
                    </select>

                    <p
                        v-if="form.errors.purpose"
                        class="mt-1.5 text-sm text-red-600"
                    >
                        {{ form.errors.purpose }}
                    </p>
                </div>

                <div>
                    <label
                        for="topic"
                        class="block text-sm font-medium text-slate-700"
                    >
                        Topic
                        <span class="text-red-500">*</span>
                    </label>

                    <input
                        id="topic"
                        v-model="form.topic"
                        type="text"
                        autocomplete="off"
                        spellcheck="false"
                        class="mt-2 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 font-mono text-sm text-[#0B1735] shadow-sm outline-none transition placeholder:font-sans placeholder:text-slate-400 focus:border-[#2583FF] focus:ring-2 focus:ring-blue-100"
                        :class="{
                            'border-red-300 focus:border-red-400 focus:ring-red-100':
                                form.errors.topic,
                        }"
                        placeholder="tele/device/state"
                    />

                    <p
                        v-if="form.errors.topic"
                        class="mt-1.5 text-sm text-red-600"
                    >
                        {{ form.errors.topic }}
                    </p>

                    <p
                        v-else
                        class="mt-1.5 text-xs text-slate-500"
                    >
                        Use the exact MQTT topic configured by the equipment.
                    </p>
                </div>
            </div>
        </section>

        <section
            v-if="form.purpose === 'state'"
            class="rounded-xl border border-slate-200 bg-white shadow-sm"
        >
            <div class="border-b border-slate-200 px-6 py-4">
                <h2 class="text-base font-semibold text-[#0B1735]">
                    State payload
                </h2>

                <p class="mt-1 text-sm text-slate-500">
                    Configure how LaraIoT extracts and normalizes the received value.
                </p>
            </div>

            <div class="space-y-5 p-6">
                <div>
                    <label
                        for="payload_format"
                        class="block text-sm font-medium text-slate-700"
                    >
                        Payload Format
                        <span class="text-red-500">*</span>
                    </label>

                    <select
                        id="payload_format"
                        v-model="form.payload_format"
                        class="mt-2 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-[#0B1735] shadow-sm outline-none transition focus:border-[#2583FF] focus:ring-2 focus:ring-blue-100"
                    >
                        <option value="raw">
                            RAW
                        </option>

                        <option value="json">
                            JSON
                        </option>
                    </select>

                    <p
                        v-if="form.errors.payload_format"
                        class="mt-1.5 text-sm text-red-600"
                    >
                        {{ form.errors.payload_format }}
                    </p>
                </div>

                <div v-if="form.payload_format === 'json'">
                    <label
                        for="value_path"
                        class="block text-sm font-medium text-slate-700"
                    >
                        JSON Value Path
                        <span class="text-red-500">*</span>
                    </label>

                    <input
                        id="value_path"
                        v-model="form.value_path"
                        type="text"
                        autocomplete="off"
                        spellcheck="false"
                        class="mt-2 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 font-mono text-sm text-[#0B1735] shadow-sm outline-none transition placeholder:font-sans placeholder:text-slate-400 focus:border-[#2583FF] focus:ring-2 focus:ring-blue-100"
                        placeholder="MS01.Humidity"
                    />

                    <p
                        v-if="form.errors.value_path"
                        class="mt-1.5 text-sm text-red-600"
                    >
                        {{ form.errors.value_path }}
                    </p>
                </div>

                <div>
                    <div
                        class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div>
                            <label class="block text-sm font-medium text-slate-700">
                                Value Mapping
                            </label>

                            <p class="mt-1 text-xs text-slate-500">
                                Optional mapping from equipment values to normalized LaraIoT values.
                            </p>
                        </div>

                        <button
                            type="button"
                            class="inline-flex items-center justify-center gap-2 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
                            @click="addValueMapEntry"
                        >
                            <Plus class="size-4" />

                            Add Mapping
                        </button>
                    </div>

                    <div
                        v-if="form.value_map.length > 0"
                        class="mt-4 space-y-3"
                    >
                        <div
                            v-for="(mapping, index) in form.value_map"
                            :key="index"
                            class="grid gap-3 sm:grid-cols-[1fr_1fr_auto]"
                        >
                            <input
                                v-model="mapping.source"
                                type="text"
                                class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 font-mono text-sm text-[#0B1735] shadow-sm outline-none transition focus:border-[#2583FF] focus:ring-2 focus:ring-blue-100"
                                placeholder="ON"
                            />

                            <input
                                v-model="mapping.target"
                                type="text"
                                class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 font-mono text-sm text-[#0B1735] shadow-sm outline-none transition focus:border-[#2583FF] focus:ring-2 focus:ring-blue-100"
                                placeholder="on"
                            />

                            <button
                                type="button"
                                class="flex size-10 items-center justify-center rounded-lg text-red-600 transition hover:bg-red-50"
                                title="Remove mapping"
                                @click="removeValueMapEntry(index)"
                            >
                                <Trash2 class="size-4" />
                            </button>
                        </div>
                    </div>

                    <p
                        v-if="form.errors.value_map"
                        class="mt-1.5 text-sm text-red-600"
                    >
                        {{ form.errors.value_map }}
                    </p>
                </div>
            </div>
        </section>

        <section
            v-if="form.purpose === 'command'"
            class="rounded-xl border border-slate-200 bg-white shadow-sm"
        >
            <div class="border-b border-slate-200 px-6 py-4">
                <h2 class="text-base font-semibold text-[#0B1735]">
                    Command payloads
                </h2>

                <p class="mt-1 text-sm text-slate-500">
                    Map LaraIoT's logical on/off commands to the payloads expected by the equipment.
                </p>
            </div>

            <div class="grid gap-5 p-6 md:grid-cols-2">
                <div>
                    <label
                        for="command_on"
                        class="block text-sm font-medium text-slate-700"
                    >
                        ON Payload
                        <span class="text-red-500">*</span>
                    </label>

                    <textarea
                        id="command_on"
                        v-model="form.command_on"
                        rows="4"
                        class="mt-2 block w-full resize-y rounded-lg border border-slate-300 bg-white px-3 py-2.5 font-mono text-sm text-[#0B1735] shadow-sm outline-none transition focus:border-[#2583FF] focus:ring-2 focus:ring-blue-100"
                        placeholder="ON"
                    />

                    <p
                        v-if="form.errors.command_on"
                        class="mt-1.5 text-sm text-red-600"
                    >
                        {{ form.errors.command_on }}
                    </p>
                </div>

                <div>
                    <label
                        for="command_off"
                        class="block text-sm font-medium text-slate-700"
                    >
                        OFF Payload
                        <span class="text-red-500">*</span>
                    </label>

                    <textarea
                        id="command_off"
                        v-model="form.command_off"
                        rows="4"
                        class="mt-2 block w-full resize-y rounded-lg border border-slate-300 bg-white px-3 py-2.5 font-mono text-sm text-[#0B1735] shadow-sm outline-none transition focus:border-[#2583FF] focus:ring-2 focus:ring-blue-100"
                        placeholder="OFF"
                    />

                    <p
                        v-if="form.errors.command_off"
                        class="mt-1.5 text-sm text-red-600"
                    >
                        {{ form.errors.command_off }}
                    </p>
                </div>
            </div>
        </section>

        <section
            class="rounded-xl border border-slate-200 bg-white shadow-sm"
        >
            <div class="border-b border-slate-200 px-6 py-4">
                <h2 class="text-base font-semibold text-[#0B1735]">
                    MQTT options
                </h2>
            </div>

            <div class="space-y-5 p-6">
                <div>
                    <label
                        for="qos"
                        class="block text-sm font-medium text-slate-700"
                    >
                        QoS
                    </label>

                    <select
                        id="qos"
                        v-model.number="form.qos"
                        class="mt-2 block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-[#0B1735] shadow-sm outline-none transition focus:border-[#2583FF] focus:ring-2 focus:ring-blue-100"
                    >
                        <option :value="0">
                            0 — At most once
                        </option>

                        <option :value="1">
                            1 — At least once
                        </option>

                        <option :value="2">
                            2 — Exactly once
                        </option>
                    </select>

                    <p
                        v-if="form.errors.qos"
                        class="mt-1.5 text-sm text-red-600"
                    >
                        {{ form.errors.qos }}
                    </p>
                </div>

                <div
                    v-if="form.purpose === 'command'"
                    class="flex items-start gap-3 rounded-lg border border-slate-200 bg-slate-50 p-4"
                >
                    <input
                        id="retain"
                        v-model="form.retain"
                        type="checkbox"
                        class="mt-0.5 size-4 rounded border-slate-300 text-[#0B1735] focus:ring-slate-400"
                    />

                    <div>
                        <label
                            for="retain"
                            class="text-sm font-medium text-slate-800"
                        >
                            Retain command
                        </label>

                        <p class="mt-1 text-xs leading-5 text-slate-500">
                            Ask the MQTT broker to retain the published command payload.
                        </p>
                    </div>
                </div>

                <div
                    class="flex items-start gap-3 rounded-lg border border-slate-200 bg-slate-50 p-4"
                >
                    <input
                        id="is_enabled"
                        v-model="form.is_enabled"
                        type="checkbox"
                        class="mt-0.5 size-4 rounded border-slate-300 text-[#0B1735] focus:ring-slate-400"
                    />

                    <div>
                        <label
                            for="is_enabled"
                            class="text-sm font-medium text-slate-800"
                        >
                            Enabled
                        </label>

                        <p class="mt-1 text-xs leading-5 text-slate-500">
                            Disabled topics cannot be used or functionally validated.
                        </p>
                    </div>
                </div>
            </div>
        </section>
    </div>
</template>
