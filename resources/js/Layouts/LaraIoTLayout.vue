<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();

const laraiot = computed(() => page.props.laraiot ?? {});

const baseUrl = computed(() => {
    const prefix = String(laraiot.value.prefix ?? 'laraiot')
        .replace(/^\/+|\/+$/g, '');

    return `/${prefix}`;
});

const mode = computed(() => {
    const value = String(laraiot.value.mode ?? 'polling');

    return value === 'websocket' ? 'WebSocket' : 'Polling';
});
</script>

<template>
    <div class="min-h-screen bg-slate-50 text-slate-900">
        <div class="min-h-screen md:grid md:grid-cols-[16rem_1fr]">
            <aside
                class="border-b border-slate-200 bg-[#0B1735] text-white md:border-b-0 md:border-r md:border-slate-800"
            >
                <div class="flex h-16 items-center px-6">
                    <Link
                        :href="baseUrl"
                        class="text-xl font-bold tracking-tight"
                    >
                        Lara<span class="text-blue-400">IoT</span>
                    </Link>
                </div>

                <nav class="space-y-1 px-3 pb-5">
                    <Link
                        :href="baseUrl"
                        class="block rounded-lg bg-white/10 px-3 py-2.5 text-sm font-medium text-white"
                    >
                        Dashboard
                    </Link>

                    <div
                        class="rounded-lg px-3 py-2.5 text-sm text-slate-400"
                    >
                        Devices
                    </div>

                    <div
                        class="rounded-lg px-3 py-2.5 text-sm text-slate-400"
                    >
                        MQTT Topics
                    </div>

                    <div
                        class="rounded-lg px-3 py-2.5 text-sm text-slate-400"
                    >
                        Logs
                    </div>

                    <div
                        class="rounded-lg px-3 py-2.5 text-sm text-slate-400"
                    >
                        Settings
                    </div>
                </nav>
            </aside>

            <div class="min-w-0">
                <header
                    class="flex min-h-16 flex-wrap items-center justify-between gap-3 border-b border-slate-200 bg-white px-5 py-3 md:px-8"
                >
                    <div>
                        <p class="text-sm font-semibold text-[#0B1735]">
                            LaraIoT Control Center
                        </p>

                        <p class="text-xs text-slate-500">
                            Optional Vue user interface
                        </p>
                    </div>

                    <div class="flex items-center gap-2 text-xs font-medium">
                        <span
                            class="rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-emerald-700"
                        >
                            MQTT core ready
                        </span>

                        <span
                            class="rounded-full border border-blue-200 bg-blue-50 px-3 py-1.5 text-blue-700"
                        >
                            {{ mode }}
                        </span>
                    </div>
                </header>

                <main class="p-5 md:p-8">
                    <slot />
                </main>
            </div>
        </div>
    </div>
</template>
