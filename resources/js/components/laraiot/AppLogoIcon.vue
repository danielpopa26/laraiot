<script setup>
defineOptions({
    inheritAttrs: false,
});
</script>

<template>
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 512 512"
        aria-hidden="true"
        v-bind="$attrs"
    >
        <g fill="currentColor">
            <path
                d="M96 72h68v214c0 23.2 18.8 42 42 42h120v68H206c-60.8 0-110-49.2-110-110V72Z"
            />
            <rect x="338" y="224" width="68" height="172" />
        </g>

        <g
            fill="none"
            stroke="#2583ff"
            stroke-linecap="round"
            stroke-linejoin="round"
            stroke-width="9"
        >
            <path d="M130 128v184c0 26.5 21.5 48 48 48h114" />

            <circle cx="130" cy="128" r="14" fill="currentColor" />
            <circle cx="130" cy="312" r="14" fill="currentColor" />
            <circle cx="292" cy="360" r="14" fill="currentColor" />

            <path d="M326 111c-16 16-16 46 0 62" />
            <path d="M418 111c16 16 16 46 0 62" />
            <path d="M304 88c-28 30-28 78 0 108" />
            <path d="M440 88c28 30 28 78 0 108" />
        </g>

        <circle cx="372" cy="142" r="32" fill="#2583ff" />
    </svg>
</template>
