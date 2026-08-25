<?php

declare(strict_types=1);

namespace Danpopa\LaraIoT\Support\Reverb;

use Closure;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * @phpstan-type ReverbClient array{
 *     key: string,
 *     host: string,
 *     port: int,
 *     scheme: string
 * }
 * @phpstan-type ReverbSnapshot array{
 *     configured: bool,
 *     live: bool,
 *     selectable: bool,
 *     status: string,
 *     label: string,
 *     detail: string,
 *     checked_at: string,
 *     client: ReverbClient|null,
 *     reconnect_interval: int
 * }
 */
final class ReverbHealthMonitor
{
    private readonly Closure $probe;

    public function __construct(
        private readonly CacheRepository $cache,
        private readonly ConfigRepository $config,
        ?Closure $probe = null,
    ) {
        $this->probe = $probe ?? $this->probeServer(...);
    }

    /**
     * @return ReverbSnapshot
     */
    public function snapshot(bool $force = false): array
    {
        $runtime = $this->runtimeConfiguration();

        if (! $runtime['configured']) {
            return $this->notConfiguredSnapshot();
        }

        if (! $force) {
            $cached = $this->cachedSnapshot();

            if ($cached !== null) {
                return $cached;
            }
        }

        try {
            $live = (bool) ($this->probe)(
                $runtime['server'],
            );
        } catch (Throwable) {
            $live = false;
        }

        $snapshot = [
            'configured' => true,
            'live' => $live,
            'selectable' => $live,
            'status' => $live ? 'live' : 'offline',
            'label' => $live ? 'Server Live' : 'Server Offline',
            'detail' => $live
                ? 'Laravel Reverb is accepting WebSocket connections.'
                : 'Laravel Reverb is configured, but the WebSocket server is not responding. Start it with "php artisan reverb:start".',
            'checked_at' => Carbon::now()->toIso8601String(),
            'client' => $runtime['client'],
            'reconnect_interval' => $this->reconnectInterval(),
        ];

        $this->cacheSnapshot($snapshot);

        return $snapshot;
    }

    /**
     * @return array{
     *     configured: bool,
     *     server: array{host: string, port: int, hostname: string, secure: bool, app_key: string, origin: string},
     *     client: array{key: string, host: string, port: int, scheme: string}|null
     * }
     */
    private function runtimeConfiguration(): array
    {
        $connectionName = trim((string) $this->config->get(
            'laraiot.websocket.connection',
            '',
        ));

        if ($connectionName === '') {
            $connectionName = trim((string) $this->config->get(
                'broadcasting.default',
                '',
            ));
        }

        $broadcastConnection = $this->arrayValue(
            $this->config->get(
                'broadcasting.connections.'.$connectionName,
                [],
            ),
        );

        $serverName = trim((string) $this->config->get(
            'reverb.default',
            'reverb',
        ));

        $server = $this->arrayValue(
            $this->config->get(
                'reverb.servers.'.$serverName,
                [],
            ),
        );

        $applications = $this->config->get(
            'reverb.apps.apps',
            [],
        );

        $application = $this->firstApplication($applications);
        $applicationOptions = $this->arrayValue(
            $application['options'] ?? [],
        );

        $appKey = trim((string) (
            $application['key']
                ?? $broadcastConnection['key']
                ?? ''
        ));

        $serverHost = $this->connectableHost(
            (string) ($server['host'] ?? ''),
        );
        $serverPort = max(0, (int) ($server['port'] ?? 0));
        $serverHostname = trim((string) (
            $server['hostname']
                ?? $applicationOptions['host']
                ?? $serverHost
        ));

        $clientHost = trim((string) (
            $applicationOptions['host']
                ?? $server['hostname']
                ?? ''
        ));
        $clientPort = max(
            0,
            (int) ($applicationOptions['port'] ?? 0),
        );
        $clientScheme = strtolower(trim((string) (
            $applicationOptions['scheme']
                ?? 'http'
        )));

        if ($clientScheme === 'ws') {
            $clientScheme = 'http';
        } elseif ($clientScheme === 'wss') {
            $clientScheme = 'https';
        }

        $client = $appKey !== ''
            && $clientHost !== ''
            && $clientPort > 0
            && in_array($clientScheme, ['http', 'https'], true)
                ? [
                    'key' => $appKey,
                    'host' => $clientHost,
                    'port' => $clientPort,
                    'scheme' => $clientScheme,
                ]
                : null;

        $tls = $this->arrayValue(
            $server['options']['tls'] ?? [],
        );
        $secure = $this->hasTlsConfiguration($tls);

        $configured = ($broadcastConnection['driver'] ?? null) === 'reverb'
            && $serverHost !== ''
            && $serverPort > 0
            && $appKey !== ''
            && $client !== null;

        return [
            'configured' => $configured,
            'server' => [
                'host' => $serverHost,
                'port' => $serverPort,
                'hostname' => $serverHostname !== ''
                    ? $serverHostname
                    : $serverHost,
                'secure' => $secure,
                'app_key' => $appKey,
                'origin' => $this->applicationOrigin(
                    $serverHostname !== ''
                        ? $serverHostname
                        : $serverHost,
                    $secure,
                ),
            ],
            'client' => $client,
        ];
    }

