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

const active = computed(() => {
    if (props.exact) {
        return page.url === props.href;
    }

    return page.url === props.href
        || page.url.startsWith(`${props.href}/`);
});

const handleClick = () => {
    emit('navigate');
};
</script>

<template>
    <Link
        :href="href"
        class="group flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition"
        :class="
            active
                ? 'bg-slate-900 text-white'
                : 'text-slate-600 hover:bg-slate-100 hover:text-slate-950'
        "
        @click="handleClick"
    >
        <component
            :is="icon"
            class="size-5 shrink-0"
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