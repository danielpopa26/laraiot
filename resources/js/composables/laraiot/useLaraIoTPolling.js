import { onBeforeUnmount, onMounted } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

import { websocketNeedsPollingFallback } from './useLaraIoTWebSocketHealth.js';

export function useLaraIoTPolling(only = [], enabled = true) {
    const page = usePage();
    let timer = null;

    const reload = () => {
        const pollingEnabled = typeof enabled === 'function'
            ? enabled()
            : enabled;

        if (!pollingEnabled) {
            return;
        }

        if (
            page.props.laraiot?.mode !== 'polling'
            && !websocketNeedsPollingFallback(
                page.props.laraiot ?? {},
            )
        ) {
            return;
        }

        if (
            typeof document !== 'undefined'
            && document.visibilityState !== 'visible'
        ) {
            return;
        }

        const options = {
            preserveScroll: true,
            preserveState: true,
        };

        if (only.length > 0) {
            options.only = only;
        }

        router.reload(options);
    };

    onMounted(() => {
        const interval = Math.max(
            1,
            Number(page.props.laraiot?.pollingInterval ?? 10),
        );

        timer = window.setInterval(
            reload,
            interval * 1000,
        );
    });

    onBeforeUnmount(() => {
        if (timer !== null) {
            window.clearInterval(timer);
        }
    });

    return { reload };
}
