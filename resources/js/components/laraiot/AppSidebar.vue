<script setup>
import { Link } from '@inertiajs/vue3';
import {
    Boxes,
    Cpu,
    FlaskConical,
    Gauge,
    Logs,
    Network,
    SlidersHorizontal,
    X,
} from 'lucide-vue-next';

import AppLogo from './AppLogo.vue';
import NavItem from './NavItem.vue';
import { useLaraIoTUrl } from '../../composables/laraiot/useLaraIoTUrl.js';

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
            class="fixed inset-0 z-40 bg-slate-950/60 lg:hidden"
            aria-label="Close navigation"
            @click="emit('close')"
        />
    </Transition>

    <aside
        class="fixed inset-y-0 left-0 z-50 flex w-72 flex-col bg-[#08142E] text-white shadow-xl shadow-slate-950/10 transition-transform duration-200 ease-in-out lg:translate-x-0"
        :class="open ? 'translate-x-0' : '-translate-x-full'"
    >
        <div class="flex h-20 shrink-0 items-center justify-between border-b border-white/10 px-5">
            <Link
                :href="laraiotUrl()"
                class="min-w-0"
                aria-label="LaraIoT dashboard"
                @click="emit('close')"
            >
                <AppLogo variant="dark" />
            </Link>

            <button
                type="button"
                class="flex size-9 items-center justify-center rounded-lg text-slate-300 transition-colors hover:bg-white/[0.08] hover:text-white lg:hidden"
                aria-label="Close sidebar"
                @click="emit('close')"
            >
                <X class="size-5" />
            </button>
        </div>

        <nav class="flex-1 overflow-y-auto px-4 py-5" aria-label="LaraIoT navigation">
            <template
                v-for="(section, sectionIndex) in navigation"
                :key="sectionIndex"
            >
                <div v-if="section.path !== undefined" class="mb-1">
                    <NavItem
                        :label="section.label"
                        :href="laraiotUrl(section.path)"
                        :icon="section.icon"
                        :exact="section.exact ?? false"
                        @navigate="emit('close')"
                    />
                </div>

                <div v-else class="mb-6">
                    <div class="mb-2 px-3 text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">
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
                            @navigate="emit('close')"
                        />
                    </div>
                </div>
            </template>
        </nav>

        <div class="border-t border-white/10 p-4">
            <div class="flex items-center gap-3 rounded-xl bg-white/[0.06] p-3 text-slate-300">
                <FlaskConical
                    class="size-5 shrink-0 text-[#78AFFF]"
                    :stroke-width="1.8"
                />
                <div class="min-w-0">
                    <p class="text-xs font-semibold text-white">
                        Research platform
                    </p>
                    <p class="mt-0.5 truncate text-[11px] text-slate-400">
                        Laravel · MQTT · IoT
                    </p>
                </div>
            </div>
        </div>
    </aside>
</template>
