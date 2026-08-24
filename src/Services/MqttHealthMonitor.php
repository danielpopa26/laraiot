<?php

declare(strict_types=1);

namespace Danpopa\LaraIoT\Services;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Support\Carbon;
use Throwable;

final class MqttHealthMonitor
{
    public function __construct(
        private readonly CacheRepository $cache,
        private readonly ConfigRepository $config,
    ) {}

    public function markConnected(
        int $subscriptions = 0,
    ): void {
        $state = $this->storedState() ?? [];
        $timestamp = $this->timestamp();

        $this->persist([
            ...$state,
            'status' => 'connected',
            'connected' => true,
            'connected_at' => ($state['connected'] ?? false) === true
                ? ($state['connected_at'] ?? $timestamp)
                : $timestamp,
            'heartbeat_at' => $timestamp,
            'updated_at' => $timestamp,
            'subscriptions' => max(0, $subscriptions),
            'error' => null,
        ]);
    }

    public function heartbeat(
        int $subscriptions = 0,
    ): void {
        $this->markConnected($subscriptions);
    }

    public function markMessageReceived(
        int $subscriptions = 0,
    ): void {
        $state = $this->storedState() ?? [];
        $timestamp = $this->timestamp();

        $this->persist([
            ...$state,
            'status' => 'connected',
            'connected' => true,
            'connected_at' => ($state['connected'] ?? false) === true
                ? ($state['connected_at'] ?? $timestamp)
                : $timestamp,
            'heartbeat_at' => $timestamp,
            'last_message_at' => $timestamp,
            'updated_at' => $timestamp,
            'subscriptions' => max(0, $subscriptions),
            'error' => null,
        ]);
    }

    public function markDisconnected(
        ?string $error = null,
    ): void {
        $state = $this->storedState() ?? [];
        $timestamp = $this->timestamp();

        $this->persist([
            ...$state,
            'status' => 'disconnected',
            'connected' => false,
            'updated_at' => $timestamp,
            'error' => $this->normalizeError($error),
        ]);
    }

    public function markStopped(): void
    {
        $state = $this->storedState() ?? [];

        $this->persist([
            ...$state,
            'status' => 'offline',
            'connected' => false,
            'updated_at' => $this->timestamp(),
            'error' => null,
        ]);
    }

    /**
     * @return array{
     *     connected: bool|null,
     *     status: string,
     *     label: string,
     *     detail: string,
     *     heartbeat_at: string|null,
     *     last_message_at: string|null,
     *     subscriptions: int,
     *     error: string|null
     * }
     */
    public function snapshot(): array
    {
        $state = $this->storedState();

        if ($state === null) {
            return $this->unknownSnapshot();
        }

        $status = (string) ($state['status'] ?? 'unknown');

        if (
            $status === 'connected'
            && $this->heartbeatIsStale(
                $state['heartbeat_at'] ?? null,
            )
        ) {
            $status = 'offline';
        }

        $subscriptions = max(
            0,
            (int) ($state['subscriptions'] ?? 0),
        );
        $error = $this->normalizeError(
            $state['error'] ?? null,
        );

        [$connected, $label, $detail] = match ($status) {
            'connected' => [
                true,
                'Connected',
                sprintf(
                    'MQTT listener active with %d state topic subscription%s.',
                    $subscriptions,
                    $subscriptions === 1 ? '' : 's',
                ),
            ],
            'disconnected' => [
                false,
                'Disconnected',
                $error ?? 'The MQTT listener could not connect to the broker.',
            ],
            'offline' => [
                false,
                'Listener Offline',
                'The MQTT listener is stopped or its heartbeat has expired.',
            ],
            default => [
                null,
                'Unknown',
                'The MQTT listener has not reported its status yet.',
            ],
        };

        return [
            'connected' => $connected,
            'status' => $status,
            'label' => $label,
            'detail' => $detail,
            'heartbeat_at' => $this->nullableString(
                $state['heartbeat_at'] ?? null,
            ),
            'last_message_at' => $this->nullableString(
                $state['last_message_at'] ?? null,
            ),
            'subscriptions' => $subscriptions,
            'error' => $error,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function storedState(): ?array
    {
        try {
            $state = $this->cache->get(
                $this->cacheKey(),
            );
        } catch (Throwable) {
            return null;
        }

        return is_array($state)
            ? $state
            : null;
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function persist(array $state): void
    {
        try {
            $this->cache->forever(
                $this->cacheKey(),
                $state,
            );
        } catch (Throwable) {
            /*
             * MQTT health reporting is observational. A cache
             * failure must never stop message processing.
             */
        }
    }

    private function heartbeatIsStale(
        mixed $heartbeatAt,
    ): bool {
        if (! is_string($heartbeatAt) || trim($heartbeatAt) === '') {
            return true;
        }

        try {
            return Carbon::parse($heartbeatAt)
                ->addSeconds($this->staleAfter())
                ->isPast();
        } catch (Throwable) {
            return true;
        }
    }

    private function cacheKey(): string
    {
        $key = trim((string) $this->config->get(
            'laraiot.mqtt.health.cache_key',
            'laraiot:mqtt:health',
        ));

        return $key !== ''
            ? $key
            : 'laraiot:mqtt:health';
    }

    private function staleAfter(): int
    {
        return max(
            1,
            (int) $this->config->get(
                'laraiot.mqtt.health.stale_after',
                20,
            ),
        );
    }

    private function timestamp(): string
    {
        return Carbon::now()->toIso8601String();
    }

    private function normalizeError(
        mixed $error,
    ): ?string {
        if (! is_string($error)) {
            return null;
        }

        $error = trim($error);

        return $error !== ''
            ? $error
            : null;
    }

    private function nullableString(
        mixed $value,
    ): ?string {
        return is_string($value) && trim($value) !== ''
            ? $value
            : null;
    }

    /**
     * @return array{
     *     connected: null,
     *     status: string,
     *     label: string,
     *     detail: string,
     *     heartbeat_at: null,
     *     last_message_at: null,
     *     subscriptions: int,
     *     error: null
     * }
     */
    private function unknownSnapshot(): array
    {
        return [
            'connected' => null,
            'status' => 'unknown',
            'label' => 'Unknown',
            'detail' => 'The MQTT listener has not reported its status yet.',
            'heartbeat_at' => null,
            'last_message_at' => null,
            'subscriptions' => 0,
            'error' => null,
        ];
    }
}