    /**
     * Perform a Pusher-compatible WebSocket upgrade instead of relying only
     * on an open TCP port. A 101 response confirms that Reverb recognizes the
     * configured application key and accepts WebSocket connections.
     *
     * @param  array{host: string, port: int, hostname: string, secure: bool, app_key: string, origin: string}  $server
     */
    private function probeServer(array $server): bool
    {
        $transport = $server['secure'] ? 'tls' : 'tcp';
        $host = $this->socketHost($server['host']);
        $contextOptions = [];

        if ($server['secure']) {
            $contextOptions['ssl'] = [
                'peer_name' => $server['hostname'],
                'SNI_enabled' => true,
            ];
        }

        $socket = @stream_socket_client(
            sprintf(
                '%s://%s:%d',
                $transport,
                $host,
                $server['port'],
            ),
            $errorCode,
            $errorMessage,
            $this->timeout(),
            STREAM_CLIENT_CONNECT,
            stream_context_create($contextOptions),
        );

        if (! is_resource($socket)) {
            return false;
        }

        try {
            stream_set_timeout(
                $socket,
                max(1, (int) ceil($this->timeout())),
            );

            $remainingRequest = $this->handshakeRequest(
                $server,
            );

            while ($remainingRequest !== '') {
                $written = @fwrite(
                    $socket,
                    $remainingRequest,
                );

                if ($written === false || $written === 0) {
                    return false;
                }

                $remainingRequest = substr(
                    $remainingRequest,
                    $written,
                );
            }

            $statusLine = @fgets($socket, 4096);

            return is_string($statusLine)
                && preg_match(
                    '/^HTTP\/\d(?:\.\d)?\s+101\b/i',
                    trim($statusLine),
                ) === 1;
        } finally {
            fclose($socket);
        }
    }

    /**
     * @param  array{host: string, port: int, hostname: string, secure: bool, app_key: string, origin: string}  $server
     */
    private function handshakeRequest(array $server): string
    {
        $key = base64_encode(random_bytes(16));
        $path = '/app/'.rawurlencode($server['app_key'])
            .'?protocol=7&client=laraiot-health&version=1.0&flash=false';

        return implode("\r\n", [
            'GET '.$path.' HTTP/1.1',
            'Host: '.$server['hostname'].':'.$server['port'],
            'Upgrade: websocket',
            'Connection: Upgrade',
            'Sec-WebSocket-Key: '.$key,
            'Sec-WebSocket-Version: 13',
            'Origin: '.$server['origin'],
            '',
            '',
        ]);
    }

    /**
     * @return ReverbSnapshot|null
     */
    private function cachedSnapshot(): ?array
    {
        try {
            $snapshot = $this->cache->get(
                $this->cacheKey(),
            );
        } catch (Throwable) {
            return null;
        }

        return $this->isSnapshot($snapshot)
            ? $snapshot
            : null;
    }

    /**
     * @param  ReverbSnapshot  $snapshot
     */
    private function cacheSnapshot(array $snapshot): void
    {
        try {
            $this->cache->put(
                $this->cacheKey(),
                $snapshot,
                $this->cacheTtl(),
            );
        } catch (Throwable) {
            /*
             * WebSocket health is observational. Cache failures must not make
             * the LaraIoT UI unavailable.
             */
        }
    }

