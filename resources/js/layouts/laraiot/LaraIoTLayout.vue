<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    Activity,
    Boxes,
    ChevronDown,
    Cpu,
    LayoutDashboard,
    RadioTower,
    Settings,
} from 'lucide-vue-next';
import { computed } from 'vue';

type CommunicationMode = 'polling' | 'websocket';

type LaraIoTPageProps = {
    auth?: {
        user?: {
            name?: string;
            email?: string;
        } | null;
    };
    laraiot?: {
        ui?: {
            baseUrl?: string;
        };
        mqtt?: {
            connected?: boolean;
        };
        mode?: CommunicationMode;
    };
    [key: string]: unknown;
};

const page = usePage<LaraIoTPageProps>();

const baseUrl = computed(
    () => page.props.laraiot?.ui?.baseUrl ?? '/laraiot',
);

const mqttConnected = computed(
    () => page.props.laraiot?.mqtt?.connected ?? false,
);

const communicationMode = computed<CommunicationMode>(
    () => page.props.laraiot?.mode ?? 'polling',
);

const userName = computed(
    () => page.props.auth?.user?.name ?? 'Administrator',
);

const navigation = computed(() => [
    {
        label: 'Dashboard',
        href: baseUrl.value,
        icon: LayoutDashboard,
    },
    {
        label: 'Physical devices',
        href: `${baseUrl.value}/physical-devices`,
        icon: Cpu,
    },
    {
        label: 'Logical devices',
        href: `${baseUrl.value}/logical-devices`,
        icon: Boxes,
    },
    {
        label: 'Events',
        href: `${baseUrl.value}/events`,
        icon: Activity,
    },
    {
        label: 'Settings',
        href: `${baseUrl.value}/settings`,
        icon: Settings,
    },
]);

function isActive(href: string): boolean {
    if (href === baseUrl.value) {
        return page.url === href || page.url === `${href}/`;
    }

    return page.url.startsWith(href);
}
</script>

<template>
    <div class="min-h-screen bg-slate-50 text-slate-900">
        <aside
            class="fixed inset-y-0 left-0 z-40 hidden w-64 flex-col border-r border-slate-200 bg-white lg:flex"
        >
            <div class="flex h-16 items-center border-b border-slate-200 px-6">
                <Link
                    :href="baseUrl"
                    class="flex items-center gap-2.5"
                    aria-label="LaraIoT dashboard"
                >
                    <div
                        class="flex size-9 items-center justify-center rounded-xl bg-[#1677F2] text-white"
                    >
                        <RadioTower class="size-5" :stroke-width="2" />
                    </div>

                    <span
                        class="text-xl font-semibold tracking-tight text-[#0B1735]"
                    >
                        Lara<span class="text-[#1677F2]">IoT</span>
                    </span>
                </Link>
            </div>

            <nav class="flex-1 space-y-1 p-4">
                <Link
                    v-for="item in navigation"
                    :key="item.label"
                    :href="item.href"
                    class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition-colors"
                    :class="
                        isActive(item.href)
                            ? 'bg-blue-50 text-[#1677F2]'
                            : 'text-slate-600 hover:bg-slate-50 hover:text-[#0B1735]'
                    "
                >
                    <component
                        :is="item.icon"
                        class="size-5 shrink-0"
                        :stroke-width="1.9"
                    />

                    {{ item.label }}
                </Link>
            </nav>

            <div class="border-t border-slate-200 p-4">
                <div
                    class="rounded-xl bg-slate-50 px-3 py-3 text-xs text-slate-500"
                >
                    <p class="font-medium text-slate-700">
                        LaraIoT
                    </p>
                    <p class="mt-1">
                        IoT management starter
                    </p>
                </div>
            </div>
        </aside>

        <div class="lg:pl-64">
            <header
                class="sticky top-0 z-30 flex h-16 items-center justify-between border-b border-slate-200 bg-white/95 px-4 backdrop-blur sm:px-6 lg:px-8"
            >
                <div class="flex items-center gap-3">
                    <span
                        class="text-sm font-medium text-[#0B1735] lg:hidden"
                    >
                        Lara<span class="text-[#1677F2]">IoT</span>
                    </span>
                </div>

                <div class="flex items-center gap-3">
                    <div
                        class="hidden items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium sm:flex"
                    >
                        <span
                            class="size-2 rounded-full"
                            :class="
                                mqttConnected
                                    ? 'bg-emerald-500'
                                    : 'bg-red-500'
                            "
                        />

                        MQTT

                        <span class="text-slate-500">
                            {{
                                mqttConnected
                                    ? 'Connected'
                                    : 'Disconnected'
                            }}
                        </span>
                    </div>

                    <div
                        class="hidden rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium sm:block"
                    >
                        <span class="text-slate-500">Mode</span>
                        <span class="ml-2 text-[#1677F2]">
                            {{
                                communicationMode === 'websocket'
                                    ? 'WebSocket'
                                    : 'Polling'
                            }}
                        </span>
                    </div>

                    <button
                        type="button"
                        class="flex h-9 items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-50"
                    >
                        <span class="max-w-32 truncate">
                            {{ userName }}
                        </span>

                        <ChevronDown
                            class="size-4 text-slate-400"
                            :stroke-width="1.8"
                        />
                    </button>
                </div>
            </header>

            <main class="p-4 sm:p-6 lg:p-8">
                <slot />
            </main>
        </div>
    </div>
</template>