<script setup lang="ts">
import LaraIoTLayout from '@/layouts/laraiot/LaraIoTLayout.vue';
import { Head } from '@inertiajs/vue3';
</script>

<template>
    <Head title="LaraIoT Dashboard" />

    <LaraIoTLayout>
        <div class="space-y-6">
            <div>
                <h1 class="text-2xl font-semibold text-[#0B1735]">
                    Dashboard
                </h1>

                <p class="mt-1 text-sm text-slate-500">
                    LaraIoT system overview
                </p>
            </div>

            <div
                class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"
            >
                <h2 class="font-semibold text-[#0B1735]">
                    LaraIoT starter UI
                </h2>

                <p class="mt-2 text-sm text-slate-500">
                    The LaraIoT Vue starter interface is installed and ready.
                </p>
            </div>
        </div>
    </LaraIoTLayout>
</template>