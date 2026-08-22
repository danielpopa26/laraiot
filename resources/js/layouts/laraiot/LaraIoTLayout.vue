<script setup>
import { ref } from 'vue';

import '@fontsource/inter/400.css';
import '@fontsource/inter/500.css';
import '@fontsource/inter/600.css';
import '@fontsource/inter/700.css';
import '@fontsource/jetbrains-mono/400.css';
import '@fontsource/jetbrains-mono/500.css';
import '../../../css/laraiot.css';

import AppSidebar from '../../components/laraiot/AppSidebar.vue';
import AppTopbar from '../../components/laraiot/AppTopbar.vue';

const sidebarOpen = ref(false);
</script>

<template>
    <div class="laraiot-shell">
        <AppSidebar
            :open="sidebarOpen"
            @close="sidebarOpen = false"
        />

        <div class="min-h-screen lg:pl-72">
            <AppTopbar
                @toggle-sidebar="sidebarOpen = true"
            />

            <main class="px-4 py-6 sm:px-6 lg:px-8">
                <div class="mx-auto w-full max-w-[1600px]">
                    <slot />
                </div>
            </main>
        </div>
    </div>
</template>
