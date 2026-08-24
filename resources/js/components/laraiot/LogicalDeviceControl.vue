<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import {
    Boxes,
    Gauge,
    LoaderCircle,
    Power,
    Radio,
    Settings2,
} from 'lucide-vue-next';

import StatusBadge from './StatusBadge.vue';
import { useLaraIoTUrl } from '../../composables/laraiot/useLaraIoTUrl.js';

const props = defineProps({
    device: {
        type: Object,
        required: true,
    },
    compact: {
        type: Boolean,
        default: false,
    },
    reloadProps: {
        type: Array,
        default: () => [],
    },
    showConfigurationLink: {
        type: Boolean,
        default: true,
    },
});

const { laraiotUrl } = useLaraIoTUrl();
const commandForm = useForm({ command: '' });
const pendingCommand = ref(null);
const confirmationFailed = ref(false);
let pollTimer = null;
let deadlineTimer = null;

const formattedValue = computed(() => {
    const value = props.device.last_value;

    if (value === null || value === undefined || value === '') {
        return '—';
    }

    const formatted = typeof value === 'boolean'
        ? (value ? 'TRUE' : 'FALSE')
        : typeof value === 'object'
            ? JSON.stringify(value)
            : String(value);

    return props.device.unit
        ? `${formatted} ${props.device.unit}`
        : formatted;
});

const currentState = computed(() =>
    props.device.control?.current_state ?? null,
);

const hasCommandTopic = computed(() =>
    props.device.command_topic !== null
    && props.device.command_topic !== undefined,
);

const canControl = computed(() =>
    props.device.control?.available === true
    && props.device.control?.command_topic_id !== null,
);

const requestedCommand = computed(() =>
    currentState.value === 'on' ? 'off' : 'on',
);

const stateLabel = computed(() => {
    if (currentState.value === 'on') return 'ON';
    if (currentState.value === 'off') return 'OFF';

    return 'Unknown';
});

const stateTone = computed(() =>
    currentState.value === 'on'
        ? 'success'
        : currentState.value === 'off'
            ? 'neutral'
            : 'warning',
);

const buttonLabel = computed(() => {
    if (pendingCommand.value === 'on') return 'Turning on...';
    if (pendingCommand.value === 'off') return 'Turning off...';
    if (!canControl.value) return 'Control unavailable';

    return requestedCommand.value === 'on'
        ? 'Turn On'
        : 'Turn Off';
});

const buttonClasses = computed(() =>
    requestedCommand.value === 'off'
        ? 'border-red-200 bg-red-50 text-red-700 hover:bg-red-100'
        : 'border-blue-600 bg-[#2583FF] text-white hover:bg-blue-600',
);

const clearTimers = () => {
    if (pollTimer !== null) {
        window.clearTimeout(pollTimer);
        pollTimer = null;
    }

    if (deadlineTimer !== null) {
        window.clearTimeout(deadlineTimer);
        deadlineTimer = null;
    }
};

const finishConfirmation = () => {
    clearTimers();
    pendingCommand.value = null;
    confirmationFailed.value = false;
};

const reloadUntilConfirmed = () => {
    if (pendingCommand.value === null) {
        return;
    }

    const options = {
        preserveScroll: true,
        preserveState: true,
        onFinish: () => {
            if (pendingCommand.value !== null) {
                pollTimer = window.setTimeout(
                    reloadUntilConfirmed,
                    1000,
                );
            }
        },
    };

    if (props.reloadProps.length > 0) {
        options.only = props.reloadProps;
    }

    router.reload(options);
};

const waitForConfirmation = (command) => {
    pendingCommand.value = command;
    confirmationFailed.value = false;

    const timeout = Math.max(
        1,
        Number(
            props.device.control?.confirmation_timeout ?? 10,
        ),
    );

    deadlineTimer = window.setTimeout(() => {
        if (pendingCommand.value !== null) {
            confirmationFailed.value = true;
            pendingCommand.value = null;

            if (pollTimer !== null) {
                window.clearTimeout(pollTimer);
                pollTimer = null;
            }
        }
    }, timeout * 1000);
};

