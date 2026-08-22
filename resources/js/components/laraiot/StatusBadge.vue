<script setup>
import { computed } from 'vue';

const props = defineProps({
    label: {
        type: String,
        required: true,
    },

    status: {
        type: String,
        default: 'neutral',
    },

    dot: {
        type: Boolean,
        default: true,
    },

    size: {
        type: String,
        default: 'sm',
        validator: (value) => ['sm', 'md'].includes(value),
    },
});

const statusClasses = computed(() => {
    const variants = {
        success: 'border-emerald-200 bg-emerald-50 text-emerald-700',
        danger: 'border-red-200 bg-red-50 text-red-700',
        warning: 'border-amber-200 bg-amber-50 text-amber-700',
        info: 'border-blue-200 bg-blue-50 text-blue-700',
        neutral: 'border-slate-200 bg-slate-50 text-slate-600',
    };

    return variants[props.status] ?? variants.neutral;
});

const dotClasses = computed(() => {
    const variants = {
        success: 'bg-emerald-500',
        danger: 'bg-red-500',
        warning: 'bg-amber-500',
        info: 'bg-blue-500',
        neutral: 'bg-slate-400',
    };

    return variants[props.status] ?? variants.neutral;
});

const sizeClasses = computed(() => {
    return props.size === 'md'
        ? 'px-3 py-1.5 text-sm'
        : 'px-2.5 py-1 text-xs';
});
</script>

<template>
    <span
        class="inline-flex items-center gap-2 rounded-full border font-medium"
        :class="[statusClasses, sizeClasses]"
    >
        <span
            v-if="dot"
            class="size-2 shrink-0 rounded-full"
            :class="dotClasses"
        />

        <span>
            {{ label }}
        </span>
    </span>
</template>