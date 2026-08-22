<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    title: {
        type: String,
        required: true,
    },
    value: {
        type: [String, Number],
        required: true,
    },
    description: {
        type: String,
        default: null,
    },
    icon: {
        type: [Object, Function],
        required: true,
    },
    href: {
        type: String,
        default: null,
    },
    tone: {
        type: String,
        default: 'blue',
        validator: (value) => ['blue', 'green', 'purple', 'slate'].includes(value),
    },
});

const toneClasses = computed(() => {
    const tones = {
        blue: 'bg-blue-50 text-[#2583FF]',
        green: 'bg-emerald-50 text-[#059669]',
        purple: 'bg-violet-50 text-[#7C3AED]',
        slate: 'bg-slate-100 text-slate-600',
    };

    return tones[props.tone] ?? tones.blue;
});
</script>

<template>
    <component
        :is="href ? Link : 'div'"
        v-bind="href ? { href } : {}"
        class="group block rounded-2xl border border-slate-200/90 bg-white p-5 shadow-sm shadow-slate-900/[0.025] transition-all"
        :class="{
            'hover:-translate-y-0.5 hover:border-blue-200 hover:shadow-md hover:shadow-slate-900/[0.05]': href,
        }"
    >
        <div class="flex items-start justify-between gap-4">
            <div class="min-w-0">
                <p class="text-sm font-medium text-slate-500">
                    {{ title }}
                </p>
                <p class="mt-2 text-3xl font-semibold tracking-tight text-[#0B1735]">
                    {{ value }}
                </p>
                <p
                    v-if="description"
                    class="mt-2 text-sm leading-5 text-slate-500"
                >
                    {{ description }}
                </p>
            </div>

            <div
                class="flex size-11 shrink-0 items-center justify-center rounded-xl transition-transform group-hover:scale-105"
                :class="toneClasses"
            >
                <component
                    :is="icon"
                    class="size-5"
                    :stroke-width="1.8"
                />
            </div>
        </div>
    </component>
</template>