const sendCommand = () => {
    if (!canControl.value || pendingCommand.value !== null) {
        return;
    }

    const command = requestedCommand.value;

    if (
        command === 'on'
        && !window.confirm(`Turn on "${props.device.name}"?`)
    ) {
        return;
    }

    commandForm.clearErrors();
    commandForm.command = command;
    waitForConfirmation(command);

    commandForm.post(
        laraiotUrl(
            `devices/logical/${props.device.id}/mqtt-topics/${props.device.control.command_topic_id}/command`,
        ),
        {
            preserveScroll: true,
            onSuccess: () => {
                if (currentState.value === command) {
                    finishConfirmation();
                    return;
                }

                pollTimer = window.setTimeout(
                    reloadUntilConfirmed,
                    500,
                );
            },
            onError: () => {
                clearTimers();
                pendingCommand.value = null;
            },
        },
    );
};

watch(currentState, (state) => {
    if (
        pendingCommand.value !== null
        && state === pendingCommand.value
    ) {
        finishConfirmation();
    }
});

onBeforeUnmount(clearTimers);
</script>

<template>
    <div
        class="rounded-xl border border-slate-200 bg-slate-50/70"
        :class="compact ? 'p-4' : 'p-5'"
    >
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <Boxes class="size-4 text-[#2583FF]" />
                    <Link
                        :href="laraiotUrl(`devices/logical/${device.id}`)"
                        class="truncate text-sm font-semibold text-[#0B1735] hover:text-[#2583FF]"
                    >
                        {{ device.name }}
                    </Link>
                    <StatusBadge
                        :label="device.configuration.label"
                        :status="device.configuration.tone"
                    />
                </div>

                <p class="mt-1 text-xs text-slate-500">
                    {{ device.device_type?.name ?? 'Logical device' }}
                    <span class="px-1 text-slate-300">·</span>
                    <code>{{ device.identifier }}</code>
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <div class="min-w-24 rounded-lg border border-slate-200 bg-white px-3 py-2">
                    <div class="flex items-center gap-1.5 text-[11px] font-medium uppercase tracking-wide text-slate-400">
                        <Gauge class="size-3.5" />
                        Current value
                    </div>
                    <p class="mt-1 text-lg font-semibold text-[#0B1735]">
                        {{ formattedValue }}
                    </p>
                </div>

                <div
                    v-if="hasCommandTopic"
                    class="flex items-center gap-2"
                >
                    <StatusBadge
                        :label="stateLabel"
                        :status="stateTone"
                        size="md"
                    />

                    <button
                        type="button"
                        :disabled="!canControl || pendingCommand !== null || commandForm.processing"
                        class="inline-flex min-w-36 items-center justify-center gap-2 rounded-lg border px-4 py-2.5 text-sm font-semibold transition disabled:cursor-not-allowed disabled:border-slate-200 disabled:bg-slate-100 disabled:text-slate-400"
                        :class="canControl ? buttonClasses : ''"
                        @click="sendCommand"
                    >
                        <LoaderCircle
                            v-if="pendingCommand !== null || commandForm.processing"
                            class="size-4 animate-spin"
                        />
                        <Power v-else class="size-4" />
                        {{ buttonLabel }}
                    </button>
                </div>
            </div>
        </div>

        <div class="mt-3 flex flex-col gap-2 border-t border-slate-200 pt-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-start gap-2 text-xs text-slate-500">
                <Radio class="mt-0.5 size-3.5 shrink-0" />
                <span>{{ device.configuration.message }}</span>
            </div>

            <Link
                v-if="showConfigurationLink"
                :href="laraiotUrl(`devices/logical/${device.id}`)"
                class="inline-flex shrink-0 items-center gap-1.5 text-xs font-medium text-[#2583FF] hover:text-blue-700"
            >
                <Settings2 class="size-3.5" />
                View and configure
            </Link>
        </div>

        <p
            v-if="commandForm.errors.command"
            class="mt-3 text-xs font-medium text-red-600"
        >
            {{ commandForm.errors.command }}
        </p>

        <p
            v-else-if="confirmationFailed"
            class="mt-3 text-xs font-medium text-amber-700"
        >
            The command was published, but the expected MQTT state was not confirmed in time.
        </p>
    </div>
</template>
