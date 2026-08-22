<script setup>
import { Link } from '@inertiajs/vue3';
import NavItem from './NavItem.vue';
import { useLaraIoTUrl } from '../../composables/laraiot/useLaraIoTUrl.js';

import {
    Activity,
    Boxes,
    Cpu,
    Gauge,
    Logs,
    Network,
    Settings,
    SlidersHorizontal,
    X,
} from 'lucide-vue-next';

defineProps({
    open: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['close']);
const { laraiotUrl } = useLaraIoTUrl();

const navigation = [
    {
        label: 'Dashboard',
        path: '',
        icon: Gauge,
        exact: true,
    },
    {
        heading: 'Devices',
        items: [
            {
                label: 'Physical Devices',
                path: 'devices/physical',
                icon: Cpu,
            },
            {
                label: 'Logical Devices',
                path: 'devices/logical',
                icon: Boxes,
            },
        ],
    },
    {
        label: 'Logs',
        path: 'logs',
        icon: Logs,
    },
    {
        heading: 'Settings',
        items: [
            {
                label: 'Application',
                path: 'settings',
                icon: SlidersHorizontal,
                exact: true,
            },
            {
                label: 'Device Types',
                path: 'settings/device-types',
                icon: Network,
            },
        ],
    },
];

const close = () => {
    emit('close');
};
</script>

<template>
    <Transition
        enter-active-class="transition-opacity duration-200"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition-opacity duration-200"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
    >
        <button
            v-if="open"
            type="button"
            class="fixed inset-0 z-40 bg-slate-950/50 lg:hidden"
            aria-label="Close navigation"
            @click="close"
        />
    </Transition>

    <aside
        class="fixed inset-y-0 left-0 z-50 flex w-72 flex-col border-r border-slate-200 bg-white transition-transform duration-200 ease-in-out lg:translate-x-0"
        :class="open ? 'translate-x-0' : '-translate-x-full'"
    >
        <div class="flex h-16 shrink-0 items-center justify-between border-b border-slate-200 px-5">
            <Link
                :href="laraiotUrl()"
                class="flex items-center gap-3"
                @click="close"
            >
                <div class="flex size-9 items-center justify-center rounded-lg bg-slate-900 text-white">
                    <Activity class="size-5" />
                </div>

                <div class="leading-tight">
                    <div class="text-base font-semibold tracking-tight text-slate-950">
                        LaraIoT
                    </div>
                    <div class="text-xs text-slate-500">
                        IoT Management
                    </div>
                </div>
            </Link>

            <button
                type="button"
                class="flex size-9 items-center justify-center rounded-md text-slate-500 transition hover:bg-slate-100 hover:text-slate-900 lg:hidden"
                aria-label="Close sidebar"
                @click="close"
            >
                <X class="size-5" />
            </button>
        </div>

        <nav class="flex-1 overflow-y-auto px-4 py-5">
            <template
                v-for="(section, sectionIndex) in navigation"
                :key="sectionIndex"
            >
                <div
                    v-if="section.path !== undefined"
                    class="mb-1"
                >
                    <NavItem
                        :label="section.label"
                        :href="laraiotUrl(section.path)"
                        :icon="section.icon"
                        :exact="section.exact ?? false"
                        @navigate="close"
                    />
                </div>

                <div
                    v-else
                    class="mb-6"
                >
                    <div class="mb-2 px-3 text-xs font-semibold uppercase tracking-wider text-slate-400">
                        {{ section.heading }}
                    </div>

                    <div class="space-y-1">
                        <NavItem
                            v-for="item in section.items"
                            :key="item.path"
                            :label="item.label"
                            :href="laraiotUrl(item.path)"
                            :icon="item.icon"
                            :exact="item.exact ?? false"
                            @navigate="close"
                        />
                    </div>
                </div>
            </template>
        </nav>

        <div class="border-t border-slate-200 p-4">
            <div class="rounded-lg bg-slate-50 px-3 py-3">
                <div class="flex items-center gap-2">
                    <Settings class="size-4 text-slate-500" />
                    <span class="text-xs font-medium text-slate-700">
                        LaraIoT
                    </span>
                </div>
                <p class="mt-1 text-xs leading-5 text-slate-500">
                    Laravel IoT monitoring and control
                </p>
            </div>
        </div>
    </aside>
</template>