    /**
     * @return ReverbSnapshot
     */
    private function notConfiguredSnapshot(): array
    {
        return [
            'configured' => false,
            'live' => false,
            'selectable' => false,
            'status' => 'not_configured',
            'label' => 'Not Configured',
            'detail' => 'Laravel Reverb or its frontend connection settings are incomplete. Run "php artisan laraiot:install" to configure WebSocket support.',
            'checked_at' => Carbon::now()->toIso8601String(),
            'client' => null,
            'reconnect_interval' => $this->reconnectInterval(),
        ];
    }

    /**
     * @phpstan-assert-if-true ReverbSnapshot $snapshot
     */
    private function isSnapshot(mixed $snapshot): bool
    {
        if (! is_array($snapshot)) {
            return false;
        }

        $client = $snapshot['client'] ?? null;
        $clientIsValid = $client === null
            || (
                is_array($client)
                && is_string($client['key'] ?? null)
                && is_string($client['host'] ?? null)
                && is_int($client['port'] ?? null)
                && is_string($client['scheme'] ?? null)
            );

        return is_bool($snapshot['configured'] ?? null)
            && is_bool($snapshot['live'] ?? null)
            && is_bool($snapshot['selectable'] ?? null)
            && is_string($snapshot['status'] ?? null)
            && is_string($snapshot['label'] ?? null)
            && is_string($snapshot['detail'] ?? null)
            && is_string($snapshot['checked_at'] ?? null)
            && array_key_exists('client', $snapshot)
            && $clientIsValid
            && is_int($snapshot['reconnect_interval'] ?? null);
    }

    /**
     * @return array<string, mixed>
     */
    private function firstApplication(mixed $applications): array
    {
        if (! is_array($applications)) {
            return [];
        }

        foreach ($applications as $application) {
            if (is_array($application)) {
                return $application;
            }
        }

        return [];
    }

    /**
     * @return array<string, mixed>
     */
    private function arrayValue(mixed $value): array
    {
        return is_array($value)
            ? $value
            : [];
    }

    /**
     * @param  array<string, mixed>  $tls
     */
    private function hasTlsConfiguration(array $tls): bool
    {
        foreach ($tls as $value) {
            if ($value !== null && $value !== '' && $value !== false) {
                return true;
            }
        }

        return false;
    }

    private function connectableHost(string $host): string
    {
        $host = trim($host);

        return match ($host) {
            '0.0.0.0' => '127.0.0.1',
            '::', '[::]' => '::1',
            default => $host,
        };
    }

    private function socketHost(string $host): string
    {
        return str_contains($host, ':')
            ? '['.trim($host, '[]').']'
            : $host;
    }

    private function applicationOrigin(
        string $fallbackHostname,
        bool $secure,
    ): string {
        $applicationUrl = trim((string) $this->config->get(
            'app.url',
            '',
        ));
        $parts = parse_url($applicationUrl);

        if (
            is_array($parts)
            && in_array($parts['scheme'] ?? null, ['http', 'https'], true)
            && is_string($parts['host'] ?? null)
            && $parts['host'] !== ''
        ) {
            $origin = $parts['scheme'].'://'.$parts['host'];

            if (isset($parts['port'])) {
                $origin .= ':'.(int) $parts['port'];
            }

            return $origin;
        }

        return ($secure ? 'https' : 'http')
            .'://'
            .$fallbackHostname;
    }

    private function cacheKey(): string
    {
        $key = trim((string) $this->config->get(
            'laraiot.websocket.health.cache_key',
            'laraiot:websocket:health',
        ));

        return $key !== ''
            ? $key
            : 'laraiot:websocket:health';
    }

    private function cacheTtl(): int
    {
        return max(
            1,
            (int) $this->config->get(
                'laraiot.websocket.health.cache_ttl',
                5,
            ),
        );
    }

    private function timeout(): float
    {
        return max(
            0.1,
            (float) $this->config->get(
                'laraiot.websocket.health.timeout',
                1.0,
            ),
        );
    }

    private function reconnectInterval(): int
    {
        return max(
            1,
            (int) $this->config->get(
                'laraiot.websocket.health.reconnect_interval',
                3,
            ),
        );
    }
}
