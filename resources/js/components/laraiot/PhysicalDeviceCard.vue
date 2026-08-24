<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import {
    Cpu,
    ExternalLink,
    Network,
    Plus,
} from 'lucide-vue-next';

import LogicalDeviceControl from './LogicalDeviceControl.vue';
import StatusBadge from './StatusBadge.vue';
import { useLaraIoTUrl } from '../../composables/laraiot/useLaraIoTUrl.js';

const props = defineProps({
    physicalDevice: {
        type: Object,
        required: true,
    },
});

const { laraiotUrl } = useLaraIoTUrl();

const visibleLogicalDevices = computed(() =>
    [...(props.physicalDevice.logical_devices ?? [])]
        .sort((left, right) => {
            const leftHasCommand = left.command_topic ? 1 : 0;
            const rightHasCommand = right.command_topic ? 1 : 0;

            return rightHasCommand - leftHasCommand;
        })
        .slice(0, 2),
);

const hiddenLogicalDeviceCount = computed(() =>
    Math.max(
        0,
        (props.physicalDevice.logical_devices?.length ?? 0) - 2,
    ),
);
</script>

<template>
    <article class="flex h-full min-h-[30rem] flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm shadow-slate-900/[0.025]">
        <header class="flex flex-col gap-4 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="flex min-w-0 items-start gap-3">
                <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-[#2583FF]">
                    <Cpu class="size-5" />
                </span>

                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <Link
                            :href="laraiotUrl(`devices/physical/${physicalDevice.id}`)"
                            class="truncate font-semibold text-[#0B1735] hover:text-[#2583FF]"
                        >
                            {{ physicalDevice.name }}
                        </Link>
                        <StatusBadge
                            :label="physicalDevice.is_enabled ? 'Enabled' : 'Disabled'"
                            :status="physicalDevice.is_enabled ? 'success' : 'neutral'"
                        />
                    </div>

                    <p class="mt-1 text-xs text-slate-500">
                        <code>{{ physicalDevice.identifier }}</code>
                        <template v-if="physicalDevice.ip_address">
                            <span class="px-1 text-slate-300">·</span>
                            {{ physicalDevice.ip_address }}
                        </template>
                    </p>
                </div>
            </div>

            <div class="flex shrink-0 items-center gap-2">
                <Link
                    v-if="physicalDevice.is_enabled"
                    :href="laraiotUrl(`devices/logical/create?physical_device_id=${physicalDevice.id}`)"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-blue-200 bg-blue-50 px-3 py-2 text-xs font-medium text-[#2583FF] hover:bg-blue-100"
                >
                    <Plus class="size-3.5" />
                    Add Logical Device
                </Link>

                <Link
                    :href="laraiotUrl(`devices/physical/${physicalDevice.id}`)"
                    class="flex size-9 items-center justify-center rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-50"
                    aria-label="View physical device"
                >
                    <ExternalLink class="size-4" />
                </Link>
            </div>
        </header>

        <div
            v-if="(physicalDevice.logical_devices?.length ?? 0) === 0"
            class="flex flex-1 flex-col items-center justify-center px-6 py-10 text-center"
        >
            <span class="flex size-11 items-center justify-center rounded-xl bg-slate-100 text-slate-400">
                <Network class="size-5" />
            </span>
            <p class="mt-3 text-sm font-medium text-[#0B1735]">
                No logical devices attached
            </p>
            <p class="mt-1 max-w-md text-xs text-slate-500">
                <template v-if="physicalDevice.is_enabled">
                    Add a logical device to expose sensor values or equipment controls.
                </template>
                <template v-else>
                    Enable this physical device before attaching logical devices.
                </template>
            </p>
        </div>

        <div v-else class="flex-1 space-y-3 p-4">
            <LogicalDeviceControl
                v-for="logicalDevice in visibleLogicalDevices"
                :key="logicalDevice.id"
                :device="logicalDevice"
                compact
                :reload-props="['physicalDevices', 'recentActivity']"
                :show-configuration-link="false"
            />

            <p
                v-if="hiddenLogicalDeviceCount > 0"
                class="px-1 text-xs font-medium text-slate-500"
            >
                + {{ hiddenLogicalDeviceCount }} additional logical device{{ hiddenLogicalDeviceCount === 1 ? '' : 's' }}
            </p>
        </div>

        <footer class="border-t border-slate-100 px-5 py-3 text-right">
            <Link
                :href="laraiotUrl(`devices/physical/${physicalDevice.id}`)"
                class="text-xs font-medium text-[#2583FF] hover:text-blue-700"
            >
                View device details
            </Link>
        </footer>
    </article>
</template>
