<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { ChevronRight } from 'lucide-vue-next';

const props = defineProps({
    label: {
        type: String,
        required: true,
    },
    href: {
        type: String,
        required: true,
    },
    icon: {
        type: [Object, Function],
        required: true,
    },
    exact: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['navigate']);
const page = usePage();

const normalizePath = (value) => {
    const path = String(value ?? '').split('?')[0].replace(/\/+$/, '');

    return path === '' ? '/' : path;
};

const active = computed(() => {
    const current = normalizePath(page.url);
    const target = normalizePath(props.href);

    if (props.exact) {
        return current === target;
    }

    return current === target || current.startsWith(`${target}/`);
});
</script>

<template>
    <Link
        :href="href"
        class="group flex min-h-11 items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition-colors"
        :class="
            active
                ? 'bg-[#2583FF] text-white shadow-sm shadow-blue-950/20'
                : 'text-slate-300 hover:bg-white/[0.08] hover:text-white'
        "
        :aria-current="active ? 'page' : undefined"
        @click="emit('navigate')"
    >
        <component
            :is="icon"
            class="size-5 shrink-0"
            :stroke-width="1.8"
        />

        <span class="flex-1 truncate">
            {{ label }}
        </span>

        <ChevronRight
            v-if="active"
            class="size-4 shrink-0 opacity-70"
        />
    </Link>
</template>
