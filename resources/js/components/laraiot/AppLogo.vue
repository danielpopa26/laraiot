<script setup>
defineProps({
    variant: {
        type: String,
        default: 'light',
        validator: (value) => ['light', 'dark'].includes(value),
    },
});

import AppLogoIcon from './AppLogoIcon.vue';
</script>

<template>
    <div class="flex min-w-0 items-center gap-2.5 font-medium">
        <div class="flex size-11 shrink-0 items-center justify-center">
            <AppLogoIcon
                class="size-11"
                :class="variant === 'dark' ? 'text-white' : 'text-[#0B1735]'"
            />
        </div>

        <span
            class="truncate text-[1.72rem] leading-none font-semibold tracking-tight"
            :class="variant === 'dark' ? 'text-white' : 'text-[#0B1735]'"
        >
            Lara<span class="text-[#2583FF]">IoT</span>
        </span>
    </div>
</template>
