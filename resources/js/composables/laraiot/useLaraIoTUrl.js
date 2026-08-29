import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

export function useLaraIoTUrl() {
    const page = usePage();

    const baseUrl = computed(() => {
        const configured = page.props.laraiot?.baseUrl ?? '/laraiot';
        const normalized = String(configured).trim().replace(/^\/+|\/+$/g, '');

        return normalized === ''
            ? ''
            : `/${normalized}`;
    });

    const laraiotUrl = (path = '') => {
        const normalizedPath = String(path).trim().replace(/^\/+|\/+$/g, '');

        if (normalizedPath === '') {
            return baseUrl.value || '/';
        }

        return `${baseUrl.value}/${normalizedPath}`;
    };

    return {
        baseUrl,
        laraiotUrl,
    };
}
