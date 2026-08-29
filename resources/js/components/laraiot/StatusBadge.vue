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
        success: 'border-emerald-200 bg-emerald-50 text-[#059669]',
        danger: 'border-red-200 bg-red-50 text-[#DC2626]',
        warning: 'border-amber-200 bg-amber-50 text-[#D97706]',
        info: 'border-blue-200 bg-blue-50 text-[#2583FF]',
        neutral: 'border-slate-200 bg-slate-50 text-slate-500',
    };

    return variants[props.status] ?? variants.neutral;
});

const dotClasses = computed(() => {
    const variants = {
        success: 'bg-[#059669]',
        danger: 'bg-[#DC2626]',
        warning: 'bg-[#D97706]',
        info: 'bg-[#2583FF]',
        neutral: 'bg-[#94A3B8]',
    };

    return variants[props.status] ?? variants.neutral;
});

const sizeClasses = computed(() =>
    props.size === 'md'
        ? 'px-3 py-1.5 text-sm'
        : 'px-2.5 py-1 text-xs',
);
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
        <span>{{ label }}</span>
    </span>
</template>
