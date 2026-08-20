<script setup>
import LaraIoTLayout from '../../Layouts/LaraIoTLayout.vue';

const cards = [
    {
        title: 'Physical devices',
        value: '—',
        description: 'Device inventory will be connected next.',
    },
    {
        title: 'Logical devices',
        value: '—',
        description: 'Logical device state will appear here.',
    },
    {
        title: 'MQTT topics',
        value: '—',
        description: 'State and command topics are available in the core.',
    },
    {
        title: 'Activity log',
        value: '—',
        description: 'Recent LaraIoT activity will be displayed here.',
    },
];
</script>

<template>
    <LaraIoTLayout>
        <section>
            <div>
                <p
                    class="text-xs font-semibold uppercase tracking-[0.16em] text-blue-600"
                >
                    Overview
                </p>

                <h1
                    class="mt-1 text-2xl font-bold tracking-tight text-[#0B1735]"
                >
                    Dashboard
                </h1>

                <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500">
                    The LaraIoT Vue interface is installed and running.
                    Dashboard data will be connected to the package core in
                    the next step.
                </p>
            </div>

            <div
                class="mt-7 grid gap-4 sm:grid-cols-2 xl:grid-cols-4"
            >
                <article
                    v-for="card in cards"
                    :key="card.title"
                    class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
                >
                    <p class="text-sm font-medium text-slate-500">
                        {{ card.title }}
                    </p>

                    <p
                        class="mt-3 text-3xl font-bold tracking-tight text-[#0B1735]"
                    >
                        {{ card.value }}
                    </p>

                    <p class="mt-3 text-xs leading-5 text-slate-500">
                        {{ card.description }}
                    </p>
                </article>
            </div>

            <div
                class="mt-6 rounded-2xl border border-dashed border-blue-200 bg-blue-50/60 p-5"
            >
                <p class="text-sm font-semibold text-blue-900">
                    UI bootstrap successful
                </p>

                <p class="mt-1 text-sm leading-6 text-blue-700">
                    If this page is visible at the configured LaraIoT prefix,
                    the optional UI route, Inertia rendering and published Vue
                    resources are working together.
                </p>
            </div>
        </section>
    </LaraIoTLayout>
</template>
