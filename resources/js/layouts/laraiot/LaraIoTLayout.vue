<script setup>
import { ref } from 'vue';

import AppSidebar from '../../components/laraiot/AppSidebar.vue';
import AppTopbar from '../../components/laraiot/AppTopbar.vue';

const sidebarOpen = ref(false);

const openSidebar = () => {
    sidebarOpen.value = true;
};

const closeSidebar = () => {
    sidebarOpen.value = false;
};
</script>

<template>
    <div class="min-h-screen bg-slate-50 text-slate-900">
        <!--
            Navigation sidebar.

            Desktop:
            - permanently visible

            Mobile:
            - opened from the top bar
            - closed by the sidebar overlay / close button
        -->
        <AppSidebar
            :open="sidebarOpen"
            @close="closeSidebar"
        />

        <!-- Main application area -->
        <div class="min-h-screen lg:pl-72">
            <AppTopbar
                @toggle-sidebar="openSidebar"
            />

            <main class="px-4 py-6 sm:px-6 lg:px-8">
                <div class="mx-auto w-full max-w-screen-2xl">
                    <slot />
                </div>
            </main>
        </div>
    </div>
</template>