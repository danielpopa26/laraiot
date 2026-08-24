import { onBeforeUnmount, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';

export const websocketConnectionStatus = ref('idle');

let active = false;
let healthSocket = null;
let reconnectTimer = null;
let connectionGeneration = 0;
let currentClient = null;
let currentReconnectInterval = 3;

const clearReconnectTimer = () => {
    if (reconnectTimer !== null) {
        window.clearTimeout(reconnectTimer);
        reconnectTimer = null;
    }
};

const closeHealthSocket = () => {
    if (healthSocket === null) {
        return;
    }

    const socket = healthSocket;
    healthSocket = null;
    socket.onopen = null;
    socket.onmessage = null;
    socket.onerror = null;
    socket.onclose = null;
    socket.close();
};

const websocketUrl = (client) => {
    const secure = String(client.scheme ?? 'http').toLowerCase() === 'https';
    const protocol = secure ? 'wss' : 'ws';
    const host = String(client.host ?? '').trim();
    const socketHost = host.includes(':') && !host.startsWith('[')
        ? `[${host}]`
        : host;
    const port = Number(client.port ?? (secure ? 443 : 80));
    const defaultPort = secure ? 443 : 80;
    const portSuffix = port === defaultPort ? '' : `:${port}`;
    const key = encodeURIComponent(String(client.key ?? ''));

    return `${protocol}://${socketHost}${portSuffix}/app/${key}?protocol=7&client=laraiot-health&version=1.0&flash=false`;
};

const scheduleReconnect = () => {
    clearReconnectTimer();

    if (!active || currentClient === null) {
        return;
    }

    reconnectTimer = window.setTimeout(
        connect,
        Math.max(1, currentReconnectInterval) * 1000,
    );
};

const connect = () => {
    clearReconnectTimer();
    closeHealthSocket();

    if (!active || currentClient === null) {
        websocketConnectionStatus.value = 'idle';
        return;
    }

    if (typeof window.WebSocket !== 'function') {
        websocketConnectionStatus.value = 'unavailable';
        return;
    }

    const generation = ++connectionGeneration;
    websocketConnectionStatus.value = 'connecting';

    try {
        healthSocket = new window.WebSocket(
            websocketUrl(currentClient),
        );
    } catch {
        websocketConnectionStatus.value = 'unavailable';
        scheduleReconnect();
        return;
    }

    healthSocket.onmessage = (message) => {
        if (generation !== connectionGeneration) {
            return;
        }

        let payload;

        try {
            payload = JSON.parse(message.data);
        } catch {
            return;
        }

        if (payload.event === 'pusher:connection_established') {
            websocketConnectionStatus.value = 'connected';
            return;
        }

        if (payload.event === 'pusher:ping') {
            healthSocket?.send(JSON.stringify({
                event: 'pusher:pong',
                data: {},
            }));
            return;
        }

        if (payload.event === 'pusher:error') {
            websocketConnectionStatus.value = 'unavailable';
            healthSocket?.close();
        }
    };

    healthSocket.onerror = () => {
        if (generation === connectionGeneration) {
            websocketConnectionStatus.value = 'unavailable';
            healthSocket?.close();
        }
    };

    healthSocket.onclose = () => {
        if (generation !== connectionGeneration) {
            return;
        }

        healthSocket = null;
        websocketConnectionStatus.value = 'disconnected';
        scheduleReconnect();
    };
};

const stop = () => {
    active = false;
    connectionGeneration += 1;
    clearReconnectTimer();
    closeHealthSocket();
    websocketConnectionStatus.value = 'idle';
};

export const websocketNeedsPollingFallback = (laraiot = {}) => {
    if (laraiot.requestedMode !== 'websocket') {
        return false;
    }

    if (laraiot.websocket?.live !== true) {
        return true;
    }

    return websocketConnectionStatus.value !== 'connected';
};

export function useLaraIoTWebSocketHealth() {
    const page = usePage();

    const stopWatching = watch(
        () => ({
            requestedMode: page.props.laraiot?.requestedMode,
            serverLive: page.props.laraiot?.websocket?.live === true,
            client: page.props.laraiot?.websocket?.client ?? null,
            reconnectInterval: Number(
                page.props.laraiot?.websocket?.reconnect_interval ?? 3,
            ),
        }),
        (configuration) => {
            const shouldConnect = configuration.requestedMode === 'websocket'
                && configuration.serverLive
                && configuration.client !== null;

            if (!shouldConnect) {
                stop();
                return;
            }

            const clientChanged = JSON.stringify(currentClient)
                !== JSON.stringify(configuration.client);

            currentClient = configuration.client;
            currentReconnectInterval = Math.max(
                1,
                configuration.reconnectInterval,
            );
            active = true;

            if (
                clientChanged
                || healthSocket === null
                || websocketConnectionStatus.value === 'idle'
            ) {
                connect();
            }
        },
        {
            deep: true,
            immediate: true,
        },
    );

    onBeforeUnmount(() => {
        stopWatching();
        stop();
    });

    return {
        status: websocketConnectionStatus,
    };
}
