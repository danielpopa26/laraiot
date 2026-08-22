<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
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
});
</script>

<template>
    <component
        :is="href ? Link : 'div'"
        v-bind="href ? { href } : {}"
        class="group block rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition"
        :class="{
            'hover:border-slate-300 hover:shadow-md': href,
        }"
    >
        <div class="flex items-start justify-between gap-4">
            <!-- Content -->
            <div class="min-w-0">
                <p
                    class="text-sm font-medium text-slate-500"
                >
                    {{ title }}
                </p>

                <p
                    class="mt-2 text-3xl font-semibold tracking-tight text-slate-950"
                >
                    {{ value }}
                </p>

                <p
                    v-if="description"
                    class="mt-2 text-sm leading-5 text-slate-500"
                >
                    {{ description }}
                </p>
            </div>

            <!-- Icon -->
            <div
                class="flex size-11 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-700 transition"
                :class="{
                    'group-hover:bg-slate-900 group-hover:text-white': href,
                }"
            >
                <component
                    :is="icon"
                    class="size-5"
                />
            </div>
        </div>
    </component>
</template>